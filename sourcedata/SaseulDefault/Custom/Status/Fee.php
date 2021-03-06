<?php

namespace Saseul\Custom\Status;

use Exception;
use Saseul\Common\Status;
use Saseul\System\Database;

/**
 * Class Fee.
 *
 * 사용하고 있지 않다.
 */
class Fee implements Status
{
    protected static $validators = [];
    protected static $fee = 0;
    protected static $blockhash = '';
    protected static $s_timestamp = 0;

    public static function GetFee()
    {
        return self::$fee;
    }

    public static function SetFee(int $value)
    {
        self::$fee = $value;
    }

    public static function SetValidators(array $validators)
    {
        self::$validators = $validators;
    }

    public static function SetValidator(string $address)
    {
        self::$validators[] = $address;
    }

    /**
     * Status 값을 초기화한다.
     */
    public static function _reset(): void
    {
        self::$validators = [];
        self::$fee = 0;
        self::$blockhash = '';
        self::$s_timestamp = 0;
    }

    public static function SetBlockhash(string $blockhash)
    {
        self::$blockhash = $blockhash;
    }

    public static function SetStandardTimestamp(int $s_timestamp)
    {
        self::$s_timestamp = $s_timestamp;
    }

    /**
     * 저장되어있는 Status 값을 읽어온다.
     */
    public static function _load(): void
    {
    }

    /**
     * Status 값을 전처리한다.
     */
    public static function _preprocess(): void
    {
//        if ((int) self::$fee === 0) {
//            self::_Reset();
//            return;
//        }
//
//        $validators = \Saseul\Custom\Method\Attributes::GetValidator();
//        $all = \Saseul\Custom\Method\Coin::GetAll($validators);
//
//        $sum = 0;
//        $remain = self::$fee;
//        $validators = [];
//        $fees = [];
//
//        # Calculate sum
//        foreach ($all as $address => $item) {
//            $sum = $sum + (int) $item['deposit'];
//        }
//
//        # Calculate remain
//        foreach ($all as $address => $item) {
//            $fee = (int)(self::$fee * ((int) $item['deposit'] / $sum));
//            $fees[] = $fee;
//            $validators[] = [
//                'address' => $address,
//                'deposit' => $item['deposit'],
//                'fee' => $fee,
//                'status' => 'deposited',
//            ];
//
//            $remain = $remain - $fee;
//        }
//
//        # Calculate remain fee
//        array_multisort($fees, $validators);
//
//        foreach ($validators as $key => $validator) {
//            if ($remain <= 0) {
//                break;
//            }
//
//            $validators[$key]['fee'] = $validators[$key]['fee'] + 1;
//            $remain = $remain - 1;
//        }
//
//        $contract = [
//            'type' => 'CoinFee',
//            'validators' => $validators,
//            'fee' => self::$fee,
//            'blockhash' => self::$blockhash,
//            's_timestamp' => self::$s_timestamp,
//            'status' => 'active',
//        ];
//
//        $cid = Contract::MakeCID($contract, self::$s_timestamp);
//
//        Contract::SetContract($cid, $contract);
//
//        self::_Reset();
    }

    /**
     * Status 값을 저장한다.
     */
    public static function _save(): void
    {
    }

    /**
     * Status 값을 후처리한다.
     *
     * @throws \MongoDB\Driver\Exception\Exception
     */
    public static function _postprocess(): void
    {
        if ((int) self::$fee === 0) {
            self::_reset();

            return;
        }

        $validators = \Saseul\Custom\Method\Attributes::getValidator();
        $all = \Saseul\Custom\Method\Coin::getAll($validators);

        $sum = 0;
        $remain = self::$fee;
        $validators = [];
        $fees = [];
        $addresses = [];

        // Calculate sum
        foreach ($all as $item) {
            $sum = $sum + (int) $item['deposit'];
        }

        // Calculate remain
        foreach ($all as $address => $item) {
            $fee = (int) (self::$fee * ((int) $item['deposit'] / $sum));
            $fees[] = $fee;
            $addresses[] = $address;

            $validators[] = [
                'address' => $address,
                'balance' => $item['balance'] + $fee,
            ];

            $remain = $remain - $fee;
        }

        // Calculate remain fee
        array_multisort($fees, $addresses, $validators);

        foreach ($validators as $key => $_) {
            if ($remain <= 0) {
                break;
            }

            $validators[$key]['balance'] = $validators[$key]['balance'] + 1;
            $remain = $remain - 1;
        }

        static::setBalance($validators);

        self::_reset();
    }

    /**
     * 테스트 하기 힘들어 우선 빼냈다. 나중에 확인하여 같이 넣어 코드를 수정한다.
     *
     * @param array $nodeList
     *
     * @throws Exception
     *
     * @todo private 로 변경되어야 한다.
     */
    public static function setBalance(array $nodeList): void
    {
        $db = Database::getInstance();

        $operations = [];
        foreach ($nodeList as $node) {
            $operations[] = [
                'updateOne' => [
                    ['address' => $node['address']],
                    ['$set' => ['balance' => $node['balance']]],
                    ['upsert' => true],
                ]
            ];
        }

        if (empty($operations)) {
            return;
        }

        $db->getCoinCollection()->bulkWrite($operations);
    }
}
