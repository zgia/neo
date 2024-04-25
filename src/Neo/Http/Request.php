<?php

namespace Neo\Http;

use Neo\Exception\ParamException;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;

// 按照预设规则强制转换Request参数的类型
// no change
define('INPUT_TYPE_NOCLEAN', 0);
// force boolean
define('INPUT_TYPE_BOOL', 1);
// force integer
define('INPUT_TYPE_INT', 2);
// force unsigned integer
define('INPUT_TYPE_UINT', 3);
// force number
define('INPUT_TYPE_NUM', 4);
// force unsigned number
define('INPUT_TYPE_UNUM', 5);
// force unix datestamp (unsigned integer)
define('INPUT_TYPE_UNIXTIME', 6);
// force trimmed string
define('INPUT_TYPE_STR', 7);
// force string - no trim
define('INPUT_TYPE_NOTRIM', 8);
// force trimmed string with HTML made safe
define('INPUT_TYPE_NOHTML', 9);
// force array
define('INPUT_TYPE_ARRAY', 10);
// force file
define('INPUT_TYPE_FILE', 11);
// force binary string
define('INPUT_TYPE_BINARY', 12);

/**
 * Class to handle and sanitize variables from GET, POST and COOKIE etc
 */
class Request extends SymfonyRequest
{
    /**
     * 是否异步请求
     *
     * @var bool
     */
    private $ajax = false;

    /**
     * 请求参数
     *
     * @var array<string,mixed>
     */
    private $params = [];

    /**
     * 请求参数
     *
     * @return array<string,mixed>
     */
    public function getParams()
    {
        return $this->params;
    }

    /**
     * 设置参数
     *
     * @param array<string,mixed> $params
     */
    public function setParams(array $params): void
    {
        $this->params = $params;
    }

    /**
     * 设置参数
     *
     * @param array<string,mixed> $params
     *
     * @return array<string,mixed>
     */
    public function mergeParams(array $params)
    {
        if ($params) {
            $this->params = array_merge($this->params, $params);
        }

        return $this->params;
    }

    /**
     * 设置请求方式
     *
     * @param bool $ajax
     */
    public function setAjax(bool $ajax): void
    {
        $this->ajax = $ajax;
    }

    /**
     * 是否异步请求
     *
     * @return bool
     */
    public function isAjax()
    {
        return $this->ajax || $this->neoServer('neo_ajax');
    }

    /**
     * Create Request
     *
     * @param string              $type
     * @param array<string,mixed> $data
     *
     * @return SymfonyRequest
     */
    public static function createRequest(string $type = 'fpm', ?array $data = null)
    {
        /**
         * @var Request $request
         */
        $request = null;

        if ($type === 'fpm') {
            $request = static::createFromGlobals();
        } else {
            if (! is_array($data)) {
                throw new ParamException(__('Invalid param $data, it must be an array.'));
            }

            $uri = (string) $data['uri'];
            $method = (string) $data['method'];
            $parameters = (array) $data['parameters'];
            $cookies = (array) $data['cookies'];
            $files = (array) $data['files'];
            $server = (array) $data['server'];
            $content = $data['content'];

            $request = static::create($uri, $method, $parameters, $cookies, $files, $server, $content);
        }

        $_req = $request->neoRequest(null, true);

        $request->setParams($_req);
        $request->setAjax((bool) ($_req['ajax'] ?? ($_req['AJAX'] ?? false)));

        return $request;
    }

    /**
     * Makes GPC variables safe to use
     *
     * @param string              $gpc       Either, g, p, c, r, f (corresponding to get, post, cookie, request, files)
     * @param array<string,mixed> $variables Array of variable names and types we want to extract from the source array
     *
     * @return array<string,mixed>
     */
    public function cleanGPC($gpc, $variables)
    {
        static $superglobal = [
            'g' => 'query',
            'p' => 'request',
            'c' => 'cookies',
            's' => 'server',
            'f' => 'files',
        ];

        switch ($gpc) {
            case 'r':
                $source = $this->neoRequest();

                break;

            case 's':
                $source = $this->neoServer();

                break;

            default:
                $source = $this->{$superglobal[$gpc]}->all();

                break;
        }

        return static::cleanArray($source, $variables);
    }

    /**
     * Makes data in an array safe to use
     *
     * @param array<string,mixed> $source    The source array containing the data to be cleaned
     * @param array<string,mixed> $variables Array of variable names and types we want to extract from the source array
     *
     * @return array<string,mixed>
     */
    public static function cleanArray($source, $variables)
    {
        $return = [];

        foreach ($variables as $varname => $vartype) {
            $return[$varname] = self::clean($source[$varname], $vartype, isset($source[$varname]));
        }

        return $return;
    }

    /**
     * Makes a single variable safe to use and returns it
     *
     * @param mixed $var     The variable to be cleaned
     * @param int   $vartype The type of the variable in which we are interested
     * @param bool  $exists  Whether or not the variable to be cleaned actually is set
     *
     * @return mixed The cleaned value
     */
    private static function clean($var, int $vartype = INPUT_TYPE_NOCLEAN, bool $exists = true)
    {
        if ($exists) {
            $var = self::doClean($var, $vartype);
        } else {
            switch ($vartype) {
                case INPUT_TYPE_INT:
                case INPUT_TYPE_UINT:
                case INPUT_TYPE_NUM:
                case INPUT_TYPE_UNUM:
                case INPUT_TYPE_UNIXTIME:
                case INPUT_TYPE_BOOL:
                    $var = 0;

                    break;

                case INPUT_TYPE_STR:
                case INPUT_TYPE_NOHTML:
                case INPUT_TYPE_NOTRIM:
                    $var = '';

                    break;

                case INPUT_TYPE_ARRAY:
                case INPUT_TYPE_FILE:
                    $var = [];

                    break;

                default:
                    $var = null;

                    break;
            }
        }

        return $var;
    }

    /**
     * Does the actual work to make a variable safe
     *
     * @param mixed $data The data we want to make safe
     * @param int   $type The type of the data
     *
     * @return mixed
     */
    private static function doClean($data, $type)
    {
        static $booltypes = ['1', 'yes', 'y', 'true'];

        switch ($type) {
            case INPUT_TYPE_INT:
                $data = intval($data);

                break;

            case INPUT_TYPE_UINT:
                $data = max(0, intval($data));

                break;

            case INPUT_TYPE_NUM:
                $data = $data + 0;

                break;

            case INPUT_TYPE_UNUM:
                $data = max(0, $data + 0);

                break;

            case INPUT_TYPE_BINARY:
            case INPUT_TYPE_NOTRIM:
                $data = strval($data);

                break;

            case INPUT_TYPE_STR:
                $data = trim(strval($data));

                break;

            case INPUT_TYPE_NOHTML:
                $data = htmlentities(trim(strval($data)), ENT_QUOTES);

                break;

            case INPUT_TYPE_BOOL:
                $data = in_array(strtolower($data), $booltypes);

                break;

            case INPUT_TYPE_ARRAY:
                if (! is_array($data)) {
                    $data = [];
                }

                break;

            case INPUT_TYPE_FILE:
                $data = static::formatUploadedFiles($data);

                break;

            case INPUT_TYPE_UNIXTIME:
                if (is_array($data)) {
                    $data = array_map('intval', $data);
                    if ($data['month'] && $data['day'] && $data['year']) {
                        $data = mktime(
                            $data['hour'],
                            $data['minute'],
                            $data['second'],
                            $data['month'],
                            $data['day'],
                            $data['year']
                        );
                    } else {
                        $data = 0;
                    }
                } else {
                    $data = self::clean($data, INPUT_TYPE_UINT);
                }

                break;
        }

        // strip out characters that really have no business being in non-binary data
        switch ($type) {
            case INPUT_TYPE_STR:
            case INPUT_TYPE_NOTRIM:
            case INPUT_TYPE_NOHTML:
                $data = str_replace(chr(0), '', $data);
        }

        return $data;
    }

    /**
     * 转换批量上传的文件：$_FILES
     * 从
     *      $_FILES['files'] = ['name' => ['name1', 'name2], 'type' => ['image/jpg', 'image/png'], ...]
     * 到
     *      $_FILES['files'] = [['name' => 'name1', 'type' =>'image/jpg', ...], ['name' => 'name2', 'type' =>'image/png', ...]]
     *
     * @param null|array<string,mixed> $data
     *
     * @return array<string,mixed>
     */
    public static function formatUploadedFiles(?array $data = null)
    {
        $f = [];

        if (is_array($data)) {
            // 一次上传多个文件
            if (is_array($data['name'])) {
                for ($index = 0; $index < count($data['name']); ++$index) {
                    $f[] = [
                        'name' => trim(strval($data['name']["{$index}"])),
                        'type' => trim(strval($data['type']["{$index}"])),
                        'tmp_name' => trim(strval($data['tmp_name']["{$index}"])),
                        'error' => intval($data['error']["{$index}"]),
                        'size' => intval($data['size']["{$index}"]),
                    ];
                }
            } else {
                $f = [
                    'name' => trim(strval($data['name'])),
                    'type' => trim(strval($data['type'])),
                    'tmp_name' => trim(strval($data['tmp_name'])),
                    'error' => intval($data['error']),
                    'size' => intval($data['size']),
                ];
            }
        } else {
            $f = [
                'name' => '',
                'type' => '',
                'tmp_name' => '',
                'error' => UPLOAD_ERR_NO_FILE,
                'size' => 0,
            ];
        }

        return $f;
    }

    /**
     * 移除 URI 中的 Query String
     *
     * @param string $uri
     *
     * @return string URI
     */
    public static function stripQueryString(string $uri)
    {
        if (false !== $pos = strpos($uri, '?')) {
            $uri = substr($uri, 0, $pos);
        }

        return $uri;
    }

    /**
     * 获取当前页不带 Query String 的 URI
     *
     * @return string URI
     */
    public function script()
    {
        return static::stripQueryString($this->getRequestUri());
    }

    /**
     * Fetches an alternate IP address of the current visitor
     *
     * @return string The alternate IP
     */
    public function altIp()
    {
        $ips = $this->getClientIps();

        return $ips[1] ?? $ips[0];
    }

    /**
     * Http Referer
     *
     * @return string The referer
     */
    public function referer()
    {
        return $this->neoHeader('referer');
    }

    /**
     * User Agent
     *
     * @return null|string The user agent
     */
    public function userAgent()
    {
        return $this->neoHeader('user-agent');
    }

    /**
     * 获取伪 $_POST 中的数据
     *
     * @param string $key
     *
     * @return null|array<string,mixed>|string
     */
    public function neoPost(?string $key = null)
    {
        if ($key) {
            return $this->request->get($key);
        }

        return $this->request->all();
    }

    /**
     * 获取伪 $_GET 中的数据
     *
     * @param string $key
     *
     * @return null|array<string,mixed>|string
     */
    public function neoGet(?string $key = null)
    {
        if ($key) {
            return $this->query->get($key);
        }

        return $this->query->all();
    }

    /**
     * 获取伪 $_FILES 中的数据
     *
     * @param string $key
     *
     * @return null|array<string,mixed>|string
     */
    public function neoFile(?string $key = null)
    {
        if ($key) {
            return $this->files->get($key);
        }

        return $this->files->all();
    }

    /**
     * 获取伪 $_COOKIES 中的数据
     *
     * @param string $key
     *
     * @return null|array<string,mixed>|string
     */
    public function neoCookie(?string $key = null)
    {
        if ($key) {
            return $this->cookies->get($key);
        }

        return $this->cookies->all();
    }

    /**
     * 获取 Header 中的数据
     *
     * @param string $key
     *
     * @return null|array<string,mixed>|string
     */
    public function neoHeader(?string $key = null)
    {
        if ($key) {
            return $this->headers->get($key);
        }

        return $this->headers->all();
    }

    /**
     * 获取伪 $_SERVER 中的数据
     *
     * @param string $key
     *
     * @return null|array<string,mixed>|string
     */
    public function neoServer(?string $key = null)
    {
        static $_svr = null;

        if ($_svr === null) {
            $this->server->set('QUERY_STRING', static::normalizeQueryString(http_build_query($this->query->all(), '', '&')));

            $tmp = $this->server->all();

            foreach ($this->headers->all() as $k => $v) {
                $k = strtoupper(str_replace('-', '_', $k));
                if (in_array($k, ['CONTENT_TYPE', 'CONTENT_LENGTH', 'CONTENT_MD5'], true)) {
                    $tmp[$k] = implode(', ', $v);
                } else {
                    $tmp['HTTP_' . $k] = implode(', ', $v);
                }
            }

            $_svr = $tmp;
        }

        if ($key) {
            return $_svr[$key] ?? null;
        }

        return $_svr;
    }

    /**
     * 获取伪 $_REQUEST 中的数据
     *
     * @param string $key
     * @param bool   $reload
     *
     * @return null|array<string,mixed>|string
     */
    public function neoRequest(?string $key = null, bool $reload = false)
    {
        static $_req = null;

        if ($_req === null || $reload) {
            $request = ['g' => $this->query->all(), 'p' => $this->request->all(), 'c' => $this->cookies->all()];

            $requestOrder = ini_get('request_order') ?: ini_get('variables_order');
            $requestOrder = preg_replace('#[^cgp]#', '', strtolower($requestOrder)) ?: 'gp';

            $tmp = [[]];

            foreach (str_split($requestOrder) as $order) {
                $tmp[] = $request[$order];
            }

            $_req = array_merge(...$tmp);
        }

        if ($key) {
            return $_req[$key] ?? null;
        }

        return $_req;
    }
}
