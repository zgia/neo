<?php

use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Neo\Database\MySQL;
use Neo\Database\MySQLExplain;
use Neo\Database\NeoDatabase;
use Neo\Debug;
use Neo\Html\Page;
use Neo\Http\Request;
use Neo\I18n;
use Neo\NeoFrame;
use Neo\Utility;

// 时区
if (! defined('DATETIME_ZONE')) {
    define('DATETIME_ZONE', null);
}

// 时区偏移
if (! defined('TIMEZONE_OFFSET')) {
    define('TIMEZONE_OFFSET', null);
}

/**
 * 返回NeoFrame实例
 *
 * @return NeoFrame
 */
function neo()
{
    return NeoFrame::getInstance();
}

/**
 * 返回数据库（MySQL）实例
 *
 * @param bool $init 如果没有链接，则初始化
 *
 * @return MySQL|NeoDatabase
 */
function db(bool $init = true)
{
    return neo()->getDB($init);
}

/**
 * 格式化 Request Parameters
 *
 * @param string $gpc
 * @param array  $variables
 *
 * @return array
 */
function input(string $gpc, array $variables)
{
    $request = neo()->getRequest();

    $params = $request->cleanGPC($gpc, $variables);

    return $request->setParams($params);
}

/**
 * 格式化 Request Parameters
 *
 * @param string $gpc
 * @param string $varname
 * @param int    $vartype
 *
 * @return mixed
 */
function inputOne(string $gpc, string $varname, int $vartype = INPUT_TYPE_NOCLEAN)
{
    $input = input($gpc, [$varname => $vartype]);

    return $input[$varname];
}

/**
 * 格式化一个数组
 *
 * @param array $source
 * @param array $variables
 *
 * @return array
 */
function inputArray(array $source, array $variables)
{
    return Request::cleanArray($source, $variables);
}

/**
 * 获取当前时间
 *
 * @param bool $inMemory
 *
 * @return int
 */
function timenow(bool $inMemory = false)
{
    return $inMemory ? time() : TIMENOW;
}

/**
 * 获取某个处理需要的时间
 *
 * @param float $start
 *
 * @return float
 */
function getExecutionTime(float $start = 0)
{
    $start || $start = neo()->getTimeStart();

    return microtime(true) - $start;
}

/**
 * 载入类
 *
 * @param string $class     类名
 * @param string $namespace 命名空间
 *
 * @return mixed
 */
function loadClass(string $class, string $namespace = null)
{
    static $_classes = [];

    // 类名
    $className = ucfirst($class);
    if (empty($namespace)) {
        $namespace = '';
    }
    $classK = $namespace . '\\' . $className;

    // 如果类已实例化
    if (isset($_classes[$classK])) {
        return $_classes[$classK];
    }

    $_classes[$classK] = new $classK();

    return $_classes[$classK];
}

/**
 * 多语言翻译
 *
 * @param string $text
 *
 * @return string
 */
function translate(string $text)
{
    return __($text);
}

/**
 * 返回翻译后的短语
 *
 * @param string $text 待翻译的短语
 *
 * @return string 已经翻译的短语
 */
function __(string $text)
{
    return I18n::__($text);
}

/**
 * 显示翻译后的短语
 *
 * @param string $text 待翻译的短语
 */
function _e(string $text)
{
    echo __($text);
}

/**
 * 格式化输出短语
 */
function _f()
{
    echo __f(...func_get_args());
}

/**
 * 返回格式化后的已经翻译的短语
 *
 * @return string 已经翻译的短语
 */
function __f()
{
    $args = func_get_args();

    if (empty($args)) {
        return '';
    }
    if (count($args) == 1) {
        return __($args[0]);
    }

    $str = $args[0];
    unset($args[0]);

    return vsprintf(__($str), $args);
}

/**
 * 获取基于某个时区的Carbon对象
 *
 * @param null|string $tz
 * @param null|bool   $immutable
 *
 * @return Carbon|CarbonImmutable
 */
function carbon(?string $tz = null, ?bool $immutable = false)
{
    $tz || $tz = getDatetimeZone();

    return $immutable ? CarbonImmutable::now($tz) : Carbon::now($tz);
}

/**
 * 获取时区
 *
 * @return string
 */
function getDatetimeZone()
{
    if (defined('DATETIME_ZONE') && ! is_null(DATETIME_ZONE)) {
        return DATETIME_ZONE;
    }

    return date_default_timezone_get() ?: 'UTC';
}

/**
 * 获取时区的偏移，单位：秒
 *
 * @return int
 */
function getTimezoneOffset()
{
    if (defined('TIMEZONE_OFFSET') && ! is_null(TIMEZONE_OFFSET)) {
        return TIMEZONE_OFFSET;
    }

    return timezone_offset_get(timezone_open(getDatetimeZone()), date_create());
}

/**
 * 将一个时间串转换为UTC时间的UNIX时间戳
 *
 * @param string $str  时间串
 * @param int    $time 时间戳
 *
 * @return int
 */
function stringToUtcTime(string $str, int $time = 0)
{
    $time || $time = time();
    $t = strtotime($str, $time);

    return $t ? $t - getTimezoneOffset() : 0;
}

/**
 * 按照预订格式显示时间
 *
 * @param string $format    格式
 * @param int    $timestamp 时间
 * @param int    $yestoday  时间显示模式：0：标准的年月日模式，1：今天/昨天模式，2：1分钟，1小时，1天等更具体的模式
 *
 * @return string 格式化后的时间串
 */
function formatDate(string $format = 'Ymd', int $timestamp = 0, int $yestoday = 0)
{
    $carbon = carbon();

    if ($timestamp) {
        $microsecond = $carbon->microsecond;
        $carbon->setTimestamp($timestamp)->setMicrosecond($microsecond);
    } else {
        $timestamp = $carbon->timestamp;
    }

    $timenow = time();

    if ($yestoday == 0) {
        $returndate = $carbon->format($format);
    } elseif ($yestoday == 1) {
        if (date('Y-m-d', $timestamp) == date('Y-m-d', $timenow)) {
            $returndate = __('Today');
        } elseif (date('Y-m-d', $timestamp) == date('Y-m-d', $timenow - 86400)) {
            $returndate = __('Yesterday');
        } else {
            $returndate = $carbon->format($format);
        }
    } else {
        $timediff = $timenow - $timestamp;

        if ($timediff < 0) {
            $returndate = $carbon->format($format);
        } elseif ($timediff < 60) {
            $returndate = __('1 minute before');
        } elseif ($timediff < 3600) {
            $returndate = sprintf(__('%d minutes before'), intval($timediff / 60));
        } elseif ($timediff < 7200) {
            $returndate = __('1 hour before');
        } elseif ($timediff < 86400) {
            $returndate = sprintf(__('%d hours before'), intval($timediff / 3600));
        } elseif ($timediff < 172800) {
            $returndate = __('1 day before');
        } elseif ($timediff < 604800) {
            $returndate = sprintf(__('%d days before'), intval($timediff / 86400));
        } elseif ($timediff < 1209600) {
            $returndate = __('1 week before');
        } elseif ($timediff < 3024000) {
            $returndate = sprintf(__('%d weeks before'), intval($timediff / 604900));
        } elseif ($timediff < 15552000) {
            $returndate = sprintf(__('%d months before'), intval($timediff / 2592000));
        } else {
            $returndate = $carbon->format($format);
        }
    }

    return $returndate;
}

/**
 * 格式化时间，长类型：Y-m-d H:i:s
 *
 * @param int $timestamp 时间
 *
 * @return string
 */
function formatLongDate(int $timestamp = 0)
{
    return formatDate('Y-m-d H:i:s', $timestamp);
}

/**
 * 检查浏览器类型
 *
 * @param string $browserName 浏览器名称: chrome, safari, ie...
 *
 * @return string 浏览器名或者null
 */
function isBrowser(string $browserName = '')
{
    static $browser;

    if (! $browser) {
        // 忽略大小写
        $ua = ' ' . strtolower($_SERVER['HTTP_USER_AGENT']);

        // Humans / Regular Users
        if (strpos($ua, 'opera') || strpos($ua, 'opr/')) {
            $browser = 'opera';
        } elseif (strpos($ua, 'edge')) {
            $browser = 'edge';
        } elseif (strpos($ua, 'chrome')) {
            $browser = 'chrome';
        } elseif (strpos($ua, 'safari')) {
            $browser = 'safari';
        } elseif (strpos($ua, 'firefox')) {
            $browser = 'firefox';
        } elseif (strpos($ua, 'msie') || strpos($ua, 'trident/7')) {
            $browser = 'ie';
        } // Search Engines
        elseif (strpos($ua, 'google')) {
            $browser = 'bot_google';
        } elseif (strpos($ua, 'bing')) {
            $browser = 'bot_bing';
        } elseif (strpos($ua, 'slurp')) {
            $browser = 'bot_yahoo';
        } elseif (strpos($ua, 'duckduckgo')) {
            $browser = 'bot_duckduck';
        } elseif (strpos($ua, 'baidu')) {
            $browser = 'bot_baidu';
        } elseif (strpos($ua, 'yandex')) {
            $browser = 'bot_yandex';
        } elseif (strpos($ua, 'sogou')) {
            $browser = 'bot_sogou';
        } elseif (strpos($ua, 'exabot')) {
            $browser = 'bot_exabot';
        } elseif (strpos($ua, 'msn')) {
            $browser = 'bot_msn';
        } // Common Tools and Bots
        elseif (strpos($ua, 'mj12bot')) {
            $browser = 'bot_majestic';
        } elseif (strpos($ua, 'ahrefs')) {
            $browser = 'bot_ahrefs';
        } elseif (strpos($ua, 'semrush')) {
            $browser = 'bot_semrush';
        } elseif (strpos($ua, 'rogerbot') || strpos($ua, 'dotbot')) {
            $browser = 'bot_roger_dot';
        } elseif (strpos($ua, 'frog') || strpos($ua, 'screaming')) {
            $browser = 'bot_screaming_frog';
        } // Miscellaneous
        elseif (strpos($ua, 'facebook')) {
            $browser = 'bot_facebook';
        } elseif (strpos($ua, 'pinterest')) {
            $browser = 'bot_pinterest';
        } // Check for strings commonly used in bot user agents
        elseif (strpos($ua, 'crawler') || strpos($ua, 'api') || strpos($ua, 'spider') || strpos(
            $ua,
            'http'
        ) || strpos($ua, 'bot') || strpos($ua, 'archive') || strpos($ua, 'info') || strpos($ua, 'data')) {
            $browser = 'bot_other';
        }

        $browser = 'unknown';
    }

    if ($browserName) {
        return strtolower($browserName) == $browser ? $browser : null;
    }

    return $browser;
}

/**
 * 将输出内容数组转为JSON格式输出显示
 *
 * @param string $msg        消息
 * @param int    $code       错误码
 * @param int    $statusCode Http状态码
 */
function printErrorJSON(string $msg, int $code = 1, int $statusCode = 200)
{
    $jsonarray = [
        'code' => $code,
        'msg' => $msg,
    ];

    printOutJSON($jsonarray, $statusCode);
}

/**
 * 将输出内容数组转为JSON格式输出显示
 *
 * @param string $msg        消息
 * @param int    $code       错误码
 * @param int    $statusCode Http状态码
 */
function printSuccessJSON(string $msg, int $code = 0, int $statusCode = 200)
{
    $jsonarray = [
        'code' => $code,
        'msg' => $msg,
    ];

    printOutJSON($jsonarray, $statusCode);
}

/**
 * 将输出内容数组转为JSON格式输出显示
 *
 * @param array $jsonarray  输出数据
 * @param int   $statusCode Http状态码
 */
function printOutJSON(array $jsonarray, int $statusCode = 200)
{
    if (! isset($jsonarray['data'])) {
        $jsonarray['data'] = [];
    }

    neo()->getRequest()->setRequestFormat('json');

    byebye($statusCode, $jsonarray);
}

/**
 * 显示提示信息
 *
 * @param string $message 显示的信息
 * @param string $url     页面跳转地址
 * @param bool   $back    是否显示后退链接
 */
function displayMessage(string $message, string $url = '', $back = false)
{
    if (Utility::isAjax()) {
        printSuccessJSON($message);
    } else {
        Page::redirect($url, $message, __('Information'), 0, false, $back);
    }
}

/**
 * 跳转时显示错误信息。如果指定URL，则显示错误信息后，自动跳转到这个URL。
 * 否则，停留在错误信息页面，等待用户后退。
 *
 * @param string $message 跳转时，显示的信息
 * @param string $url     页面跳转地址
 * @param bool   $back    是否显示后退链接
 */
function displayError(string $message, string $url = '', bool $back = true)
{
    if (Utility::isAjax()) {
        printErrorJSON($message);
    } else {
        Page::redirect($url, $message, __('Error'), $url ? 2 : 0, true, $back);
    }
}

/**
 * 页面结束处理
 *
 * @param int          $statusCode Http状态码
 * @param array|string $content    输出内容
 */
function byebye(?int $statusCode = null, $content = null)
{
    Debug::logHttpContent($content);

    // 只有调试模式下，非ajax调用页面输出
    if (! Utility::isAjax() && neo()->getExplainSQL()) {
        MySQLExplain::display();
    }

    neo()->getResponse()->sendData($statusCode, is_array($content) ? json_encode($content) : $content);

    unload();
}

/**
 * 卸载系统
 */
function unload()
{
    neo()->unload();

    exit();
}

/**
 * 获取用户自己定义的变量，并加到$neo->templateVars中，用于输出到模板
 * 超全局，全局和一些配置变量将被忽略
 *
 * @param array $vars    来自方法：get_defined_vars()
 * @param array $filters 其他需要忽略的变量
 */
function getUserDefinedVars(array $vars, array $filters = [])
{
    $filters[] = 'neo';
    $filters[] = 'db';

    $left = [];
    foreach ($vars as $key => $var) {
        if (! in_array($key, $filters) && $key[0] != '_' && ! preg_match('/(Controller|Model|Helper|Service)$/i', $key)) {
            $left[$key] = $var;
        }
    }

    neo()->getTemplate()->setVars($left);
}

/**
 * 从文件名中移除系统路径
 *
 * @param null|string $filename
 *
 * @return string
 */
function removeSysPath(?string $filename = null)
{
    if ($filename) {
        return str_ireplace(neo()->getAbsPath(), '', $filename);
    }

    return $filename;
}
/**
 * 打印变量
 *
 * @param mixed ...$args
 */
function d(...$args)
{
    if (empty($args)) {
        return;
    }

    $addtrace = false;
    if ($args[count($args) - 1] == '__add_trace__') {
        $addtrace = true;
        array_pop($args);
    }

    $lines = [];
    if ($addtrace) {
        $calledFrom = Debug::getTraces();
        $lines = Debug::getTracesAsString(array_slice($calledFrom, 2));
    }

    $hr = PHP_EOL . '-------------------------' . PHP_EOL;

    $errors = 'Time: ' . formatLongDate() . $hr;
    foreach ($args as $arg) {
        $errors .= print_r($arg, true) . PHP_EOL;
    }

    if (Utility::isCli() || Utility::isAjax()) {
        echo $errors, $hr, implode(PHP_EOL, $lines);
    } else {
        static $class = null;

        if ($class == null) {
            $class = '<style>pre{display:block;padding:10px;margin:20px;font-size:13px;line-height:1.5;color:#333;word-break:break-all;word-wrap:break-word;background-color:#f5f5f5;border:1px solid #ccc;border-radius:4px;overflow:auto;}code{padding:1px 4px;font-size:90%;color:#c7254e;background-color:#f9f2f4;border-radius:4px;border:1px solid #e1e1e1;-webkit-box-shadow: 0 1px 4px rgba(0, 0, 0, 0.1);-moz-box-shadow: 0 1px 4px rgba(0, 0, 0, 0.1);box-shadow: 0 1px 4px rgba(0, 0, 0, 0.1);}</style>';
            echo $class;
        }

        echo '<pre>', $errors, '</pre>';

        if ($addtrace) {
            echo '<pre><ol><li>', implode('</li><li>', $lines), '</li></ol></pre>';
        }
    }
}

/**
 * 打印变量，并退出
 *
 * @param mixed ...$args
 */
function x(...$args)
{
    $args[] = '__add_trace__';

    d(...$args);

    unload();
}
