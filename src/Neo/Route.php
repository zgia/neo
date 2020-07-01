<?php

namespace Neo;

use FastRoute\Dispatcher;
use Neo\Exception\NeoException;

/**
 * 路由
 */
class Route
{
    /**
     * @var Dispatcher
     */
    private static $dispatcher;

    private function __construct()
    {
    }

    /**
     * 初始化
     *
     * @param callable $routeDefinitionCallback
     * @param bool     $cache
     * @param array    $options
     */
    public static function init(callable $routeDefinitionCallback, $cache = false, array $options = [])
    {
        if ($cache) {
            self::$dispatcher = \FastRoute\cachedDispatcher($routeDefinitionCallback, $options);
        } else {
            self::$dispatcher = \FastRoute\simpleDispatcher($routeDefinitionCallback, $options);
        }
    }

    /**
     * 加载路由
     *
     * @param string $controllerNameSpace
     */
    public static function dispatch(string $controllerNameSpace = '')
    {
        $parts = parse_url($_SERVER['REQUEST_URI']);
        $path = $parts['path'] ?? '';

        if ($path == '/favicon.ico') {
            return;
        }

        if (false !== $pos = strpos($path, '?')) {
            $path = substr($path, 0, $pos);
        }

        // 特殊处理
        ($path === '' || $path === '/' || $path === '/index.php') && $path = '/index';

        $path = rawurldecode(rtrim($path, '/'));

        $routeInfo = self::$dispatcher->dispatch($_SERVER['REQUEST_METHOD'], $path);

        switch ($routeInfo[0]) {
            case Dispatcher::NOT_FOUND:
                throw new NeoException(__f('(%s) Not Found', $path), 404);
                break;
            case Dispatcher::METHOD_NOT_ALLOWED:
                throw new NeoException(
                    __f('Method (%s) for (%s) Not Allowed', implode(', ', $routeInfo[1]), $path),
                    405
                );
                break;
            case Dispatcher::FOUND:
                if (is_array($routeInfo[1]) || is_string($routeInfo[1])) {
                    self::callFunc($routeInfo, $controllerNameSpace);
                } elseif (is_callable($routeInfo[1])) {
                    $routeInfo[1](...$routeInfo[2]);
                } else {
                    throw new NeoException(__f('(%s) Route Error', $path));
                }
                break;
        }
    }

    /**
     * 使用"类->方法"的方式加载控制器
     *
     * @param array  $routeInfo
     * @param string $controllerNameSpace
     */
    private static function callFunc(array $routeInfo, string $controllerNameSpace = '')
    {
        // 匹配的是数组, 格式：['controllerName', 'methodName']
        if (is_array($routeInfo[1])) {
            [$controllerName, $methodName] = $routeInfo[1];
        } else {
            // 字符串, 格式：controllerName@methodName
            [$controllerName, $methodName] = explode('@', $routeInfo[1]);
        }

        $className = $controllerNameSpace ? $controllerNameSpace . '\\' . $controllerName : $controllerName;

        $params = [];

        if (! empty($routeInfo[2]) && is_array($routeInfo[2])) {
            if ($methodName === '*') {
                if (isset($routeInfo[2]['function'])) {
                    $methodName = $routeInfo[2]['function'];
                    unset($routeInfo[2]['function']);
                }
            }

            if (! empty($routeInfo[2])) {
                $params = array_values($routeInfo[2]);
            }
        }

        if ($methodName === '*') {
            $methodName = 'method_not_allowed';
        }

        (new $className(NeoFrame::getInstance()))->{$methodName}(...$params);
    }
}
