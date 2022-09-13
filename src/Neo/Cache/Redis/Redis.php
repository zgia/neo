<?php

namespace Neo\Cache\Redis;

use Neo\Cache\CacheInterface;

/**
 * Class Redis
 *
 * User: gewei
 * Date: 15/11/5
 * Time: 下午4:04
 */
class Redis implements CacheInterface
{
    private static $_instance = [];

    /**
     * @var null|\Redis
     */
    private $phpRedis;

    /**
     * 标记Redis是否挂了
     *
     * @var bool
     */
    private $down = false;

    /**
     * Redis 挂了吗?
     *
     * @return bool
     */
    public function isDown()
    {
        return $this->down;
    }

    /**
     * @return null|\Redis
     */
    public function getPhpRedis()
    {
        return $this->phpRedis;
    }

    /**
     * 获取
     *
     * @param string $key 键名称
     *
     * @return null|array
     */
    public function get(string $key)
    {
        $value = $this->phpRedis->get($key);

        return $value ? jsonDecode($value) : null;
    }

    /**
     * 写入
     *
     * @see https://github.com/phpredis/phpredis#set
     *
     * @param string    $key     键名称
     * @param mixed     $value   值
     * @param array|int $expired 有效期，秒；或者NX|XX数组，['nx', 'ex'=>60]
     *
     * @return bool
     */
    public function set(string $key, $value, $expired = 0)
    {
        if (is_array($expired) && $expired) {
            // nothing
        } else {
            $expired = max(0, (int) $expired);
        }

        $expired || $expired = [];

        return $this->phpRedis->set($key, json_encode($value), $expired);
    }

    /**
     * 批零设置多个值
     *
     * @param array $data 值
     */
    public function mset(array $data)
    {
        foreach (array_chunk($data, 100, true) as $chunk) {
            $chunk = array_map('json_encode', $chunk);

            $this->phpRedis->mset($chunk);
        }
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
        if ($batch) {
            if (! is_array($value)) {
                $value = [$value];
            }
        } else {
            $value = [$value];
        }
        $value = array_map('json_encode', $value);

        return $this->phpRedis->lPush($key, ...$value);
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
        if ($timeout === 0) {
            $value = $this->phpRedis->rPop($key);
        } else {
            $value = null;

            $tmp = $this->phpRedis->brPop($key, $timeout);
            if (is_array($tmp) && $tmp[0] === $key) {
                $value = $tmp[1] ?? null;
            }
        }

        return $value ? jsonDecode($value) : null;
    }

    /**
     * 闭包方式实现互斥锁
     *
     * @param callable $callable 闭包方法
     * @param string   $key      锁名称
     * @param int      $timeout  锁超时时间，超过时间锁自动失效，单位毫秒
     *
     * @throws RedisException 失败时抛出异常信息
     * @return mixed          成功时返回闭包函数的返回值
     */
    public function lock(callable $callable, string $key, int $timeout = 3000)
    {
        try {
            $locked = $this->phpRedis->psetex($key, $timeout, 1);

            if ($locked === false) {
                throw new RedisException(__('Failed to grab redis lock'));
            }

            return call_user_func($callable);
        } catch (\Exception $ex) {
            throw new RedisException($ex->getMessage(), $ex->getCode(), $ex);
        } finally {
            $this->phpRedis->del($key);
        }
    }

    /**
     * 添加redis 服务
     *
     * @param array $configs = array('servername' => ['host' => '127.0.0.1', 'port' => '6079'])
     *
     * @throws RedisException
     */
    public static function addServer(array $configs)
    {
        if(empty($configs)){
            throw new RedisException('Invalid Redis config.');
        }
        foreach ($configs as $serverName => $config) {
            if (! isset(self::$_instance[$serverName])) {
                try {
                    self::$_instance[$serverName] = new self($config);
                } catch (\RedisClusterException | \RedisException | \Exception $ex) {
                    throw new RedisException($ex->getMessage(), $ex->getCode(), $ex);
                }
            }
        }
    }

    /**
     * 得到 Redis 实例
     *
     * @param string $serverName
     *
     * @return null|Redis
     */
    public static function getInstance(string $serverName = 'master')
    {
        $serverName || $serverName = 'master';

        return self::$_instance[$serverName] ?? null;
    }

    /**
     * 得到 Redis 实例
     *
     * @param array $config
     *
     * @return null|\Redis
     */
    public static function loadRedis(array $config)
    {
        if (empty($config) || empty($config['host'])) {
            return null;
        }

        $config['port'] = (int) $config['port'] ?: 6379;
        $config['timeout'] = (float) $config['timeout'];
        $config['dbindex'] = (int) $config['dbindex'];

        $redis = new \Redis();
        if ($redis->connect($config['host'], $config['port'], $config['timeout'])) {
            if ($config['password']) {
                $redis->auth($config['password']);
            }

            if ($config['dbindex']) {
                $redis->select($config['dbindex']);
            }

            return $redis;
        }

        return null;
    }

    /**
     * @param array $config
     *
     * @throws \RedisClusterException
     * @return null|\RedisCluster
     */
    public static function loadCluster(array $config)
    {
        if (empty($config) || empty($config['host'])) {
            return null;
        }

        $config['timeout'] = (float) $config['timeout'];

        $redis = new \RedisCluster(
            null,
            $config['host'],
            $config['timeout'],
            $config['timeout'],
            false,
            $config['password']
        );

        return $redis ?: null;
    }

    /**
     * Redis constructor
     *
     * @param array $config
     *
     * @throws \RedisClusterException|\RedisException
     */
    private function __construct(array $config)
    {
        if (empty($config['cluster'])) {
            $this->phpRedis = self::loadRedis($config);
        } else {
            $this->phpRedis = self::loadCluster($config);
        }

        if (is_null($this->phpRedis)) {
            $this->down = true;
        } else {
            if (is_array($config['options'])) {
                foreach ($config['options'] as $opt => $val) {
                    $this->phpRedis->setOption($opt, $val);
                }
            }
        }
    }

    /**
     * 关闭从Redis连接
     */
    public function __destruct()
    {
        $this->phpRedis->close();
    }

    /**
     * 禁止Clone
     */
    private function __clone()
    {
    }

    /**
     * 调用redis 方法
     *
     * @param string $method
     * @param array  $args
     *
     * @throws RedisException
     * @return mixed
     */
    public function __call($method, $args)
    {
        if (! $this->phpRedis || ! $method) {
            return false;
        }
        if (! method_exists($this->phpRedis, $method)) {
            throw new RedisException("Class Redis not have method ({$method}) ");
        }

        return call_user_func_array([$this->phpRedis, $method], $args);
    }
}
