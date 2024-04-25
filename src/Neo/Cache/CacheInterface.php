<?php

namespace Neo\Cache;

/**
 * Interface CacheInterface
 */
interface CacheInterface
{
    /**
     * 缓存服务器 挂了吗?
     *
     * @return bool
     */
    public function isDown();

    /**
     * 写
     *
     * @param string $key
     * @param mixed  $val
     * @param mixed  $expired 有效期，秒
     * 
     * @return bool
     */
    public function set(string $key, $val, $expired = 0);

    /**
     * 读
     *
     * @param string $key
     *
     * @return mixed
     */
    public function get(string $key);
}
