<?php

namespace Saseul\Core;

class Env
{
    public static $memcached = [
        'host' => 'memcached',
        'port' => 11211,
        'prefix' => '',
    ];

    public static $mongoDb = [
        'host' => 'mongo',
        'port' => 27017
    ];

    public static $nodeInfo = [
        'host' => '',
        'address' => '',
        'public_key' => '',
        'private_key' => ''
    ];

    public static $genesis = [
        'host' => '',
        'address' => '',
        'coin_amount' => '',
        'deposit_amount' => '',
        'key' => []
    ];

    public static $log = [
        'path' => '',
        'level' => ''
    ];

    /**
     * Genesis key 를 path로 부터 읽어온다.
     *
     * @param string $path Genesis key 파일이 있는 위치
     *
     * @return array
     */
    public static function loadGenesisKey(string $path): array
    {
        $get_key = file_get_contents(SASEUL_DIR . '/' . $path);

        return json_decode($get_key, true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * ENV 값들을 불러온다.
     */
    public static function load(): void
    {
        // Node info
        self::$nodeInfo['host'] = self::getFromEnv('NODE_HOST');
        self::$nodeInfo['address'] = self::getFromEnv('NODE_ADDRESS');
        self::$nodeInfo['public_key'] = self::getFromEnv('NODE_PUBLIC_KEY');
        self::$nodeInfo['private_key'] = self::getFromEnv('NODE_PRIVATE_KEY');

        // Genesis
        self::$genesis['host'] = self::getFromEnv('GENESIS_HOST');
        self::$genesis['address'] = self::getFromEnv('GENESIS_ADDRESS');
        self::$genesis['coin_amount'] = self::getFromEnv('GENESIS_COIN_VALUE');
        self::$genesis['deposit_amount'] = self::getFromEnv('GENESIS_DEPOSIT_VALUE');

        $genesis_key_path = self::getFromEnv('GENESIS_KEY_PATH');
        self::$genesis['key'] = self::loadGenesisKey($genesis_key_path);

        // Log
        self::$log['path'] = self::getFromEnv('LOG_PATH');
        self::$log['level'] = self::getFromEnv('LOG_LEVEL');
    }

    /**
     * Check environment param.
     *
     * @param string $key
     *
     * @return false|string If $key is missing, return false.
     */
    private static function getFromEnv(string $key)
    {
        $value = getenv($key);

        if (empty($value)) {
            // Todo: Add logging message
            echo "Environment variables failed assertions: {$key} is missing.\n";

            return false;
        }

        return $value;
    }
}
