<?php

namespace Neo;

use Neo\Cache\Memcached\MemcachedNull;
use Neo\Cache\Memcached\MemcachedStore;
use Neo\Cache\Redis\Redis;
use Neo\Cache\Redis\RedisNull;
use Neo\Database\MySQL;
use Neo\Database\MySQLExplain;
use Neo\Database\NeoDatabase;
use Neo\Exception\DatabaseException;
use Neo\Exception\RedisException;
use Neo\Exception\ResourceNotFoundException;
use Neo\Html\Page;
use Neo\Html\Template;
use Neo\Http\Request;
use Neo\Http\Response;

/**
 * NeoFrame Container
 */
class NeoFrame implements \ArrayAccess
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
     * @var array
     */
    private $bindings = [];

    /**
     * Request object.
     *
     * @var Request
     */
    public $request;

    /**
     * Response object.
     *
     * @var Response
     */
    public $response;

    /**
     * Database object.
     *
     * @var NeoDatabase
     */
    private $db;

    /**
     * 是否使用MySQLExplain初始化数据库连接，在页面上显示SQL Explain信息
     * 0: 不启用
     * 1: 显示SQL Explain，SQL一行显示
     * 2: 显示SQL Explain，SQL格式化显示
     */
    private $explainSQL = 0;

    /**
     * Redis object.
     *
     * @var Redis
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
     * @var array
     */
    private $user = [];

    /**
     * Array of system Config
     *
     * @var array
     */
    private $config = [];

    /**
     * 脚本开始时间
     *
     * @var float
     */
    private $timeStart = 0;

    /**
     * NeoFrame
     *
     * @var NeoFrame
     */
    private static $instance = null;

    /**
     * NeoFrame constructor.
     *
     * @param null|string $absPath
     */
    public function __construct(string $absPath = null)
    {
        // 当前时间戳
        define('TIMENOW', time());

        // Error Handler
        set_error_handler([$this, 'errorHandler'], E_ALL & ~E_NOTICE & ~E_STRICT & ~E_DEPRECATED);

        // Exception Handler
        set_exception_handler([$this, 'exceptionHandler']);

        // 系统所在目录
        $this->setAbsPath($absPath);

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
     * 错误处理
     *
     * @param int    $severity Error number
     * @param string $errstr   PHP error text string
     * @param string $errfile  File that contained the error
     * @param int    $errline  Line in the file that contained the error
     *
     * @throws \ErrorException
     * @return bool            true: don't execute PHP internal error handler
     */
    public function errorHandler($severity, $errstr, $errfile, $errline)
    {
        if (! (error_reporting() & $severity)) {
            return true;
        }

        if ($severity == E_USER_ERROR) {
            throw new \ErrorException($errstr, 0, $severity, $errfile, $errline);
        }

        @NeoLog::warning('warning', 'Warning', "{$errstr} in {$errfile} on line {$errline}");

        return true;
    }

    /**
     * 处理未捕捉的异常
     *
     * @param \Throwable $ex 异常实例
     */
    public function exceptionHandler(\Throwable $ex)
    {
        @NeoLog::error('exceptionhandler', 'Exception', $ex);

        $host = Utility::gethostname();

        $errors = [];
        $msg = "{$ex->getMessage()} " . PHP_EOL . "F: {$ex->getFile()} on line {$ex->getLine()} " . PHP_EOL . "H: {$host}";
        $title = __('The server is asleep, please wake it up.');

        Page::neoDie(removeSysPath($msg), $title, $errors);
    }

    /**
     * Destruct
     */
    public function unload()
    {
        unset($this->db, $this->redis, $this->memcached, $this->request, $this->response);
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
     *
     * @param string $absPath
     */
    public function setAbsPath($absPath)
    {
        $this->abspath = rtrim($absPath, '\/');

        $this->setPaths();
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
     */
    public function setPaths()
    {
        // 控制器路径
        $this->bindings['controllers_dir'] = $this->getAbsPath() . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Controller';

        // 模版文件路径
        $this->bindings['templates_dir'] = $this->getAbsPath() . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'View';

        // 语言文件路径
        $this->bindings['languages_dir'] = $this->getAbsPath() . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Language';

        // 数据缓存路径
        $this->bindings['datastore_dir'] = $this->getAbsPath() . DIRECTORY_SEPARATOR . 'datastore';

        // 资源文件路径
        $this->bindings['content_dir'] = $this->getAbsPath() . DIRECTORY_SEPARATOR . 'public';

        // 日志文件存放路径
        if (defined('NEO_LOG_DIR') && NEO_LOG_DIR) {
            $this->bindings['log_dir'] = NEO_LOG_DIR;
        }
    }

    /**
     * 检查日志文件存放目录
     */
    private function checkLoggerDir()
    {
        $dir = $this->bindings['log_dir'];

        // 使用文件记录日志
        if (defined('NEO_LOG_FILE') && NEO_LOG_FILE) {
            if (empty($dir)) {
                throw new ResourceNotFoundException(__('Logger dir cannot be null.'));
            }

            if (! is_dir($dir) || ! is_writeable($dir)) {
                throw new ResourceNotFoundException(__f('Logger dir %s cannot be writeable.', $dir));
            }
        }
    }

    /**
     * 当前登录用户
     *
     * @param array $user
     */
    public function setUser(array $user)
    {
        $this->user = $user;
    }

    /**
     * 当前登录用户
     *
     * @return array
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
            $this->request = Request::createFromGlobals();
        }

        return $this->request;
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
     * 获取响应的系统配置
     *
     * @param null|string $key
     *
     * @return null|array|string
     */
    public function getConfig(?string $key = null)
    {
        $config = (array) $this->config;

        if ($key) {
            return $config[$key] ?? null;
        }

        return $config;
    }

    /**
     * 添加配置文件
     *
     * @param array $config
     */
    public function setConfig(array $config)
    {
        $this->config = $config;
    }

    /**
     * Get instance
     *
     * @return static
     */
    public static function getInstance()
    {
        if (is_null(static::$instance)) {
            static::$instance = new static();
        }

        return static::$instance;
    }

    /**
     * Set instance
     *
     * @param null|NeoFrame $container
     *
     * @return static
     */
    public static function setInstance(NeoFrame $container = null)
    {
        return static::$instance = $container;
    }

    /**
     * 初始化数据库连接
     *
     * @param array $config    数据库参数
     * @param bool  $withSlave 是否启用从数据库
     *
     * @return null|MySQL|MySQLExplain
     */
    public static function initDatabase(array $config, bool $withSlave = true)
    {
        // 是否在页面上显示SQL Explain信息
        if (neo()->getExplainSQL()) {
            $db = new MySQLExplain();
        } else {
            switch ($config['driver']) {
                case 'pdo_mysql':
                case 'mysqli':
                    $db = new MySQL();
                    break;
                default:
                    $db = null;
                    break;
            }
        }

        if ($db == null) {
            throw new DatabaseException(__f('Invalid mysql driver %s.', $config['driver']));
        }

        $config['withSlave'] = $withSlave;

        // 创建数据库连接
        $db->connect($config);

        return $db;
    }

    /**
     * 获取数据库连接
     *
     * @param bool $init 如果没有链接，则初始化
     *
     * @return MySQL|NeoDatabase
     */
    public function getDB(bool $init = true)
    {
        if ($init && (! $this->db || ! $this->db instanceof NeoDatabase)) {
            $this->db = static::initDatabase($this->config['database']['mysql']);
        }

        return $this->db;
    }

    /**
     * 是否使用MySQLExplain初始化数据库连接，在页面上显示SQL Explain信息
     *
     * @param int $ex
     */
    public function setExplainSQL(int $ex = 0)
    {
        $this->explainSQL = $ex;
    }

    /**
     * 是否使用MySQLExplain初始化数据库连接，在页面上显示SQL Explain信息
     *
     * @return int
     */
    public function getExplainSQL()
    {
        return $this->explainSQL;
    }

    /**
     * 初始化一个Redis
     *
     * @param string $app
     * @param string $type
     *
     * @return null|Redis|RedisNull
     */
    public static function initRedis($app = 'neo', string $type = 'master')
    {
        $redis = null;

        try {
            Redis::addServer((array) static::getInstance()->getConfig('redis')[$app]);

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
     * @param Redis $redis
     */
    public function setRedis(Redis $redis)
    {
        $this->redis = $redis;
    }

    /**
     * 获取Redis
     *
     * @return Redis|RedisNull
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
    public function offsetExists($key)
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
    public function offsetGet($key)
    {
        return $this->bindings[$key];
    }

    /**
     * Set the value at a given offset.
     *
     * @param string $key
     * @param mixed  $value
     */
    public function offsetSet($key, $value)
    {
        $this->bindings[$key] = $value;
    }

    /**
     * Unset the value at a given offset.
     *
     * @param string $key
     */
    public function offsetUnset($key)
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
        $this[$key] = $value;
    }
}
