<?php

namespace Neo;

use Neo\Cache\Memcached\MemcachedNull;
use Neo\Cache\Memcached\MemcachedStore;
use Neo\Cache\Redis\Redis;
use Neo\Cache\Redis\RedisNull;
use Neo\Database\MySQLExplain;
use Neo\Database\MySQLi;
use Neo\Database\NeoDatabase;
use Neo\Database\PdoMySQL;
use Neo\Exception\DatabaseException;
use Neo\Exception\RedisException;
use Neo\Exception\ResourceNotFoundException;
use Neo\Html\Page;
use Neo\Http\Request as NeoRequest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

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
    protected static $ABSPATH = '';

    /**
     * The container's bindings.
     *
     * @var array
     */
    protected $bindings = [];

    /**
     * Request object.
     *
     * @var \Symfony\Component\HttpFoundation\Request
     */
    public $request;

    /**
     * Response object.
     *
     * @var \Symfony\Component\HttpFoundation\Response
     */
    public $response;

    /**
     * Database object.
     *
     * @var \Neo\Database\NeoDatabase
     */
    public $db;

    /**
     * Redis object.
     *
     * @var \Neo\Cache\Redis\Redis
     */
    public $redis;

    /**
     * Redis object.
     *
     * @var \Neo\Cache\Redis\Redis
     */
    public $redisslave;

    /**
     * MemcachedStore object.
     *
     * @var \Neo\Cache\Memcached\MemcachedStore
     */
    public $memcached;

    /**
     * 用于将变量传递给模版
     *
     * @var array
     */
    public $templateVars = [];

    /**
     * Internationalization
     *
     * @var array
     */
    public $i18n = [];

    /**
     * User Information
     *
     * @var array
     */
    public $user = [];

    /**
     * User ID
     *
     * @var int
     */
    public $userid = 0;

    /**
     * language
     *
     * @var array
     */
    public $lang = [];

    /**
     * Array of system Config
     *
     * @var array
     */
    public $config = [];

    /**
     * Array of data that has been cleaned by the input cleaner.
     *
     * @var array
     */
    public $input = [];

    /**
     * 脚本开始时间
     *
     * @var float
     */
    private $timeStart = 0;

    /**
     * NeoFrame
     *
     * @var \Neo\NeoFrame
     */
    private static $instance = null;

    /**
     * NeoFrame constructor.
     *
     * @param null|string $absPath
     */
    public function __construct(?string $absPath = null)
    {
        // 当前时间戳
        define('TIMENOW', time());

        // Error Handler
        set_error_handler([$this, 'errorHandler'], E_ALL & ~E_NOTICE & ~E_STRICT & ~E_DEPRECATED);

        // Exception Handler
        set_exception_handler([$this, 'exceptionHandler']);

        // 如果定义了常量
        if (empty($absPath) && defined('ABSPATH') && ABSPATH) {
            $absPath = ABSPATH;
        }

        if ($absPath) {
            $this->setAbsPath($absPath);
        }

        static::setInstance($this);

        // 多语言
        I18n::loadDefaultLanguage($this->bindings['languages_dir']);

        // 检查日志文件存放目录
        $this->checkLoggerDir();

        // HTTP Request
        $this->request = Request::createFromGlobals();

        // HTTP Response
        $this->response = new Response();

        $this->response->setCharset(static::charset());

        $this->timeStart = (defined('NEO_TIMESTART') && NEO_TIMESTART) ? NEO_TIMESTART : microtime(true);
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

        Page::neoDie(removeSystemPathFromFileName($msg), $title, $errors);
    }

    /**
     * Destruct
     */
    public function unload()
    {
        unset($this->db, $this->redis, $this->redisslave, $this->memcached, $this->request, $this->response);
    }

    /**
     * 获取系统缺省的Charset，默认是utf-8
     *
     * @return string Charset
     */
    public static function charset()
    {
        $charset = 'utf-8';
        if (defined('NEO_CHARSET') && NEO_CHARSET) {
            $charset = NEO_CHARSET;
        }

        return $charset;
    }

    /**
     * 获取系统缺省的语言设置，默认是zh-CN
     *
     * @return string Language
     */
    public static function language()
    {
        $lang = 'zh-CN';
        if (defined('NEO_LANG') && NEO_LANG) {
            $lang = NEO_LANG;
        }

        return $lang;
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
        static::$ABSPATH = rtrim($absPath, '\/');

        $this->setPaths();
    }

    /**
     * Get the base path for the application.
     *
     * @return string
     */
    public static function getAbsPath()
    {
        return static::$ABSPATH;
    }

    /**
     * 设置多个目录
     */
    public function setPaths()
    {
        // 控制器路径
        $this->bindings['controllers_dir'] = static::getAbsPath() . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Controller';

        // 模版文件路径
        $this->bindings['templates_dir'] = static::getAbsPath() . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'View';

        // 语言文件路径
        $this->bindings['languages_dir'] = static::getAbsPath() . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Language';

        // 数据缓存路径
        $this->bindings['datastore_dir'] = static::getAbsPath() . DIRECTORY_SEPARATOR . 'datastore';

        // 资源文件路径
        $this->bindings['content_dir'] = static::getAbsPath() . DIRECTORY_SEPARATOR . 'public';

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
     * 把变量传到模版中
     *
     * @param array $elements 变量
     */
    public function setTemplateVars(array $elements)
    {
        if (empty($elements)) {
            return;
        }

        if (! is_array($this->templateVars)) {
            $this->templateVars = [];
        }

        $this->templateVars = array_merge($this->templateVars, $elements);
    }

    /**
     * 获取数据库连接
     *
     * @param bool $init 如果没有链接，则初始化
     *
     * @return Database\MySQLi|Database\NeoDatabase|Database\PdoMySQL
     */
    public function getDB(bool $init = true)
    {
        if ($init && (! $this->db || ! $this->db instanceof NeoDatabase)) {
            $this->db = static::initDatabase($this->config['database']['mysql']);
        }

        return $this->db;
    }

    /**
     * 获取响应的系统配置
     *
     * @param null|string $key
     *
     * @return null|array|string
     */
    public static function getConfig(?string $key = null)
    {
        static $config = null;

        if ($config === null) {
            $config = (array) static::getInstance()->config;
        }

        if ($key) {
            return $config[$key] ?? null;
        }

        return $config;
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
     * @return null|MySQLExplain|MySQLi|PdoMySQL
     */
    public static function initDatabase(array $config, bool $withSlave = true)
    {
        $neo = neo();

        // 是否显示当前页面的数据库查询信息
        if ($neo['explain_sql']) {
            $db = new MySQLExplain();
        } else {
            switch ($config['driver']) {
            case 'pdo_mysql':
                $db = new PdoMySQL();
                break;
            case 'mysqli':
                $db = new MySQLi();
                break;
            default:
                $db = null;
                break;
        }
        }

        if ($db == null) {
            throw new DatabaseException(__f('Invalid mysql driver %s.', $config['driver']));
        }

        // 是否使用数据库代理
        $useDBProxy = $config['proxy'] ?? false;

        $master = $config['master'];
        $slaves = $config['slaves'] ?? [];
        unset($config['master'], $config['slaves']);

        // 从库
        if (! $useDBProxy && $withSlave && $slaves) {
            $index = 0;

            // 根据IP在多个从数据库切换
            $hosts = (array) $slaves['host'];

            $slaveCount = count($hosts);
            if ($slaveCount > 1) {
                $index = ord(md5(NeoRequest::ip())[0]) % $slaveCount;
            }
            // 用于日志
            $neo['dbSlaveIndex'] = $index;

            // 从库配置
            $db->slaveConfig = $config;

            $db->slaveConfig['host'] = $hosts[$index];
        }

        // 创建数据库连接
        $config['host'] = $master['host'];
        $config['port'] = (int) $config['port'];

        $db->connect($config);
        $db->setUseDBProxy($useDBProxy);
        $db->setTablePrefix($config['prefix']);

        return $db;
    }

    /**
     * 初始化一个Redis
     *
     * @param string $app
     * @param string $type
     *
     * @return null|Redis|RedisNull
     */
    public static function initRedis($app, string $type = 'master')
    {
        if (defined('NEO_REDIS') && NEO_REDIS) {
            $redis = null;

            try {
                Redis::addServer((array) NeoFrame::getConfig('redis')[$app]);

                $redis = Redis::getInstance($type);
            } catch (RedisException $ex) {
                NeoLog::error('redis', __FUNCTION__, $ex);
            }

            if (! $redis instanceof Redis || $redis->isWentAway()) {
                $redis = new RedisNull();
            }
        } else {
            $redis = new RedisNull();
        }

        return $redis;
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
        if (defined('NEO_MEMCACHED') && NEO_MEMCACHED) {
            $memcached = MemcachedStore::getInstance($type);

            if (! $memcached instanceof MemcachedStore || $memcached->isWentAway()) {
                $memcached = new MemcachedNull();
            }
        } else {
            $memcached = new MemcachedNull();
        }

        return $memcached;
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
