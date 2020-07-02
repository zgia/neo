<?php

namespace Neo;

use Neo\Exception\NeoException;

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
            $request['http_request'] = (string) neo()->getRequest();

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
         * @var NeoException $ex
         */
        if (method_exists($ex, 'getMore') && $more = $ex->getMore()) {
            $data['more'] = $more;
        }

        return $data;
    }
}
