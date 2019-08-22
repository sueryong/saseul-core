<?php

// TODO: class명 정해야함. 일단 임시로 사용.

namespace Saseul\Constant;

class MongoDbConfig
{
    public const ASC = 1;
    public const DESC = -1;

    public const DB_TRACKER = 'saseul_tracker';
    public const DB_COMMITTED = 'saseul_committed';

    public const COLLECTION_ATTRIBUTE = 'attributes';
    public const COLLECTION_BLOCK = 'blocks';
    public const COLLECTION_COIN = 'coin';
    public const COLLECTION_CONTRACT = 'contract';
    public const COLLECTION_TRANSACTION = 'transactions';
    public const COLLECTION_TOKEN = 'token';
    public const COLLECTION_TOKEN_LIST = 'token_list';
    public const COLLECTION_GENERATION = 'generations';
    public const COLLECTION_TRACKER = 'tracker';

    public const NAMESPACE_ATTRIBUTE = self::DB_TRACKER . '.' . self::COLLECTION_ATTRIBUTE;
    public const NAMESPACE_BLOCK = self::DB_COMMITTED . '.' . self::COLLECTION_BLOCK;
    public const NAMESPACE_COIN = self::DB_COMMITTED . '.' . self::COLLECTION_COIN;
    public const NAMESPACE_CONTRACT = self::DB_COMMITTED . '.' . self::COLLECTION_CONTRACT;
    public const NAMESPACE_TRANSACTION = self::DB_COMMITTED . '.' . self::COLLECTION_TRANSACTION;
    public const NAMESPACE_TOKEN = self::DB_TRACKER . '.' . self::COLLECTION_TOKEN;
    public const NAMESPACE_TOKEN_LIST = self::DB_COMMITTED . '.' . self::COLLECTION_TOKEN_LIST;
    public const NAMESPACE_GENERATION = self::DB_COMMITTED . '.' . self::COLLECTION_GENERATION;
    public const NAMESPACE_TRACKER = self::DB_TRACKER . '.' . self::COLLECTION_TRACKER;
}
