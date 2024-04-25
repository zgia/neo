<?php

namespace Neo\Cache\Redis;

/**
 * Class RedisKeyConfig
 */
class RedisKeyConfig
{
    /**
     * @var array<string,mixed>
     */
    private static $keyConfig;

    /**
     * 设置Redis keys
     *
     * @param string $key
     *
     * @return null|string
     */
    public static function getConfig(string $key)
    {
        if (self::$keyConfig == null) {
            self::$keyConfig = (array) neo()->cacheKeys;
        }

        return self::$keyConfig[$key] ?? null;
    }

    /**
     * 获取Key
     *
     * @param string $key
     * @param string $prefix
     *
     * @return string
     *
     * @throws RedisException
     */
    public static function getKey(?string $key = null, string $prefix = '')
    {
        if (! $key) {
            return '';
        }

        $keyParam = explode(':', $key);
        $index = array_shift($keyParam);

        // $keyConfig
        // ['index' => 'index:%s:%s']
        // $key = 'index:123:456'

        if (! ($value = self::getConfig($index))) {
            return $prefix . $key;
        }

        return $prefix . vsprintf($value, $keyParam);
    }
}
