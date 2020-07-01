<?php

namespace Neo\Cache\Redis;

use Neo\Cache\CacheInterface;
use Neo\Exception\NeoException;
use Neo\Exception\RedisException;

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
     * Redis是否挂了
     *
     * @var bool
     */
    private $wentaway = false;

    /**
     * Redis 挂了吗?
     * @return bool
     */
    public function isWentAway()
    {
        return $this->wentaway;
    }

    /**
     * @return null|\Redis
     */
    public function getPhpRedis()
    {
        return $this->phpRedis;
    }

    /**
     * @param $val
     *
     * @return mixed
     */
    public function json_decode($val)
    {
        return \json_decode($val, true, 512, JSON_BIGINT_AS_STRING);
    }

    /**
     * 获取
     *
     * @param string $key
     *
     * @return null|array
     */
    public function get($key)
    {
        $value = $this->phpRedis->get($key);

        $value && $value = $this->json_decode($value);

        return $value ?: null;
    }

    /**
     * 写入
     *
     * @see https://github.com/phpredis/phpredis#set
     *
     * @param string    $key
     * @param mixed     $value
     * @param array|int $expired 有效期，秒；或者NX|XX数组，['nx', 'ex'=>60]
     *
     * @return bool
     */
    public function set($key, $value, $expired = 0)
    {
        $value = json_encode($value);

        if (is_array($expired) && $expired) {
            // nothing
        } else {
            $expired = max(0, (int) $expired);
        }

        $expired || $expired = [];

        return $this->phpRedis->set($key, $value, $expired);
    }

    /**
     * 批量写
     *
     * @param array $data
     */
    public function mset(array $data)
    {
        foreach (array_chunk($data, 100, true) as $chunk) {
            $chunk = array_map('json_encode', $chunk);

            $this->phpRedis->mset($chunk);
        }
    }

    /**
     * 阻塞式弹出
     *
     * @param string $key
     * @param int    $timeout
     *
     * @return null|array|int|string
     */
    public function bPop($key, $timeout)
    {
        $value = $this->phpRedis->brPop($key, $timeout);

        if (! is_null($value)) {
            return $this->json_decode($value[1]);
        }

        return null;
    }

    /**
     * Pop
     *
     * @param string $key
     *
     * @return null|array|int|string
     */
    public function pop($key)
    {
        $value = $this->phpRedis->rPop($key);

        if (! is_null($value)) {
            return $this->json_decode($value);
        }

        return null;
    }

    /**
     * Push
     *
     * @param string $key
     * @param mixed  $value
     * @param bool   $batch true表示批量push多个值
     */
    public function push(string $key, $value, $batch = false)
    {
        if ($batch) {
            if (! is_array($value)) {
                $value = [$value];
            }
        } else {
            $value = [$value];
        }

        $value = array_map('json_encode', $value);

        $this->phpRedis->lPush($key, ...$value);
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
    public static function initRedis(array $config)
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
    public static function initRedisCluster(array $config)
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
            $this->phpRedis = self::initRedis($config);
        } else {
            $this->phpRedis = self::initRedisCluster($config);
        }

        if (is_null($this->phpRedis)) {
            $this->wentaway = true;
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
     * @throws NeoException
     * @return mixed
     */
    public function __call($method, $args)
    {
        if (! $this->phpRedis || ! $method) {
            return false;
        }
        if (! method_exists($this->phpRedis, $method)) {
            throw new NeoException("Class Redis not have method ({$method}) ");
        }

        return call_user_func_array([$this->phpRedis, $method], $args);
    }
}
