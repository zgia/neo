<?php

namespace Neo\Cache\Memcached;

use Neo\Cache\CacheInterface;
use Neo\Exception\LogicException;

/**
 * 关于过期时间
 *
 * 实际发送的值可以 是一个Unix时间戳（自1970年1月1日起至失效时间的整型秒数），
 * 或者是一个从现在算起的以秒为单位的数字。对于后一种情况，这个 秒数不能超过60×60×24×30（30天时间的秒数）;
 * 如果失效的值大于这个值， 服务端会将其作为一个真实的Unix时间戳来处理而不是 自当前时间的偏移。
 *
 * 推荐使用以秒为单位的数字，除非时间大于30天
 *
 * Class Memcached
 */
class MemcachedStore implements CacheInterface
{
    /**
     * The Memcached instance.
     *
     * @var \Memcached
     */
    private $_memcached;

    /**
     * A string that should be prepended to keys.
     *
     * @var string
     */
    protected $prefix = '';

    /**
     * Memcached instance
     *
     * @var array<string,MemcachedStore>
     */
    private static $_instance = [];

    /**
     * MemcachedStore constructor.
     *
     * @param array<array<string,mixed>> $servers
     * @param null|string                $connectionId
     * @param array<string,mixed>        $options
     * @param array<string>              $credentials
     */
    private function __construct(array $servers, $connectionId = null, array $options = [], array $credentials = [])
    {
        $this->_memcached = $this->connect($servers, $connectionId, $options, $credentials);
    }

    /**
     * 关闭Memcached连接
     */
    public function __destruct()
    {
        $this->_memcached->quit();
    }

    /**
     * 添加 Memcached 服务
     *
     * @param array<string,mixed> $configs
     */
    public static function addServer(array $configs): void
    {
        foreach ($configs as $serverName => $config) {
            if (! isset(self::$_instance[$serverName])) {
                if ($config['servers']) {
                    $instance = new self(
                        $config['servers'],
                        $config['persistent_id'],
                        $config['options'],
                        $config['sasl']
                    );

                    $instance->setPrefix($config['prefix']);

                    self::$_instance[$serverName] = $instance;
                }
            }
        }
    }

    /**
     * 得到Memcached 实例
     *
     * @param string $serverName
     *
     * @return null|self
     */
    public static function getInstance($serverName)
    {
        if (isset(self::$_instance[$serverName])) {
            return self::$_instance[$serverName];
        }

        return null;
    }

    /**
     * Memcached挂掉否？
     *
     * @return bool
     */
    public function isDown()
    {
        try {
            $this->validateConnection($this->getMemcached());
        } catch (LogicException|\Throwable $ex) {
            return true;
        }

        return false;
    }

    /**
     * Create a new Memcached connection.
     *
     * @param array<array<string,mixed>> $servers
     * @param null|string                $connectionId
     * @param array<string,mixed>        $options
     * @param array<string>              $credentials
     *
     * @return \Memcached
     *
     * @throws \RuntimeException
     */
    public function connect(array $servers, $connectionId = null, array $options = [], array $credentials = [])
    {
        $memcached = $this->getMemcachedInstance(
            $connectionId,
            $credentials,
            $options
        );

        if (! $memcached->getServerList()) {
            // For each server in the array, we'll just extract the configuration and add
            // the server to the Memcached connection. Once we have added all of these
            // servers we'll verify the connection is successful and return it back.
            foreach ($servers as $server) {
                $memcached->addServer(
                    $server['host'],
                    $server['port'],
                    $server['weight']
                );
            }
        }

        return $this->validateConnection($memcached);
    }

    /**
     * Get a new Memcached instance.
     *
     * @param null|string         $connectionId
     * @param array<string>       $credentials
     * @param array<string,mixed> $options
     *
     * @return \Memcached
     */
    protected function getMemcachedInstance($connectionId, array $credentials, array $options)
    {
        $memcached = empty($connectionId) ? new \Memcached() : new \Memcached($connectionId);

        if (count($credentials) == 2) {
            [$username, $password] = $credentials;

            $memcached->setOption(\Memcached::OPT_BINARY_PROTOCOL, true);

            $memcached->setSaslAuthData($username, $password);
        }

        // 关闭压缩
        $options[\Memcached::OPT_COMPRESSION] = false;
        // php memcached有个bug，当get的值不存在，有固定40ms延迟，开启这个参数，可以避免这个bug
        $options[\Memcached::OPT_TCP_NODELAY] = true;

        $memcached->setOptions($options);

        return $memcached;
    }

    /**
     * Validate the given Memcached connection.
     *
     * @param \Memcached $memcached
     *
     * @return \Memcached
     */
    protected function validateConnection($memcached)
    {
        $status = $memcached->getVersion();

        if (! is_array($status)) {
            throw new LogicException('No Memcached servers added.', 233333);
        }

        if (in_array('255.255.255', $status) && count(array_unique($status)) === 1) {
            throw new LogicException('Could not establish Memcached connection.', 233333);
        }

        return $memcached;
    }

    /**
     * Retrieve an item from the cache by key.
     *
     * @param string $key
     *
     * @return mixed
     */
    public function get(string $key)
    {
        $value = $this->getMemcached()
            ->get($this->prefix . $key);

        if ($this->getMemcached()
            ->getResultCode() == 0) {
            return $value;
        }

        return null;
    }

    /**
     * Retrieve multiple items from the cache by key.
     *
     * Items not found in the cache will have a null value.
     *
     * @param array<string> $keys
     *
     * @return array<string,mixed>
     */
    public function many(array $keys)
    {
        $prefixedKeys = array_map(
            function ($key) {
                return $this->prefix . $key;
            },
            $keys
        );

        $values = $this->getMemcached()
            ->getMulti($prefixedKeys, \Memcached::GET_PRESERVE_ORDER);

        if ($this->getMemcached()
            ->getResultCode() != 0) {
            return array_fill_keys($keys, null);
        }

        return array_combine($keys, $values);
    }

    /**
     * Store an item in the cache for a given number of seconds.
     * If key is exist, it will be delete first.
     *
     * @param string    $key
     * @param mixed     $val
     * @param float|int $expired
     *
     * @return bool
     */
    public function set(string $key, $val, $expired = 0)
    {
        $this->delete($key);
        $this->add($key, $val, $expired);

        return true;
    }

    /**
     * Store an item in the cache for a given number of seconds.
     *
     * @param string    $key
     * @param mixed     $value
     * @param float|int $seconds
     */
    public function put(string $key, $value, float|int $seconds = 0): void
    {
        $this->getMemcached()
            ->set($this->prefix . $key, $value, $seconds);
    }

    /**
     * Store multiple items in the cache for a given number of seconds.
     *
     * @param array<string,mixed> $values
     * @param float|int           $seconds
     */
    public function putMany(array $values, float|int $seconds = 0): void
    {
        $prefixedValues = [];

        foreach ($values as $key => $value) {
            $prefixedValues[$this->prefix . $key] = $value;
        }

        $this->getMemcached()
            ->setMulti($prefixedValues, $seconds);
    }

    /**
     * Store an item in the cache if the key doesn't exist.
     *
     * @param string    $key
     * @param mixed     $value
     * @param float|int $seconds
     *
     * @return bool
     */
    public function add(string $key, $value, $seconds = 0)
    {
        return $this->getMemcached()
            ->add($this->prefix . $key, $value, $seconds);
    }

    /**
     * Increment the value of an item in the cache.
     *
     * @param string $key
     * @param mixed  $value
     *
     * @return bool|int
     */
    public function increment(string $key, $value = 1)
    {
        return $this->getMemcached()
            ->increment($this->prefix . $key, $value);
    }

    /**
     * Decrement the value of an item in the cache.
     *
     * @param string $key
     * @param mixed  $value
     *
     * @return bool|int
     */
    public function decrement(string $key, $value = 1)
    {
        return $this->getMemcached()
            ->decrement($this->prefix . $key, $value);
    }

    /**
     * Store an item in the cache indefinitely.
     *
     * @param string $key
     * @param mixed  $value
     */
    public function forever(string $key, $value): void
    {
        $this->put($key, $value, 0);
    }

    /**
     * Remove an item from the cache.
     *
     * @param string $key
     *
     * @return bool
     */
    public function delete(string $key)
    {
        return $this->getMemcached()
            ->delete($this->prefix . $key);
    }

    /**
     * Remove all items from the cache.
     */
    public function flush(): void
    {
        $this->getMemcached()
            ->flush();
    }

    /**
     * Get the underlying Memcached connection.
     *
     * @return \Memcached
     */
    public function getMemcached()
    {
        return $this->_memcached;
    }

    /**
     * Get the cache key prefix.
     *
     * @return string
     */
    public function getPrefix()
    {
        return $this->prefix;
    }

    /**
     * Set the cache key prefix.
     *
     * @param string $prefix
     */
    public function setPrefix(string $prefix): void
    {
        $this->prefix = ! empty($prefix) ? $prefix . ':' : '';
    }
}
