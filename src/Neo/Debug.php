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
     * @param null|array|string $request
     * @param null|array|string $response
     */
    public static function logHttpContent($request = null, $response = null)
    {
        $neo = neo();

        if ($neo['log_http_request']) {
            if (! is_array($request)) {
                $request = (array) $request;
            }
            $request['db_slave'] = $neo->getDB()->getSlaveIndex();
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
     * @return array
     */
    public static function getTraces(int $index = 0, int $limit = 0)
    {
        $backtraces = debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT, $limit);

        foreach ($backtraces as &$trace) {
            if (is_array($trace['args'])) {
                foreach ($trace['args'] as &$arg) {
                    if (is_object($arg)) {
                        $arg = 'Object(' . get_class($arg) . ')';
                    } elseif (is_array($arg)) {
                        $arg = 'Array';
                    } elseif (is_string($arg)) {
                        $arg = "'" . addslashes($arg) . "'";
                    }
                }
            } else {
                $trace['args'] = [];
            }
        }

        return $index ? $backtraces[$index] : $backtraces;
    }

    /**
     * 以 Throwable::getTraceAsString() 的方式格式化将某个断点堆栈
     *
     * @param array $traces
     *
     * @return array
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
     * @param array $trace
     *
     * @return string
     */
    public static function traceToString(array $trace)
    {
        $func = $trace['class'] . $trace['type'] . $trace['function'];

        return removeSysPath("{$trace['file']}({$trace['line']}): {$func}(" . implode(
            ', ',
            $trace['args']
        ) . ')');
    }

    /**
     * @param \Throwable $ex
     *
     * @return array
     */
    public static function simplifyException(\Throwable $ex)
    {
        if (empty($ex)) {
            return [];
        }

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
     * @param mixed ...$args
     */
    public static function dump(...$args)
    {
        if (empty($args)) {
            return;
        }

        $addtrace = false;
        if ($args[0] == '__add_trace__') {
            array_shift($args);
            $addtrace = true;
        }

        $lines = [];
        if ($addtrace) {
            $calledFrom = static::getTraces();
            $lines = static::getTracesAsString(array_slice($calledFrom, 2));
        }

        $hr = PHP_EOL . '-------------------------' . PHP_EOL;

        // 详细的变量信息
        $var_dump = false;
        if ($args[0] == 'var_dump') {
            array_shift($args);
            $var_dump = true;
        }

        $errors = 'Time: ' . formatLongDate() . $hr;
        foreach ($args as $arg) {
            $errors .= '|==> ' . ($var_dump ? var_dump($arg) : print_r($arg, true)) . PHP_EOL;
        }

        if (neo()->getRequest()->isAjax()) {
            printOutJSON([
                'code' => 1,
                'msg' => $errors,
                'data' => $lines,
            ]);
        } elseif (Utility::isCli()) {
            echo $errors, $hr, implode(PHP_EOL, $lines);
        } else {
            static $class = null;

            if ($class == null) {
                $class = '<style>pre{display:block;padding:10px;margin:20px;font-size:13px;line-height:1.5;color:#333;word-break:break-all;word-wrap:break-word;background-color:#f5f5f5;border:1px solid #ccc;border-radius:4px;overflow:auto;}code{padding:1px 4px;font-size:90%;color:#c7254e;background-color:#f9f2f4;border-radius:4px;border:1px solid #e1e1e1;-webkit-box-shadow:0 1px 4px rgba(0, 0, 0, 0.1);-moz-box-shadow:0 1px 4px rgba(0, 0, 0, 0.1);box-shadow:0 1px 4px rgba(0, 0, 0, 0.1);}</style>';
                echo $class;
            }

            echo '<pre>', $errors, '</pre>';

            if ($addtrace) {
                echo '<pre><ol><li>', implode('</li><li>', $lines), '</li></ol></pre>';
            }
        }
    }
}
