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
     * @param string                        $key
     * @param int|string                    $value
     * @param array<string,mixed>|float|int $expired 有效期，秒；或者NX|XX数组，['nx', 'ex'=>60]
     *
     * @return bool
     */
    public function set($key, $value, $expired = 0)
    {
        return false;
    }

    /**
     * 添加值到列表的左侧
     *
     * @param string $key   列表名称
     * @param mixed  $value 值
     * @param bool   $batch true表示批量push多个值
     *
     * @return bool|int 成功返回列表长度，失败返回FALSE
     */
    public function push(string $key, $value, bool $batch = false)
    {
        return false;
    }

    /**
     * 弹出列表的（右）值
     *
     * @param string $key     列表名称
     * @param int    $timeout 超时时间，如果设置，则使用阻塞弹出
     *
     * @return null|int|mixed|string
     */
    public function pop(string $key, int $timeout = 0)
    {
        return null;
    }

    /**
     * 闭包方式实现互斥锁
     *
     * @param callable $callable 闭包方法
     * @param string   $key      锁名称
     * @param int      $timeout  锁超时时间，超过时间锁自动失效，单位毫秒
     *
     * @return mixed 成功时返回闭包函数的返回值
     *
     * @throws RedisException 失败时抛出异常信息
     */
    public function lock(callable $callable, string $key, int $timeout = 3000)
    {
        try {
            return call_user_func($callable);
        } catch (\Exception $ex) {
            throw new RedisException($ex->getMessage(), $ex->getCode(), $ex);
        }
    }

    /**
     * @param string $name
     * @param mixed  $arguments
     *
     * @return mixed
     */
    public function __call($name, $arguments)
    {
        return null;
    }
}
