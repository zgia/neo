<?php

namespace Neo;

use Neo\Cache\Memcached\MemcachedNull;
use Neo\Cache\Memcached\MemcachedStore;
use Neo\Cache\Redis\Redis;
use Neo\Cache\Redis\RedisException;
use Neo\Cache\Redis\RedisNull;
use Neo\Database\AbstractDatabase;
use Neo\Database\DatabaseException;
use Neo\Database\MySQL;
use Neo\Database\SQLite;
use Neo\Exception\FileException;
use Neo\Exception\ResourceNotFoundException;
use Neo\Html\Page;
use Neo\Html\Template;
use Neo\Http\Request;
use Neo\Http\Response;

/**
 * Neo Container
 */
class Neo implements \ArrayAccess
{
    /**
     * The base path for the system
     *
     * @var string
     */
    private $abspath = '';

    /**
     * The container's bindings.
     *
     * @var array<string,mixed>
     */
    private array $bindings = [];

    /**
     * Request object.
     *
     * @var Request
     */
    public $request;

    /**
     * 请求类型
     *
     * @var string
     */
    private $requestType = 'fpm';

    /**
     * 请求参数
     *
     * @var array<string,mixed>
     */
    private array $requestData = [];

    /**
     * Response object.
     *
     * @var Response
     */
    public $response;

    /**
     * Database object.
     *
     * @var AbstractDatabase
     */
    private $db;

    /**
     * Redis object.
     *
     * @var Cache\CacheInterface
     */
    private $redis;

    /**
     * MemcachedStore object.
     *
     * @var MemcachedStore
     */
    private $memcached;

    /**
     * 用于将变量传递给模版
     *
     * @var Template
     */
    private $template;

    /**
     * User Information
     *
     * @var array<string,mixed>
     */
    private array $user = [];

    /**
     * 脚本开始时间
     *
     * @var float
     */
    private $timeStart = 0;

    /**
     * Neo
     *
     * @var null|static
     */
    private static $instance;

    /**
     * 服务运行模式: 作为api，web还是cli方式提供服务
     *
     * @var string
     */
    private static string $serverMode = 'api';

    /**
     * Neo constructor.
     *
     * @param null|callable $exceptionHandler
     */
    public function __construct(callable $exceptionHandler = null)
    {
        // 当前时间戳
        define('TIMENOW', time());

        // Error Handler
        set_error_handler([$this, 'errorHandler'], E_ALL & ~E_NOTICE & ~E_STRICT & ~E_DEPRECATED);

        // Exception Handler
        set_exception_handler(is_callable($exceptionHandler) ? $exceptionHandler : [$this, 'exceptionHandler']);

        // 系统所在目录
        $this->setAbsPath();

        static::setInstance($this);

        // 多语言
        I18n::loadDefaultLanguage($this->bindings['languages_dir']);

        // 检查日志文件存放目录
        $this->checkLoggerDir();

        // HTTP Request
        $this->getRequest();

        // HTTP Response
        $this->getResponse();

        $this->timeStart = microtime(true);
    }

    /**
     * 服务运行模式
     *
     * @return string
     */
    public static function getServerMode()
    {
        return self::$serverMode;
    }

    /**
     * 服务运行模式
     *
     * @param string $mode
     */
    public static function setServerMode(string $mode): void
    {
        self::$serverMode = $mode;
    }

    /**
     * 错误处理
     *
     * @param int    $severity Error number
     * @param string $errstr   PHP error text string
     * @param string $errfile  File that contained the error
     * @param int    $errline  Line in the file that contained the error
     *
     * @return bool true: don't execute PHP internal error handler
     *
     * @throws \ErrorException
     */
    public function errorHandler($severity, $errstr, $errfile, $errline)
    {
        if (! (error_reporting() & $severity)) {
            return true;
        }

        if ($severity == E_USER_ERROR) {
            throw new \ErrorException($errstr, 0, $severity, $errfile, $errline);
        }

        return true;
    }

    /**
     * 处理未捕捉的异常
     *
     * @param \Throwable $ex 异常实例
     */
    public function exceptionHandler(\Throwable $ex): void
    {
        $host = Utility::gethostname();

        $errors = [];
        $msg = "{$ex->getMessage()} " . PHP_EOL . "F: {$ex->getFile()} on line {$ex->getLine()} " . PHP_EOL . "H: {$host}";
        $title = __('The server is asleep, please wake it up.');

        Page::neoDie(removeSysPath($msg), $title, $errors);
    }

    /**
     * Destruct
     */
    public function unload(): void
    {
        unset($this->db, $this->redis, $this->memcached, $this->request, $this->response);

        self::$instance = null;
    }

    /**
     * 获取系统缺省的Charset，默认是utf-8
     *
     * @return string Charset
     */
    public static function charset()
    {
        return (defined('NEO_CHARSET') && NEO_CHARSET) ? NEO_CHARSET : 'utf-8';
    }

    /**
     * 获取系统缺省的语言设置，默认是zh-CN
     *
     * @return string Language
     */
    public static function language()
    {
        return (defined('NEO_LANG') && NEO_LANG) ? NEO_LANG : 'zh-CN';
    }

    /**
     * 脚本开始时间
     *
     * @return float
     */
    public function getTimeStart()
    {
        return $this->timeStart;
    }

    /**
     * Set the base path for the application.
     */
    public function setAbsPath(): void
    {
        $paths = Config::get('dir', null, []);

        $this->abspath = rtrim($paths['abs_path'] ?? '', '\/');

        $this->setPaths($paths);
    }

    /**
     * Get the base path for the application.
     *
     * @return string
     */
    public function getAbsPath()
    {
        return $this->abspath;
    }

    /**
     * 设置多个目录
     *
     * @param null|array<string,string> $paths
     */
    public function setPaths(?array $paths = null): void
    {
        // 控制器路径
        $this->bindings['controllers_dir'] = $paths['controllers'] ?? null;

        // 模版文件路径
        $this->bindings['templates_dir'] = $paths['templates'] ?? null;

        // 语言文件路径
        $this->bindings['languages_dir'] = $paths['languages'] ?? null;

        // 数据缓存路径
        $this->bindings['datastore_dir'] = $paths['datastore'] ?? null;

        // 资源文件路径
        $this->bindings['content_dir'] = $paths['content'] ?? null;

        // 日志文件存放路径
        $this->bindings['log_dir'] = Config::get('logger', 'dir', null);
    }

    /**
     * 使用文件记录日志
     *
     * 检查日志文件存放目录
     */
    private function checkLoggerDir(): void
    {
        if (! in_array('file', Config::get('logger', 'types', []))) {
            return;
        }

        $dir = $this->bindings['log_dir'];

        if (empty($dir)) {
            throw new ResourceNotFoundException(__('Logger dir cannot be null.'));
        }

        if (! is_dir($dir) || ! is_writeable($dir)) {
            throw new FileException(__f('Logger dir(%s) is not writeable.', $dir));
        }
    }

    /**
     * 当前操作用户
     *
     * @param array<string,mixed> $user
     */
    public function setUser(array $user): void
    {
        $this->user = $user;
    }

    /**
     * 当前操作用户
     *
     * @return array<string,mixed>
     */
    public function getUser()
    {
        return $this->user;
    }

    /**
     * 获取模板引擎
     *
     * @return Template
     */
    public function getTemplate()
    {
        if ($this->template === null) {
            $this->template = new Template();
        }

        return $this->template;
    }

    /**
     * 获取Neo Request
     *
     * @return Request
     */
    public function getRequest()
    {
        if ($this->request === null) {
            // @phpstan-ignore-next-line
            $this->request = Request::createRequest($this->requestType, $this->requestData);
        }

        // @phpstan-ignore-next-line
        return $this->request;
    }

    /**
     * 设置请求类型
     *
     * @param string $type
     */
    public function setRequestType(string $type): void
    {
        $this->requestType = $type;
    }

    /**
     * 设置请求数据
     *
     * @param array<string,mixed> $data
     */
    public function setRequestData(?array $data = null): void
    {
        $this->requestData = $data;
    }

    /**
     * 获取Neo Response
     *
     * @return Response
     */
    public function getResponse()
    {
        if ($this->response === null) {
            $this->response = new Response();

            $this->response->setCharset(static::charset());
        }

        return $this->response;
    }

    /**
     * Get instance
     *
     * @return null|static
     */
    public static function getInstance()
    {
        if (is_null(self::$instance)) {
            self::$instance = new static();
        }

        return self::$instance;
    }

    /**
     * Set instance
     *
     * @param null|static $container
     *
     * @return null|static
     */
    public static function setInstance(Neo $container = null)
    {
        return self::$instance = $container;
    }

    /**
     * 初始化数据库连接
     *
     * $config:
     *         [
     *             'driver' => 'pdo_mysql',
     *             'prefix' => '',
     *             'base' => [
     *                 'dbname' => 'db_name',
     *                 'port' => 3306,
     *                 'user' => 'db_user',
     *                 'password' => 'db_password',
     *                 'charset' => 'utf8mb4',
     *             ],
     *             'primary' => ['host' => '127.0.0.1'],
     *             'replica' => [
     *                 ['host' => '127.0.0.1'],
     *                 ['host' => '127.0.0.1'],
     *             ],
     *             'logger' => \Neo\Database\Logger::class,
     *         ]
     *
     * @param array<string,mixed> $config      数据库参数
     * @param bool                $withReplica 是否启用从数据库
     *
     * @return AbstractDatabase
     */
    public static function initDatabase(array $config, bool $withReplica = true)
    {
        if (empty($config)) {
            throw new DatabaseException(__f('Invalid database config.'));
        }

        $config['withReplica'] = $withReplica;
        $config = Config::parseDatabaseConfig($config);

        switch ($config['driver']) {
            case 'pdo_mysql':
            case 'mysqli':
                $db = new MySQL();

                break;

            case 'pdo_sqlite':
                $db = new SQLite();

                break;

            default:
                $db = null;

                break;
        }

        if ($db == null) {
            throw new DatabaseException(__f('Invalid database driver %s.', $config['driver']));
        }

        // 创建数据库连接
        $db->parseConfig($config);
        $db->connect();

        return $db;
    }

    /**
     * 获取数据库连接
     *
     * @param bool  $init   如果没有链接，则初始化
     * @param mixed $driver 使用哪个驱动
     *
     * @return AbstractDatabase
     */
    public function getDB(bool $init = true, $driver = 'mysql')
    {
        // @phpstan-ignore-next-line
        if ($init && ! ($this->db && $this->db instanceof AbstractDatabase)) {
            $this->db = static::initDatabase(Config::get('database', $driver, []));
        }

        return $this->db;
    }

    /**
     * 初始化一个Redis
     *
     * @param string $app
     * @param string $type
     *
     * @return Redis|RedisNull
     */
    public static function initRedis($app = 'neo', string $type = 'master')
    {
        $redis = null;

        try {
            Redis::addServer((array) Config::get('redis', $app, []));

            $redis = Redis::getInstance($type);
        } catch (RedisException $ex) {
            NeoLog::error('redis', __FUNCTION__, $ex);
        }

        if (! $redis instanceof Redis || $redis->isDown()) {
            $redis = new RedisNull();
        }

        return $redis;
    }

    /**
     * 设置Redis
     *
     * @param Cache\CacheInterface $redis
     */
    public function setRedis(Cache\CacheInterface $redis): void
    {
        $this->redis = $redis;
    }

    /**
     * 获取Redis
     *
     * @return Cache\CacheInterface|Redis|RedisNull
     */
    public function getRedis()
    {
        return $this->redis;
    }

    /**
     * 初始化一个Memcached
     *
     * @param string $type Memcached服务
     *
     * @return MemcachedStore
     */
    public static function initMemcached(string $type = 'master')
    {
        $memcached = MemcachedStore::getInstance($type);

        if (! $memcached instanceof MemcachedStore || $memcached->isDown()) {
            $memcached = new MemcachedNull();
        }

        return $memcached;
    }

    /**
     * Get Memcached
     *
     * @return MemcachedStore
     */
    public function getMemcached()
    {
        return $this->memcached;
    }

    /**
     * Determine if a given offset exists.
     *
     * @param string $key
     *
     * @return bool
     */
    public function offsetExists($key): bool
    {
        return isset($this->bindings[$key]);
    }

    /**
     * Get the value at a given offset.
     *
     * @param string $key
     *
     * @return mixed
     */
    public function offsetGet($key): mixed
    {
        return $this->bindings[$key] ?? null;
    }

    /**
     * Set the value at a given offset.
     *
     * @param string $key
     * @param mixed  $value
     */
    public function offsetSet($key, $value): void
    {
        if (empty($key)) {
            $this->bindings[] = $value;
        } else {
            $this->bindings[$key] = $value;
        }
    }

    /**
     * Unset the value at a given offset.
     *
     * @param string $key
     */
    public function offsetUnset($key): void
    {
        unset($this->bindings[$key]);
    }

    /**
     * Dynamically access container services.
     *
     * @param string $key
     *
     * @return mixed
     */
    public function __get($key)
    {
        return $this[$key] ?? null;
    }

    /**
     * Dynamically set container services.
     *
     * @param string $key
     * @param mixed  $value
     */
    public function __set($key, $value)
    {
        if (! empty($key)) {
            $this[$key] = $value;
        }
    }
}
