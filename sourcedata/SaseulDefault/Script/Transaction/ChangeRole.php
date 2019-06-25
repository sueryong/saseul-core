<?php

namespace Saseul\Script\Transaction;

use Saseul\Common\Script;
use Saseul\Version;
use Saseul\Core\NodeInfo;
use Saseul\Core\Tracker;
use Saseul\System\Key;
use Saseul\Util\DateTime;
use Saseul\Util\Logger;
use Saseul\Util\RestCall;

class ChangeRole extends Script
{
    private $rest;

    private $m_result;

    public function __construct()
    {
        $this->rest = RestCall::GetInstance();
    }

    public function _process()
    {
        Logger::EchoLog('Type role to change to. (validator | supervisor | light) ');
        $role = trim(fgets(STDIN));

        $validator = Tracker::GetRandomValidator();
        $host = $validator['host'];

        $transaction = [
            'type' => 'ChangeRole',
            'version' => Version::CURRENT,
            'from' => NodeInfo::getAddress(),
            'role' => $role,
            'transactional_data' => '',
            'timestamp' => DateTime::Microtime(),
        ];

        $thash = hash('sha256', json_encode($transaction));
        $public_key = NodeInfo::getPublicKey();
        $signature = Key::makeSignature(
            $thash,
            NodeInfo::getPrivateKey(),
            NodeInfo::getPublicKey()
        );

        $url = "http://{$host}/transaction";
        $ssl = false;
        $data = [
            'transaction' => json_encode($transaction),
            'public_key' => $public_key,
            'signature' => $signature,
        ];
        $header = [];

        $result = $this->rest->POST($url, $data, $ssl, $header);
        $this->m_result = json_decode($result, true);
    }

    public function _end()
    {
        $this->data['result'] = $this->m_result;
    }
}
