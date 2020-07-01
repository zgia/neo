<?php

use Neo\Database\MySQLExplain;
use Neo\Debug;
use Neo\Html\Page;
use Neo\Html\Template;
use Neo\Http\Request as NeoRequest;
use Neo\Http\Response as NeoResponse;
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
 * @return \Neo\Database\MySQLi|\Neo\Database\NeoDatabase|\Neo\Database\PdoMySQL
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
    $neo = neo();

    $params = NeoRequest::cleanGPC($gpc, $variables);
    $neo->input = array_merge($neo->input, $params);

    return $neo->input;
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
function inputOne(string $gpc, string $varname, int $vartype = TYPE_NOCLEAN)
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
    return NeoRequest::cleanArray($source, $variables);
}

/**
 * @param string $template
 *
 * @return string
 */
function loadTemplate(string $template)
{
    return Template::getTemplate(preg_replace(
        '/' . \Neo\Html\Template::$TEMPLATE_FILE_EXTENSION . '$/i',
        '',
        $template
    ));
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
 * 加载工具类
 *
 * @param string $helper    工具类
 * @param string $namespace 命名空间前缀
 *
 * @return \Neo\Base\Helper 加载的类
 */
function loadHelper(string $helper, $namespace = 'App\\Helper')
{
    return loadClass($helper, $namespace);
}

/**
 * 加载业务
 *
 * @param string $service   业务类
 * @param string $namespace 命名空间前缀
 *
 * @return \Neo\Base\Service 加载的类
 */
function loadService(string $service, $namespace = 'App\\Service')
{
    return loadClass($service, $namespace);
}

/**
 * 加载模型
 *
 * @param string $model     模型类
 * @param string $namespace 命名空间前缀
 *
 * @return \Neo\Base\Model 加载的类
 */
function loadModel(string $model, $namespace = 'App\\Model')
{
    return loadClass($model, $namespace);
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
    return \Neo\I18n::__($text);
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
 * @return \Carbon\Carbon|\Carbon\CarbonImmutable
 */
function carbon(?string $tz = null, ?bool $immutable = false)
{
    $tz || $tz = getDatetimeZone();

    return $immutable ? \Carbon\CarbonImmutable::now($tz) : \Carbon\Carbon::now($tz);
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
        $carbon->setTimestamp($timestamp)
            ->setMicrosecond($microsecond);
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

    neo()->request->setRequestFormat('json');

    Debug::logApi($jsonarray);

    byebye($statusCode, json_encode($jsonarray));
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
 * @param int    $statusCode Http状态码
 * @param string $content    输出内容
 */
function byebye(?int $statusCode = null, ?string $content = null)
{
    $neo = neo();

    // 只有调试模式下，非ajax调用页面输出
    if (! Utility::isAjax() && $neo['explain_sql']) {
        MySQLExplain::display();
    }

    NeoResponse::send($statusCode, $content);

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
    $neo = neo();

    $filters[] = 'neo';
    $filters[] = 'db';

    $left = [];
    foreach ($vars as $key => $var) {
        if (! in_array($key, $filters) && $key[0] != '_' && ! preg_match('/(Controller|Model|Helper|Service)$/i', $key)) {
            $left[$key] = $var;
        }
    }

    $neo->setTemplateVars($left);
}

/**
 * 从文件名中移除系统路径
 *
 * @param null|string $filename
 *
 * @return string
 */
function removeSystemPathFromFileName(?string $filename = null)
{
    if ($filename) {
        return str_ireplace(NeoFrame::getAbsPath(), '', $filename);
    }

    return $filename;
}
