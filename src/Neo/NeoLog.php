<?php

namespace Neo;

use Monolog\Formatter\JsonFormatter;
use Monolog\Formatter\LineFormatter;
use Monolog\Formatter\LogstashFormatter;
use Monolog\Handler\RedisHandler;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Logger as Monologger;
use Neo\Exception\NeoException;

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
    private static $instance = null;

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
            throw new NeoException('Function (NeoLog::' . $method . ') is not exist.');
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
            $context['type'] = $type ?: 'neo';

            // 获取日志记录在文件中的位置
            if (! isset($context['traces'])) {
                $context['traces'] = Debug::getTracesAsString(NeoLogUtility::getTraces());
            }

            $logger->{$action}($message, $context);
        } catch (\Exception $ex) {
            $args = Debug::simplifyException($ex);

            $args['action'] = $action;
            $args['type'] = $type;
            $args['message'] = $message;
            $args['context'] = $context;

            $msg = NeoLogUtility::formatDate() . "\t" . $this->getLogId() . "\t" . json_encode(
                $args,
                JSON_UNESCAPED_UNICODE
            ) . PHP_EOL . PHP_EOL;

            error_log($msg, 3, NeoLogUtility::getFileLogDir() . DIRECTORY_SEPARATOR . 'neologerror.log');

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
        $type || $type = 'neo';

        $handlers = [];

        // 写到文件
        if (defined('NEO_LOG_FILE') && NEO_LOG_FILE) {
            $fileHandler = $this->log2File($type);
            if ($fileHandler) {
                $handlers[] = $fileHandler;
            }
        }

        // 写到Redis
        if (defined('NEO_LOG_REDIS') && NEO_LOG_REDIS) {
            $redisHandler = $this->log2RedisWithLogstash();
            if ($redisHandler) {
                $handlers[] = $redisHandler;
            }
        }

        // 写到php://stderr
        if (defined('NEO_LOG_STDERR') && NEO_LOG_STDERR) {
            $streamHandler = $this->log2Stream();
            if ($streamHandler) {
                $handlers[] = $streamHandler;
            }
        }

        if ($handlers) {
            $logger = new Monologger('neolog', $handlers);
            Monologger::setTimezone(NeoLogUtility::dateTimeZone());
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
        if (! (defined('NEO_REDIS') && NEO_REDIS)) {
            return null;
        }

        if ($this->redisHandler) {
            return $this->redisHandler;
        }

        $logRedis = NeoFrame::initRedis('logstash');
        if (! $logRedis || $logRedis->isWentAway()) {
            return null;
        }

        $redisHandler = new RedisHandler($logRedis->getPhpRedis(), $rediskey, NeoLogUtility::getLogLevel());
        $formatter = new NeoLogRedisLogstashFormatter('', $rediskey, null, '');
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
        // 是否只输出到一个日志文件，PERTYPE：每个type一个日志文件
        if (! (defined('NEO_LOG_FILE_PERTYPE') && NEO_LOG_FILE_PERTYPE)) {
            // 项目指定文件日志的文件名
            $type = defined('NEO_LOG_FILE_TYPENAME') && NEO_LOG_FILE_TYPENAME ? NEO_LOG_FILE_TYPENAME : 'neo';
        }

        if ($this->fileHandler[$type]) {
            return $this->fileHandler[$type];
        }

        $fmt = defined('NEO_LOG_FILE_FORMATTER') && NEO_LOG_FILE_FORMATTER ? NEO_LOG_FILE_FORMATTER : 'json';

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

        $SIMPLE_FORMAT = '[%logtime%] %channel%.%level_name% %logid% %message% %context% %extra% %line%' . PHP_EOL;
        $stream->setFormatter(new LineFormatter($SIMPLE_FORMAT));

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
            case 'line':
                $SIMPLE_FORMAT = '[%logtime%] %channel%.%level_name% %logid% %message% %context% %extra% %line%' . PHP_EOL;
                $formatter = new LineFormatter($SIMPLE_FORMAT);
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

        if (defined('NEO_LOG_ID') && NEO_LOG_ID) {
            $this->logid = NEO_LOG_ID;
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
    /**
     * 获取日志调用路径
     *
     * @return array
     */
    public static function getTraces()
    {
        $traces = Debug::getTraces();

        // 是否使用路由
        $routing = false;
        foreach ($traces as $trace) {
            if (stripos($trace['file'], 'Route.php') !== false) {
                $routing = true;
                break;
            }
        }

        // 4 表示移除NeoLog中的调用路径
        // 8 表示移除初始化到加载控制器的通用加载路径(4步)，加上NeoLog调用路径(4步)
        // 没有通过路由的，则不移除
        return array_slice($traces, 4, $routing ? count($traces) - 8 : null);
    }

    /**
     * 时区
     *
     * @return \DateTimeZone
     */
    public static function dateTimeZone()
    {
        return new \DateTimeZone(getDatetimeZone());
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
     * 文件日志级别
     *
     * @return int
     */
    public static function getLogLevel()
    {
        if (defined('NEO_LOG_LEVEL') && NEO_LOG_LEVEL) {
            return NEO_LOG_LEVEL;
        }

        // DEBUG = 100;
        // INFO = 200;
        // NOTICE = 250;
        // WARNING = 300;
        // ERROR = 400;
        // CRITICAL = 500;
        // ALERT = 550;
        // EMERGENCY = 600;
        return 100;
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
     * @param array $record
     *
     * @return array
     */
    public function more(array $record)
    {
        $record['logid'] = $record['context']['logid'];
        $record['logtime'] = NeoLogUtility::formatDate();
        $record['type'] = $record['context']['type'];

        $record['extra']['userid'] = (int) neo()->user['userid'];
        $record['extra']['username'] = (string) neo()->user['username'];
        $record['extra']['host'] = Utility::gethostname();
        $record['extra']['traces'] = $record['context']['traces'];

        unset($record['context']['traces'], $record['context']['type'], $record['context']['logid']);

        return $record;
    }
}

/**
 * Class NeoLogFileProcessor
 */
class NeoLogFileProcessor extends NeoLogProcessor
{
    /**
     * @param array $record
     *
     * @return array
     */
    public function __invoke(array $record)
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
     * @param array $record
     *
     * @return array
     */
    public function __invoke(array $record)
    {
        return $this->more($record);
    }
}

/**
 * Class NeoLogRedisLogstashFormatter
 */
class NeoLogRedisLogstashFormatter extends LogstashFormatter
{
    /**
     * @param array $record
     *
     * @return array
     */
    protected function formatV0(array $record)
    {
        $message = parent::formatV0($record);

        if (isset($record['logid'])) {
            $message['@logid'] = $record['logid'];
        }

        $message['@logtime'] = NeoLogUtility::formatDate();

        if (isset($record['line'])) {
            $message['@fileline'] = $record['line'];
        }

        return $message;
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
    protected function write(array $record)
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
