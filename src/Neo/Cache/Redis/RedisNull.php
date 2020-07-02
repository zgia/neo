<?php

namespace Neo\Cache\Redis;

use Neo\Cache\CacheInterface;

/**
 * Class RedisNull
 *
 * NEO_REDIS 为false时，调用此类
 */
class RedisNull implements CacheInterface
{
    /**
     * Redis 挂了吗?
     * @return bool
     */
    public function isDown()
    {
        return true;
    }

    /**
     * @return $this
     */
    public function getPhpRedis()
    {
        return $this;
    }

    /**
     * 获取
     *
     * @param string $key
     *
     * @return mixed
     */
    public function get($key)
    {
        return null;
    }

    /**
     * 写入
     *
     * @param string     $key
     * @param int|string $value
     * @param int        $expired 有效期，秒
     *
     * @return bool
     */
    public function set($key, $value, $expired = 0)
    {
        return false;
    }

    /**
     * @param $name
     * @param $arguments
     *
     * @return mixed
     */
    public function __call($name, $arguments)
    {
        return null;
    }
}
