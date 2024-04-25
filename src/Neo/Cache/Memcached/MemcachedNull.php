<?php

namespace Neo\Cache\Memcached;

use Neo\Cache\CacheInterface;

/**
 * NEO_MEMCACHED 为false时，调用此类
 *
 * Class MemcachedNull
 */
class MemcachedNull implements CacheInterface
{
    /**
     * Memcached 挂了吗?
     *
     * @return bool
     */
    public function isDown()
    {
        return true;
    }

    /**
     * @return $this
     */
    public function getMemcached()
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
    public function get(string $key)
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
    public function set(string $key, $value, $expired = 0)
    {
        return false;
    }

    /**
     * @param string $name
     * @param string $arguments
     *
     * @return mixed
     */
    public function __call($name, $arguments)
    {
        return null;
    }
}
