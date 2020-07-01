<?php

namespace Neo\Cache;

use Neo\Cache\File\File as FileCache;

/**
 * Class Cache
 */
class Cache
{
    /**
     * 缓存类别
     */
    // 文件缓存
    const CACHE_TYPE_FILE = 1;

    // Redis - 主
    const CACHE_TYPE_REDIS_MASTER = 2;

    // Redis - 从
    const CACHE_TYPE_REDIS_SLAVE = 3;

    /**
     * 使用文件缓存还是Redis缓存
     *
     * @param int $type 缓存类别
     *
     * @return CacheInterface
     */
    public static function getCache($type = self::CACHE_TYPE_REDIS_MASTER)
    {
        $redis = defined('NEO_REDIS') && NEO_REDIS && $type != self::CACHE_TYPE_FILE ? true : false;

        if ($redis) {
            $instance = self::getRedisCache($type);
        } else {
            $instance = self::getFileCache();
        }

        return $instance;
    }

    /**
     * 文件缓存
     *
     * @return CacheInterface
     */
    public static function getFileCache()
    {
        return FileCache::getInstance();
    }

    /**
     * Redis缓存
     *
     * @param int $type 默认使用主服务器
     *
     * @return CacheInterface
     */
    public static function getRedisCache($type = self::CACHE_TYPE_REDIS_MASTER)
    {
        switch ($type) {
            case self::CACHE_TYPE_REDIS_SLAVE:

                $instance = neo()->redisslave;
                break;
            case self::CACHE_TYPE_REDIS_MASTER:
            default:

                $instance = neo()->redis;
                break;
        }

        return $instance;
    }
}
