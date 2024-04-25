<?php

namespace Neo;

/**
 * 配置处理
 */
class Config
{
    /**
     * @var array<string,mixed>
     */
    private static array $config = [];

    /**
     * 加载配置文件
     *
     * @param array<string,mixed> $config
     */
    public static function load(array $config): void
    {
        self::$config = $config;
    }

    /**
     * 返回全部的配置项
     *
     * @return array<string,mixed>
     */
    public static function all()
    {
        return self::$config;
    }

    /**
     * 获取某个配置项，如果指定$key，则返回子项。
     *
     * 支持配置项：
     *      [
     *          type => VALUE,
     *          type => [key => VALUE, key => VALUE]
     *      ]
     *
     * @param string $type
     * @param string $key
     * @param mixed  $default
     *
     * @return mixed
     */
    public static function get(string $type, string $key = null, $default = null)
    {
        if (! isset(self::$config[$type])) {
            return $default;
        }

        $cfg = self::$config[$type];

        if ($key) {
            return $cfg[$key] ?? $default;
        }

        return $cfg;
    }

    /**
     * 设置某个配置项
     *
     * @param string $key
     * @param mixed  $value
     */
    public static function setValue(string $key, $value): void
    {
        self::$config[$key] = $value;
    }

    /**
     * 设置某个配置项
     *
     * @param string $type
     * @param string $key
     * @param mixed  $value
     */
    public static function set(string $type, string $key, $value): void
    {
        self::$config[$type][$key] = $value;
    }

    /**
     * 解析数据库配置，以便支持Doctrine
     *
     * @param array<string,mixed> $config
     *
     * @return array<string,mixed>
     */
    public static function parseDatabaseConfig(array $config)
    {
        if ($config['driver'] == 'pdo_sqlite') {
            return $config;
        }

        /*
         * Primary ReadReplica Connection
         *
         * $conn = DriverManager::getConnection(array(
         *    'wrapperClass' => 'Doctrine\DBAL\Connections\PrimaryReadReplicaConnection',
         *    'driver' => 'pdo_mysql',
         *    'primary' => array('user' => '', 'password' => '', 'host' => '', 'dbname' => ''),
         *    'replica' => array(
         *        array('user' => 'replica1', 'password', 'host' => '', 'dbname' => ''),
         *        array('user' => 'replica2', 'password', 'host' => '', 'dbname' => ''),
         *    )
         * ));
         *
         */

        if (empty($config['base']['port'])) {
            $config['base']['port'] = 3306;
        }

        // primary => 127.0.0.1
        // primary => [host => 127.0.0.1]
        // replica => 127.0.0.1
        // replica => [127.0.0.1, 127.0.0.2]
        // replica => [[host => 127.0.0.1], [host => 127.0.0.2]]

        // 处理其他格式的master
        if (is_string($config['primary'])) {
            // master => 127.0.0.1
            $config['primary'] = ['host' => $config['primary']];
        }
        $config['primary'] = array_merge($config['base'], $config['primary']);

        if ($config['withReplica'] && ! empty($config['replica'])) {
            $replica = $config['replica'];

            // 处理其他格式的从库
            if (is_string($replica)) {
                // replica => 127.0.0.1
                $config['replica'] = [['host' => $replica]];
            } elseif (is_array($replica) && is_string($replica[0])) {
                $config['replica'] = [];
                // replica => [127.0.0.1, 127.0.0.1]
                foreach ($replica as $r) {
                    $config['replica'][] = ['host' => $r];
                }
            }

            $tmp = [];
            foreach ($config['replica'] as $r) {
                $tmp[] = array_merge($config['base'], $r);
            }

            $config['replica'] = $tmp;
        } else {
            $master = $config['primary'];
            $config = array_merge($config, $master);

            unset($config['primary'], $config['replica']);
        }
        unset($config['base'], $config['withReplica']);

        return $config;
    }
}
