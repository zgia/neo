<?php

namespace Neo\Html;

use Neo\Http\Cookie;
use Neo\Str;
use Neo\Utility;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * HTML 页面元素处理
 */
class Page
{
    /**
     * 跳转页面到指定的URL，缺省是跳转到首页
     *
     * @param string $url          指定的URL
     * @param string $message      跳转时，显示提示信息
     * @param string $title        跳转时，显示标题信息
     * @param int    $redirectTime 多长时间后，自动跳转
     * @param bool   $isError      是否是错误信息，0表示成功，false表示常规信息，true表示错误信息
     * @param bool   $back         是否显示后退链接
     */
    public static function redirect(
        string $url = '',
        string $message = '',
        string $title = '',
        int $redirectTime = 2,
        bool $isError = false,
        bool $back = true
    ) {
        if (strpos($url, "\r\n") !== false) {
            trigger_error('Header may not contain more than a single header, new line detected.', E_USER_ERROR);
        }

        if (! preg_match('#^[a-z]+(?<!about|javascript|vbscript|data)://#i', $url)) {
            if ($url[0] != '/') {
                $url = '/' . $url;
            }
        }

        // 跳转时，添加一个随机数
        $addRandStr = defined('REDIRECT_WITH_RANDOM') && REDIRECT_WITH_RANDOM;
        // 用于翻页后的跳转
        $backpage = (int) Cookie::get('backpage');

        if ($addRandStr || $backpage > 1) {
            $sc = parse_url($url);
            $url = '';

            if (isset($sc['scheme'])) {
                $url .= $sc['scheme'] . '://';
            }
            if (isset($sc['user'])) {
                $url .= $sc['user'];

                if (isset($sc['pass'])) {
                    $url .= ':' . $sc['pass'];
                }
                $url .= '@';
            }
            if (isset($sc['host'])) {
                $url .= $sc['host'];

                if (isset($sc['port'])) {
                    $url .= ':' . $sc['port'];
                }
            }
            if (isset($sc['path'])) {
                $url .= $sc['path'];
            }

            $query = [];
            if (isset($sc['query'])) {
                parse_str($sc['query'], $query);
            }
            // 跳转时，添加一个随机数
            if ($addRandStr) {
                $query['_'] = Str::randString(12, 0, true);
            }
            // 用于翻页后的跳转
            if ($backpage > 1 && ! isset($query['p'])) {
                $query['p'] = $backpage;
            }

            if ($query) {
                $url .= '?' . http_build_query($query);
            }

            if (isset($sc['fragment'])) {
                $url .= '#' . $sc['fragment'];
            }
        }

        if (empty($message)) {
            (new RedirectResponse($url, 302, neo()->getResponse()->headers->all()))->prepare(neo()->getRequest())->send();

            // 不用byebye
            unload();
        } else {
            $title || $title = __('Redirect');

            $extension = [
                'redirectTime' => $redirectTime,
                'isError' => $isError,
                'url' => $url,
                'back' => $back,
            ];

            static::displaySimpleErrorPage($message, $title, $extension);
        }
    }

    /**
     * 404错误
     */
    public static function neo404()
    {
        $heading = '404 Page Not Found';
        $message = 'The page you requested was not found.';

        Page::neoDie($message, $heading, [], 404);
    }

    /**
     * 退出系统，显示失败信息。
     *
     * @param string $message    错误信息
     * @param string $title      错误标题
     * @param array  $more       更多信息
     * @param int    $statusCode Http状态码
     */
    public static function neoDie(
        ?string $message = null,
        ?string $title = null,
        array $more = [],
        int $statusCode = 200
    ) {
        $message = (string) $message;

        if (Utility::isCli()) {
            $msg = $message . PHP_EOL . PHP_EOL;

            foreach ($more as $k => $v) {
                if ($v) {
                    $msg .= $k . ":\t\t" . $v . PHP_EOL;
                }
            }

            byebye($statusCode, static::colorConsoleText($msg . PHP_EOL, 'green', 'red'));
        }

        if (Utility::isAjax()) {
            printErrorJSON($message);
        }

        static::displaySimpleErrorPage(
            nl2br($message),
            (string) $title,
            ['more' => $more, 'isError' => true, 'statusCode' => $statusCode]
        );
    }

    /**
     * 显示一个简单的错误页面
     *
     * @param string     $message
     * @param string     $title
     * @param null|array $extension
     */
    public static function displaySimpleErrorPage(string $message = '', string $title = '', ?array $extension = null)
    {
        $statusCode = is_array($extension) ? $extension['statusCode'] : null;

        // 自定义一个错误页面
        if (defined('NEO_REDIRECT_PAGE') && NEO_REDIRECT_PAGE) {
            $page = NEO_REDIRECT_PAGE;
        } else {
            $page = dirname(__FILE__) . DIRECTORY_SEPARATOR . 'error_page.php';
        }

        if (file_exists($page)) {
            getUserDefinedVars(get_defined_vars());
            neo()->getTemplate()->loadTemplateFile($page);

            $message = null;
        }

        byebye($statusCode, $message);
    }

    /**
     * 刷新输出缓冲
     */
    public static function doflush()
    {
        static $gzip_handler = null;
        if ($gzip_handler === null) {
            $gzip_handler = false;
            $output_handlers = ob_list_handlers();
            if (is_array($output_handlers)) {
                foreach ($output_handlers as $handler) {
                    if ($handler == 'ob_gzhandler') {
                        $gzip_handler = true;
                        break;
                    }
                }
            }
        }

        if ($gzip_handler) {
            // forcing a flush with this is very bad
            return;
        }

        if (ob_get_length() !== false) {
            ob_flush();
        }
        flush();
    }

    /**
     * 给命令行显示的文字加颜色
     *
     * @param string $text    文字
     * @param string $fgColor 文字颜色
     * @param string $bgColor 背景颜色
     *
     * @return string
     */
    public static function colorConsoleText($text, $fgColor = 'default', $bgColor = 'default')
    {
        // 文字颜色
        $foregroundColors = [
            'black' => ['set' => 30, 'unset' => 39],
            'red' => ['set' => 31, 'unset' => 39],
            'green' => ['set' => 32, 'unset' => 39],
            'yellow' => ['set' => 33, 'unset' => 39],
            'blue' => ['set' => 34, 'unset' => 39],
            'magenta' => ['set' => 35, 'unset' => 39],
            'cyan' => ['set' => 36, 'unset' => 39],
            'white' => ['set' => 37, 'unset' => 39],
            'default' => ['set' => 39, 'unset' => 39],
        ];

        // 文字颜色
        $backgroundColors = [
            'black' => ['set' => 40, 'unset' => 49],
            'red' => ['set' => 41, 'unset' => 49],
            'green' => ['set' => 42, 'unset' => 49],
            'yellow' => ['set' => 43, 'unset' => 49],
            'blue' => ['set' => 44, 'unset' => 49],
            'magenta' => ['set' => 45, 'unset' => 49],
            'cyan' => ['set' => 46, 'unset' => 49],
            'white' => ['set' => 47, 'unset' => 49],
            'default' => ['set' => 49, 'unset' => 49],
        ];

        $foreground = $foregroundColors[$fgColor];
        $background = $backgroundColors[$bgColor];

        $setCodes = [];
        $unsetCodes = [];

        if ($foreground !== null) {
            $setCodes[] = $foreground['set'];
            $unsetCodes[] = $foreground['unset'];
        }
        if ($background !== null) {
            $setCodes[] = $background['set'];
            $unsetCodes[] = $background['unset'];
        }

        if (count($setCodes) === 0) {
            return $text;
        }

        return sprintf("\033[%sm%s\033[%sm", implode(';', $setCodes), $text, implode(';', $unsetCodes));
    }
}
