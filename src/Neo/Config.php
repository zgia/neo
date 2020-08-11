<?php

namespace Neo;

/**
 * 配置处理
 */
class Config
{
    /**
     * @var array
     */
    private static $config;

    /**
     * 加载配置文件
     *
     * @param array $config
     */
    public static function load(array $config)
    {
        static::$config = $config;
    }

    /**
     * 返回全部的配置项
     *
     * @return array
     */
    public static function all()
    {
        return static::$config;
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
        if (! isset(static::$config[$type])) {
            return $default;
        }

        $cfg = static::$config[$type];

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
    public static function setValue(string $key, $value)
    {
        static::$config[$key] = $value;
    }

    /**
     * 设置某个配置项
     *
     * @param string $type
     * @param string $key
     * @param mixed  $value
     */
    public static function set(string $type, string $key, $value)
    {
        static::$config[$type][$key] = $value;
    }

    /**
     * 解析数据库配置，以便支持Doctrine
     *
     * @param array $config
     *
     * @return array
     */
    public static function parseDatabaseConfig(array $config)
    {
        /*
         * Master-Slave Connection
         *
         * $conn = DriverManager::getConnection(array(
         *    'wrapperClass' => 'Doctrine\DBAL\Connections\MasterSlaveConnection',
         *    'driver' => 'pdo_mysql',
         *    'master' => array('user' => '', 'password' => '', 'host' => '', 'dbname' => ''),
         *    'slaves' => array(
         *        array('user' => 'slave1', 'password', 'host' => '', 'dbname' => ''),
         *        array('user' => 'slave2', 'password', 'host' => '', 'dbname' => ''),
         *    )
         * ));
         *
         */

        if (empty($config['base']['port'])) {
            $config['base']['port'] = 3306;
        }

        // master => 127.0.0.1
        // master => [host => 127.0.0.1]
        // slaves => 127.0.0.1
        // slaves => [127.0.0.1, 127.0.0.2]
        // slaves => [[host => 127.0.0.1], [host => 127.0.0.2]]

        // 处理其他格式的master
        if (is_string($config['master'])) {
            // master => 127.0.0.1
            $config['master'] = ['host' => $config['master']];
        }
        $config['master'] = array_merge($config['base'], $config['master']);

        if ($config['withSlave'] && ! empty($config['slaves'])) {
            $slaves = $config['slaves'];

            // 处理其他格式的slave
            if (is_string($slaves)) {
                // slaves => 127.0.0.1
                $config['slaves'] = [['host' => $slaves]];
            } elseif (is_array($slaves) && is_string($slaves[0])) {
                $config['slaves'] = [];
                // slaves => [127.0.0.1, 127.0.0.1]
                foreach ($slaves as $slave) {
                    $config['slaves'][] = ['host' => $slave];
                }
            }

            $tmp = [];
            foreach ($config['slaves'] as $slave) {
                $tmp[] = array_merge($config['base'], $slave);
            }

            $config['slaves'] = $tmp;
        } else {
            $master = $config['master'];
            $config = array_merge($config, $master);

            unset($config['master'], $config['slaves']);
        }

        unset($config['base'], $config['withSlave']);

        return $config;
    }
}
