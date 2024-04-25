<?php

namespace Neo\Cache\File;

use Neo\Cache\CacheInterface;

/**
 * File Cache Class
 *
 * Class File
 */
class File implements CacheInterface
{
    /**
     * @var File
     */
    private static $_instance;

    /**
     * 写入的缓存文件内容
     *
     * @var string
     */
    private static $_fileContent;

    /**
     * @param string $key
     * @param mixed  $val
     * @param mixed  $expired
     *
     * @return bool
     */
    public function set(string $key, $val, $expired = 0)
    {
        self::write($key, $val);

        return true;
    }

    /**
     * 获取
     *
     * @param $key
     *
     * @return array<mixed>
     */
    public function get(string $key)
    {
        return self::read($key);
    }

    /**
     * 得到 File 实例
     *
     * @return File
     */
    public static function getInstance()
    {
        if (self::$_instance == null) {
            self::$_instance = new self();
        }

        return self::$_instance;
    }

    /**
     * @return bool
     */
    public function isDown()
    {
        return false;
    }

    /**
     * 写入缓存
     *
     * @param string       $path
     * @param array<mixed> $data
     * @param bool         $withTag
     * @param string       $arrayName
     * @param string       $cacheDir
     *
     * @return bool
     */
    public static function write(
        string $path,
        array $data,
        bool $withTag = true,
        string $arrayName = '',
        string $cacheDir = ''
    ) {
        $file = static::getFilePath($path, $cacheDir);

        if ($handle = fopen($file, 'w+')) {
            $tag = $withTag ? '<?php' . PHP_EOL : '';
            $prefix = $arrayName ? "\${$arrayName} = " : 'return ';

            $dataConvert = $tag . $prefix . var_export($data, true) . ';';

            flock($handle, LOCK_EX);
            $rs = fputs($handle, $dataConvert);
            flock($handle, LOCK_UN);
            fclose($handle);

            if ($rs !== false) {
                self::$_fileContent = $dataConvert;

                return true;
            }
        }

        return false;
    }

    /**
     * 获取上次写入缓存文件的内容
     *
     * @return string
     */
    public static function getFileContent()
    {
        $v = self::$_fileContent;

        self::$_fileContent = '';

        return $v;
    }

    /**
     * 获取缓存
     *
     * @param string $key
     * @param string $cacheDir
     *
     * @return array<mixed>
     */
    public static function read(string $key, string $cacheDir = '')
    {
        $file = static::getFilePath($key, $cacheDir);

        $cache = [];

        if (file_exists($file)) {
            $cache = @include $file;

            $cache || $cache = [];
        }

        return $cache;
    }

    /**
     * 缓存文件路径
     *
     * @param string $key
     * @param string $cacheDir
     *
     * @return string
     */
    public static function getFilePath(string $key, string $cacheDir = '')
    {
        $cacheDir || $cacheDir = neo()['datastore_dir'];

        return $cacheDir . DIRECTORY_SEPARATOR . $key . '.php';
    }
}
