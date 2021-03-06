<?php

namespace Saseul\Daemon;

use Saseul\Common\Daemon;
use Saseul\Consensus\CommitManager;
use Saseul\Consensus\HashManager;
use Saseul\Consensus\RoundManager;
use Saseul\Consensus\SourceManager;
use Saseul\Consensus\SyncManager;
use Saseul\Consensus\TrackerManager;
use Saseul\Constant\Event;
use Saseul\Constant\Role;
use Saseul\Constant\Structure;
use Saseul\Core\Block;
use Saseul\Core\Chunk;
use Saseul\Core\Property;
use Saseul\Core\Tracker;
use Saseul\Util\Logger;
use Saseul\Util\Merkle;
use Saseul\Util\TypeChecker;

class Node
{
    protected static $instance;
    protected $log;
    protected $steramLog;
    protected $fail_count = 0;

    protected $round_manager;
    protected $commit_manager;
    protected $hash_manager;
    protected $sync_manager;
    protected $source_manager;
    protected $tracker_manager;

    protected $excludedHosts = [];

    protected $stime = 0;
    protected $heartbeat = 0;
    protected $length = 5;
    protected $netLastRoundNumber = 0;

    // TODO: Static 에 대해서 고민 후 설계 변경
    public function __construct()
    {
        $this->round_manager = RoundManager::GetInstance();
        $this->commit_manager = CommitManager::GetInstance();
        $this->hash_manager = HashManager::GetInstance();
        $this->sync_manager = SyncManager::GetInstance();
        $this->source_manager = SourceManager::GetInstance();
        $this->tracker_manager = TrackerManager::GetInstance();

        $this->tracker_manager->GenerateTracker();
        $this->log = Logger::getLogger(Logger::DAEMON);
        $this->steramLog = Logger::getStreamLogger(Logger::DAEMON);

        Tracker::setMyHost();
    }

    // TODO: Node 를 상속 받는 클래스들이 메서드를 재 정의하고 있음 이유 확인
    public static function GetInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function round()
    {
        $this->log->debug('Nothiong');
        sleep(1);
    }

    protected function mergedNode($nodes)
    {
        $mergedNode = $nodes;
        $hosts = [];

        foreach ($mergedNode as $node) {
            $hosts[] = $node['host'];
        }

        foreach (Property::registerRequest() as $key => $node) {
            if (!TypeChecker::StructureCheck(Structure::TRACKER, $node)) {
                continue;
            }

            if (!in_array($node['host'], $hosts)) {
                $mergedNode[] = $node;
            }
        }

        return $mergedNode;
    }

    protected function finishingWork(string $result): void
    {
        // TODO: ban이 리스크를 가짐.
        switch ($result) {
            case Event::DIFFERENT:
                $this->ban();

                break;
            case Event::NO_RESULT:
            case Event::WAIT:
                $this->resetFailCount();
                $this->exclude();

                break;
            case Event::NOTHING:
            case Event::SUCCESS:
            default:
                $this->resetFailCount();
                $this->resetexcludedHosts();

                break;
        }
    }

    // TODO: 접근 지정자를 public 에서 protected 으로 변경
    protected function setLength($completeTime = 0)
    {
        $length = (int) ($completeTime + $this->heartbeat * 5) + 1;

        if ($length < 1) {
            $length = 1;
        }

        if ($length > 10) {
            $length = 10;
        }

        $this->length = $length;
    }

    protected function aliveNodes(array $nodes, array $alives)
    {
        $aliveNodes = [];
        $excludedHosts = $this->excludedHosts;

        foreach ($nodes as $node) {
            if (in_array($node['address'], $alives) && !in_array($node['host'], $excludedHosts)) {
                $aliveNodes[] = $node;
            }
        }

        return $aliveNodes;
    }

    // TODO: Validator 에서만 사용되고 있으므로 옮길 수 있는지 고려
    protected function subjectNodeByAddress(array $nodes, string $address)
    {
        $subjectNode = [];

        foreach ($nodes as $node) {
            if ($node['address'] === $address) {
                $subjectNode = $node;

                break;
            }
        }

        return $subjectNode;
    }

    // TODO: Light 클래스에서만 사용되므로 옮길 수 있는지 고려
    protected function aliveArbiters(array $nodes)
    {
        $aliveArbiters = [];

        foreach ($nodes as $node) {
            if ($node['role'] === Role::ARBITER) {
                $aliveArbiters[] = $node;
            }
        }

        return $aliveArbiters;
    }

    // TODO: Validator 클래스에서만 사용되므로 옮길 수 있는지 고려
    protected function validators(array $nodes)
    {
        $allValidators = Tracker::getValidatorAddress();
        $validators = [];

        foreach ($nodes as $node) {
            if (in_array($node['address'], $allValidators)) {
                $validators[] = $node;
            }
        }

        return $validators;
    }

    // TODO: Light 클래스에서만 사용되므로 옮길 수 있는지 고려 및 메서드 이름 명확하게 변경
    protected function update($aliveArbiters, $generation, $myRoundNumber): void
    {
        $originBlockhash = $generation['origin_blockhash'];
        $nextBlockNumber = $myRoundNumber;

        $netGenerationInfo = $this->source_manager->netGenerationInfo($aliveArbiters, $nextBlockNumber, $originBlockhash);
        $this->log->debug('network generation info', [$netGenerationInfo]);

        if (count($netGenerationInfo) === 0) {
            return;
        }

        $target = $this->source_manager->selectGenerationInfo($netGenerationInfo);
        $targetInfo = $target['item'];
        // Todo: 해당 주석부분을 확인해서 변경해야한다.
        // $unique = $target['unique'];
        //
        // if ($unique === false) {
        //    # fork 인지 ;; 정보 기록 필요함.
        //    # 필요에 따라서 네트워크 ban;
        // }

        $host = $targetInfo['host'];
        $targetSourceHash = $targetInfo['source_hash'];
        $targetSourceVersion = $targetInfo['source_version'];

        $mySourceHash = Property::sourceHash();
        $mySourceVersion = Property::sourceVersion();

        // collect source hashs
        $sourcehashs = $this->source_manager->collectSourcehashs($netGenerationInfo, $targetInfo);

        $this->log->debug(
            'source hash',
            [
                'target source hash' => ['target info' => $target, 'source hash' => $sourcehashs],
                'my source hash' => ['source hash' => $mySourceHash, 'source version' => $mySourceVersion]
            ]
        );

        if (in_array($mySourceHash, $sourcehashs) || $mySourceVersion === $targetSourceVersion) {
            return;
        }

        Property::subjectNode($this->subjectNodeByHost($aliveArbiters, $host));

        // source change
        $sourceArchive = $this->source_manager->getSource($host, $myRoundNumber);
        $this->log->debug('Source archive', ['host' => $host, 'my round number' => $myRoundNumber, 'archive' => $sourceArchive]);

        if ($sourceArchive === '') {
            // TODO: source 다른 놈한테 받아야 함.
            return;
        }

        $sourceFolder = $this->source_manager->restoreSource($sourceArchive, $targetSourceHash);
        $this->source_manager->changeSourceFolder($sourceFolder);

        sleep(1);
        Daemon::restart();
    }

    // TODO: 메서드 이름을 무엇을 싱크하고 있는지 변경 필요
    protected function sync($aliveNodes, $lastBlock, $myRoundNumber, $netRoundNumber): string
    {
        $netBunch = Block::bunchFinalNumber($netRoundNumber);
        $myBunch = Block::bunchFinalNumber($myRoundNumber);

        if ($netBunch !== $myBunch) {
            $this->log->debug('Node sync message', ['type' => 'sync', 'data' => 'bunch']);
            $syncResult = $this->syncBunch($aliveNodes, $myRoundNumber);
        } else {
            $this->log->debug('Node sync message', ['type' => 'sync', 'data' => 'block']);
            $syncResult = $this->syncBlock($aliveNodes, $lastBlock, $myRoundNumber);
        }
        $this->log->debug('Node sync message', ['type' => 'sync', 'result' => $syncResult]);

        return $syncResult;
    }

    // TODO: 무엇을 제거하는지 명확한 메서드 명 정의 필요
    private function exclude(): void
    {
        $subjectNode = Property::subjectNode();

        if ($subjectNode['host'] && $subjectNode['host'] !== '') {
            $this->excludedHosts[$subjectNode['host']];
        }
    }

    // TODO: 메서드 이름 재 고려
    private function resetExcludedHosts()
    {
        // TODO: 바로 리셋해도 별 문제 없을까?
        $this->excludedHosts = [];
    }

    private function resetFailCount()
    {
        $this->fail_count = 0;
    }

    private function increaseFailCount()
    {
        $this->fail_count++;
    }

    private function isTimeToSeparation()
    {
        return $this->fail_count >= 5;
    }

    private function subjectNodeByHost(array $nodes, string $host)
    {
        $subjectNode = [];

        foreach ($nodes as $node) {
            if ($node['host'] === $host) {
                $subjectNode = $node;

                break;
            }
        }

        return $subjectNode;
    }

    private function syncBlock($aliveNodes, $lastBlock, $myRoundNumber): string
    {
        $netBlockInfo = $this->sync_manager->netBlockInfo($aliveNodes, $myRoundNumber);

        if (count($netBlockInfo) === 0) {
            $this->log->debug('Network block info', ['type' => 'sync', 'result' => false, 'data' => $netBlockInfo]);

            return Event::NOTHING;
        }

        $target = $this->sync_manager->selectBlockInfo($netBlockInfo);
        $blockInfo = $target['item'];

        $host = $blockInfo['host'];

        Property::subjectNode($this->subjectNodeByHost($aliveNodes, $host));

        $nextBlockhash = $blockInfo['blockhash'];
        $nextStandardTimestamp = $blockInfo['s_timestamp'];

        $transactions = $this->sync_manager->getBlockFile($host, $myRoundNumber);

        return $this->syncCommit($transactions, $lastBlock, $nextBlockhash, $nextStandardTimestamp);
    }

    private function syncBunch($aliveNodes, $myRoundNumber): string
    {
        $netBunchInfo = $this->sync_manager->netBunchInfo($aliveNodes, $myRoundNumber);

        if (count($netBunchInfo) === 0) {
            $this->log->debug('Network branch info', ['type' => 'sync', 'result' => false, 'data' => $netBunchInfo]);

            return Event::NOTHING;
        }

        $target = $this->sync_manager->selectBunchInfo($netBunchInfo);
        $this->log->debug('bunch target', [$target]);
        $bunchInfo = $target['item'];
        // $unique = $target['unique'];
        //
        // if ($unique === false) {
        //    # fork 인지 ;; 정보 기록 필요함.
        //    # 필요에 따라서 네트워크 ban;
        // }

        $host = $bunchInfo['host'];
        Property::subjectNode($this->subjectNodeByHost($aliveNodes, $host));

        $nextBlockhash = $bunchInfo['blockhash'];
        $bunch = $this->sync_manager->getBunchFile($host, $myRoundNumber);
        $this->log->debug('bunch', [$bunch]);

        if ($bunch === '') {
            // Todo: 해당 부분에서는 따로 값을 가져오지 않아도 되는가?
            $this->log->debug('Get bunch file', ['type' => 'sync', 'result' => false]);

            return Event::NO_RESULT;
        }

        $tempBunch = $this->sync_manager->makeTempBunch($bunch);
        $chunks = $this->sync_manager->bunchChunks($tempBunch);

        $first = true;

        foreach ($chunks as $chunk) {
            $lastBlock = Block::getLastBlock();
            $transactions = Chunk::getChunk("{$tempBunch}/{$chunk}");
            unlink("{$tempBunch}/{$chunk}");

            $fileBlockhash = mb_substr($chunk, 0, 64);
            $fileStandardTimestamp = mb_substr($chunk, 64, mb_strpos($chunk, '.') - 64);

            // find first
            if ($first === true && $nextBlockhash !== $fileBlockhash) {
                continue;
            }

            $first = false;

            // commit-manager init
            $syncResult = $this->syncCommit($transactions, $lastBlock, $fileBlockhash, $fileStandardTimestamp);

            if ($syncResult === Event::DIFFERENT) {
                return $syncResult;
            }
        }

        return Event::SUCCESS;
    }

    private function syncCommit(array $transactions, array $lastBlock, string $expectBlockhash, int $expectStandardTimestamp): string
    {
        $lastStandardTimestamp = $lastBlock['s_timestamp'];
        $lastBlockhash = $lastBlock['blockhash'];

        // commit-manager init
        // merge & sort
        $completedTransactions = $this->commit_manager->orderedTransactions($transactions, $lastStandardTimestamp, $expectStandardTimestamp);
        $completedTransactions = $this->commit_manager->completeTransactions($completedTransactions);

        // make expect block info
        $txCount = count($completedTransactions);
        $myBlockhash = Merkle::MakeBlockHash($lastBlockhash, Merkle::MakeMerkleHash($completedTransactions), $expectStandardTimestamp);
        $expectBlock = $this->commit_manager->nextBlock($lastBlock, $expectBlockhash, $txCount, $expectStandardTimestamp);

        if ($expectBlockhash === $myBlockhash) {
            $this->commit_manager->commit($completedTransactions, $lastBlock, $expectBlock);
            $this->commit_manager->makeTransactionChunk($expectBlock, $transactions);

            // tracker
            $this->tracker_manager->GenerateTracker();

            // ok
            return Event::SUCCESS;
        }

        // banish
        $this->log->debug('Sync commit', ['type' => 'sync', 'myBlockhash' => $myBlockhash, 'expectBlockhash' => $expectBlockhash, 'result' => false]);

        return Event::DIFFERENT;
    }

    private function ban(): void
    {
        if ($this->isTimeToSeparation()) {
            // ban;
            $subjectNode = Property::subjectNode();
            Tracker::setBanHost($subjectNode['host']);
            $this->resetFailCount();

            return;
        }

        $this->increaseFailCount();
    }
}
