<?php

namespace Neo\Cache;

/**
 * Interface CacheInterface
 */
interface CacheInterface
{
    /**
     * 缓存服务器 挂了吗?
     * @return bool
     */
    public function isWentAway();

    /**
     * 写
     *
     * @param string $key
     * @param mixed  $val
     */
    public function set($key, $val);

    /**
     * 读
     *
     * @param string $key
     *
     * @return array
     */
    public function get($key);
}
