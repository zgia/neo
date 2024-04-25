<?php

namespace Neo;

/**
 * 调试相关
 */
class Debug
{
    /**
     * 记录Http Request 和 Response
     *
     * @param null|array<string,mixed>|string $request
     * @param null|array<string,mixed>|string $response
     */
    public static function logHttpContent($request = null, $response = null): void
    {
        $neo = neo();

        if ($neo['log_http_request']) {
            if (! is_array($request)) {
                $request = (array) $request;
            }
            $request['db_replica'] = $neo->getDB()->getReplicaIndex();
            $request['execution_time'] = number_format(getExecutionTime(), 3);
            $request['http_request'] = (string) $neo->getRequest();

            NeoLog::info('http', 'request', $request);
        }

        if ($neo['log_http_response']) {
            NeoLog::info('http', 'response', $response);
        }
    }

    /**
     * 获取某个断点的堆栈
     *
     * @param int $index
     * @param int $limit
     *
     * @return array<mixed>
     */
    public static function getTraces(int $index = 0, int $limit = 0)
    {
        // debug_backtrace() - show all options
        // debug_backtrace(0) - exclude ["object"]
        // debug_backtrace(1) - same as debug_backtrace()
        // debug_backtrace(2) - exclude ["object"] AND ["args"]
        $backtraces = debug_backtrace(0, $limit);

        foreach ($backtraces as &$trace) {
            foreach ($trace['args'] as &$arg) {
                if (is_object($arg)) {
                    $arg = 'Object(' . get_class($arg) . ')';
                } elseif (is_array($arg)) {
                    $arg = 'Array';
                } elseif (is_string($arg)) {
                    $arg = "'" . addslashes($arg) . "'";
                }
            }
        }

        return $index ? $backtraces[$index] : $backtraces;
    }

    /**
     * 以 Throwable::getTraceAsString() 的方式格式化将某个断点堆栈
     *
     * @param array<array<string,mixed>> $traces
     *
     * @return array<string>
     */
    public static function getTracesAsString(array $traces)
    {
        $lines = [];
        foreach ($traces as $i => $trace) {
            $lines[] = "#{$i} " . static::traceToString($trace);
        }

        return $lines;
    }

    /**
     * 格式某条Trace
     *
     * @param array<string,mixed> $trace
     *
     * @return string
     */
    public static function traceToString(array $trace)
    {
        $func = ($trace['class'] ?? '') . ($trace['type'] ?? '') . $trace['function'];

        return removeSysPath("{$trace['file']}({$trace['line']}): {$func}(" . implode(
            ', ',
            $trace['args']
        ) . ')');
    }

    /**
     * 简介的异常信息
     *
     * @param \Throwable $ex
     *
     * @return array<string,mixed>
     */
    public static function simplifyException(\Throwable $ex)
    {
        $traces = explode("\n", removeSysPath($ex->getTraceAsString()));

        $data = [
            'message' => $ex->getMessage(),
            'code' => $ex->getCode(),
            'traces' => $traces,
        ];

        /**
         * @var \Neo\Exception\NeoException $ex
         */
        if (method_exists($ex, 'getMore') && $more = $ex->getMore()) {
            $data['more'] = $more;
        }

        return $data;
    }

    /**
     * 打印变量
     *
     * @param mixed $args
     */
    public static function dump(...$args): void
    {
        if (empty($args)) {
            return;
        }

        $traces = [];
        if ($args[0] == '__add_trace__') {
            array_shift($args);

            $traces = static::getTracesAsString(array_slice(static::getTraces(), 2));
        }

        // 异步请求不返回调用栈
        $neo = neo();
        if ($neo->getRequest()->isAjax()) {
            $neo->_dump = $args + $traces;

            return;
        }

        $hr = PHP_EOL . '-------------------------' . PHP_EOL;
        $infos = 'Time: ' . formatLongDate() . $hr;
        foreach ($args as $arg) {
            $infos .= '|==> ' . print_r($arg, true) . PHP_EOL;
        }

        if (Utility::isCli()) {
            echo $infos, $hr, implode(PHP_EOL, $traces);
        } else {
            static $class = null;

            if ($class == null) {
                $class = '<style>pre.dump{display:block;padding:10px;margin:20px;font-size:12px;line-height:1.5;color:#333;word-break:break-all;word-wrap:break-word;background-color:#f5f5f5;border:1px solid #ccc;border-radius:4px;overflow:auto;}</style>';
                echo $class;
            }

            echo '<pre class="dump">', $infos, '</pre>';

            if ($traces) {
                echo '<pre class="dump"><ol><li>', implode('</li><li>', $traces), '</li></ol></pre>';
            }
        }
    }
}
