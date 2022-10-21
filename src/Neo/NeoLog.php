<?php

namespace Neo;

use Monolog\Formatter\JsonFormatter;
use Monolog\Formatter\LineFormatter;
use Monolog\Formatter\LogstashFormatter;
use Monolog\Handler\RedisHandler;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Logger as Monologger;
use Monolog\LogRecord;
use Neo\Exception\ResourceNotFoundException;

/**
 * Class NeoLog
 *
 * @method static void debug(string $type, string $message, $context)
 * @method static void info(string $type, string $message, $context)
 * @method static void notice(string $type, string $message, $context)
 * @method static void warning(string $type, string $message, $context)
 * @method static void error(string $type, string $message, $context)
 * @method static void crit(string $type, string $message, $context)
 * @method static void alert(string $type, string $message, $context)
 * @method static void emerg(string $type, string $message, $context)
 */
class NeoLog
{
    /**
     * @var array
     */
    private $fileHandler;

    /**
     * @var StreamHandler
     */
    private $streamHandler;

    /**
     * @var RedisHandler
     */
    private $redisHandler;

    /**
     * @var string
     */
    private $logid;

    /**
     * @var NeoLog
     */
    private static $instance;

    private function __construct()
    {
    }

    /**
     * 静态调用
     *
     * DEBUG = 100;
     * INFO = 200;
     * NOTICE = 250;
     * WARNING = 300;
     * ERROR = 400;
     * CRITICAL = 500;
     * ALERT = 550;
     * EMERGENCY = 600;
     *
     * @param string $method
     * @param array  $arguments
     */
    public static function __callStatic(string $method, array $arguments)
    {
        if (in_array($method, [
            'debug',
            'info',
            'notice',
            'warn',
            'warning',
            'err',
            'error',
            'crit',
            'critical',
            'alert',
            'emerg',
            'emergency',
            'setLogId',
        ])) {
            if (static::$instance == null || ! static::$instance instanceof NeoLog) {
                static::$instance = new static();
            }

            if ($method === 'setLogId') {
                static::$instance->setLogId($arguments[0]);
            } else {
                array_unshift($arguments, $method);
                static::$instance->logit(...$arguments);
            }
        } else {
            throw new ResourceNotFoundException('Function (NeoLog::' . $method . ') is not exist.');
        }
    }

    /**
     * 统一日志记录入口
     *
     * @param string $action
     * @param string $type
     * @param string $message
     * @param mixed  $context
     */
    private function logit(string $action, string $type, string $message, $context = null)
    {
        try {
            $type || $type = 'neo';

            $logger = $this->log($type);
            if ($logger == null) {
                return;
            }

            if ($context instanceof \Throwable) {
                $context = Debug::simplifyException($context);
            }

            if (! is_array($context)) {
                $context = (array) $context;
            }

            // 日志ID
            $context['logid'] = $this->getLogId();

            // 按文件分隔日志时的文件名
            $context['type'] = $type;

            // 获取日志记录在文件中的位置
            if (! isset($context['traces'])) {
                $context['traces'] = Debug::getTracesAsString(NeoLogUtility::getTraces());
            }

            $logger->{$action}($message, $context);
        } catch (\Exception $ex) {
            $args = Debug::simplifyException($ex);
            $args['log'] = [
                'action' => $action,
                'type' => $type,
                'message' => $message,
                'context' => $context,
            ];

            $msg = NeoLogUtility::formatDate() . "\t" . $this->getLogId() . "\t" . json_encode(
                $args,
                JSON_UNESCAPED_UNICODE
            ) . PHP_EOL . PHP_EOL;

            if (in_array('file', NeoLogUtility::getLogTypes())) {
                error_log($msg, 3, NeoLogUtility::getFileLogDir() . DIRECTORY_SEPARATOR . 'neologerror.log');
            } else {
                error_log($msg);
            }

            unset($args, $msg);
        }
    }

    /**
     * 日志
     *
     * @param string $type
     *
     * @throws \Exception
     * @return null|Monologger
     */
    private function log(string $type = '')
    {
        $handlers = [];
        $logTypes = NeoLogUtility::getLogTypes();

        // 写到文件
        if (in_array('file', $logTypes)) {
            $fileHandler = $this->log2File($type);
            if ($fileHandler) {
                $handlers[] = $fileHandler;
            }
        }

        // 写到Redis
        if (in_array('redis', $logTypes)) {
            $redisHandler = $this->log2RedisWithLogstash();
            if ($redisHandler) {
                $handlers[] = $redisHandler;
            }
        }

        // 写到php://stderr
        if (in_array('stderr', $logTypes)) {
            $streamHandler = $this->log2Stream();
            if ($streamHandler) {
                $handlers[] = $streamHandler;
            }
        }

        if ($handlers) {
            $logger = new Monologger('neolog', $handlers, [], getDatetimeZone());
        } else {
            $logger = null;
        }

        return $logger;
    }

    /**
     * 创建一个Redis日志处理器
     *
     * @param string $rediskey Redis key
     *
     * @return \Monolog\Handler\AbstractHandler
     */
    private function log2RedisWithLogstash(string $rediskey = 'neologstash')
    {
        if ($this->redisHandler) {
            return $this->redisHandler;
        }

        $logRedis = Neo::initRedis('logstash');
        if (! $logRedis || $logRedis->isDown()) {
            return null;
        }

        $redisHandler = new RedisHandler($logRedis->getPhpRedis(), $rediskey, NeoLogUtility::getLogLevel());
        $formatter = new LogstashFormatter('', $rediskey);
        $redisHandler->setFormatter($formatter);
        $redisHandler->pushProcessor(new NeoLogRedisProcessor());

        $this->redisHandler = $redisHandler;

        return $redisHandler;
    }

    /**
     * 创建一个文件日志处理器
     *
     * @param string $type 文件名
     *
     * @return \Monolog\Handler\AbstractHandler
     */
    private function log2File(string $type)
    {
        $fileCfg = Config::get('logger', 'file', [
            'pertype' => true, // 是否每个$type一个日志文件
            'typename' => 'neo', // 如果pertype==false，可以指定日志文件名称，默认为neo
            'formatter' => 'json', // 文件内容格式，默认为json，可选：line, json
        ]);

        // 是否只输出到一个日志文件，PERTYPE：每个type一个日志文件
        if (empty($fileCfg['pertype'])) {
            // 项目指定文件日志的文件名，
            $type = $fileCfg['typename'] ?? 'neo';
        }

        if ($this->fileHandler[$type]) {
            return $this->fileHandler[$type];
        }

        $fmt = $fileCfg['formatter'] ?? 'json';

        $stream = new NeoLogRotatingFileHandler(
            NeoLogUtility::getFileLogDir() . '/' . $type . '.log',
            10,
            NeoLogUtility::getLogLevel(),
            true,
            0664
        );

        $stream->setFormatter($this->logFormatter($fmt));
        $stream->pushProcessor(new NeoLogFileProcessor());

        $this->fileHandler[$type] = $stream;

        return $stream;
    }

    /**
     * 创建一个流日志处理器
     *
     * @param string $type php://stderr
     *
     * @throws \Exception
     * @return \Monolog\Handler\AbstractHandler
     */
    private function log2Stream(string $type = 'stderr')
    {
        if ($this->streamHandler) {
            return $this->streamHandler;
        }

        $stream = new StreamHandler('php://' . $type, NeoLogUtility::getLogLevel());
        $stream->setFormatter($this->logFormatter('stderr'));
        $stream->pushProcessor(new NeoLogStreamProcessor());

        $this->streamHandler = $stream;

        return $stream;
    }

    /**
     * 文本日志格式
     *
     * @param string $fmt
     *
     * @return JsonFormatter|LineFormatter
     */
    private function logFormatter(string $fmt = 'line')
    {
        switch ($fmt) {
            case 'stderr':
                $simple_format = '[%extra.logtime%] %channel%.%level_name% %context.logid% %message% %context% %extra% %extra.line%' . PHP_EOL;
                $formatter = new LineFormatter($simple_format);
                break;
            case 'line':
                $simple_format = '[%extra.logtime%] %channel%.%level_name% %context.logid% %message% %context% %extra% %extra.line%' . PHP_EOL;
                $formatter = new LineFormatter($simple_format);
                break;
            case 'json':
            default:
                $formatter = new JsonFormatter();
                break;
        }

        return $formatter;
    }

    /**
     * 设置logid
     *
     * @param $logid
     */
    private function setLogId(string $logid)
    {
        $this->logid = $logid;
    }

    /**
     * 生成日志ID
     *
     * @return string
     */
    public function getLogId()
    {
        if ($this->logid) {
            return $this->logid;
        }

        if ($id = Config::get('logger', 'id')) {
            $this->logid = $id;
        } else {
            $this->logid = sha1(uniqid(
                '',
                true
            ) . str_shuffle(str_repeat(
                '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ',
                16
            )));
        }

        return $this->logid;
    }
}

/**
 * Class NeoLogUtility
 */
class NeoLogUtility
{
    private static $line = '';

    public static function getLine()
    {
        return static::$line;
    }

    /**
     * 获取日志调用路径
     *
     * @return array
     */
    public static function getTraces()
    {
        $traces = Debug::getTraces();

        // 调用入口
        static::$line = Debug::traceToString($traces[count($traces)-1]);

        return $traces;
    }

    /**
     * 格式化当前时间
     *
     * @param string $format
     *
     * @return false|string
     */
    public static function formatDate(string $format = 'Y-m-d H:i:s.U')
    {
        return formatDate($format);
    }

    /**
     * 获取文件日志目录
     *
     * @return string
     */
    public static function getFileLogDir()
    {
        return neo()['log_dir'];
    }

    /**
     * 获取日志类型，目前支持：文件、Redis和stderr，可以同时支持多个类型
     *
     * @return []
     */
    public static function getLogTypes()
    {
        return Config::get('logger', 'types', ['stderr']);
    }

    /**
     * 文件日志级别
     *
     * @return int
     */
    public static function getLogLevel()
    {
        // DEBUG = 100;
        // INFO = 200;
        // NOTICE = 250;
        // WARNING = 300;
        // ERROR = 400;
        // CRITICAL = 500;
        // ALERT = 550;
        // EMERGENCY = 600;
        return (int) Config::get('logger', 'level', 300);
    }
}

/**
 * Class NeoLogProcessor
 */
class NeoLogProcessor
{
    /**
     * 添加更多内容
     *
     * @param LogRecord $record
     *
     * @return LogRecord
     */
    public function more(LogRecord $record)
    {
        $user = neo()->getUser();
        $record->extra = [
            'userid' => (int) ($user['userid'] ?? 0),
            'username' => (string) ($user['username'] ?? ''),
            'host' => Utility::gethostname(),
            'logtime' => NeoLogUtility::formatDate(),
            'line' => NeoLogUtility::getLine(),
        ];

        return $record;
    }
}
/**
 * Class NeoLogStreamProcessor
 */
class NeoLogStreamProcessor extends NeoLogProcessor
{
    /**
     * @param LogRecord $record
     *
     * @return array
     */
    public function __invoke(LogRecord $record)
    {
        return $this->more($record);
    }
}

/**
 * Class NeoLogFileProcessor
 */
class NeoLogFileProcessor extends NeoLogProcessor
{
    /**
     * @param LogRecord $record
     *
     * @return array
     */
    public function __invoke(LogRecord $record)
    {
        return $this->more($record);
    }
}

/**
 * Class NeoLogFileProcessor
 */
class NeoLogRedisProcessor extends NeoLogProcessor
{
    /**
     * @param LogRecord $record
     *
     * @return array
     */
    public function __invoke(LogRecord $record)
    {
        return $this->more($record);
    }
}

/**
 * RotatingFileHandler 不会rotate文件，故重写
 *
 * Class NeoLogRotatingFileHandler
 */
class NeoLogRotatingFileHandler extends RotatingFileHandler
{
    /**
     * {@inheritdoc}
     */
    protected function write(LogRecord $record): void
    {
        // on the first record written, if the log is new, we should rotate (once per day)
        if ($this->mustRotate === null) {
            $this->mustRotate = ! file_exists($this->url);
        }

        if ($this->mustRotate === true) {
            $this->rotate();
        }

        parent::write($record);
    }
}
