<?php

namespace Neo\Http;

use Symfony\Component\HttpFoundation\Request as SymfonyRequest;

/*
 * 按照预设规则强制转换Request参数的类型
 */
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
     * 请求参数
     *
     * @var array
     */
    private $params = [];

    /**
     * 读取Request请求参数
     *
     * @return array
     */
    public function getParams()
    {
        return $this->params;
    }

    /**
     * 设置Request请求参数
     *
     * @param array $params
     * 
     * @return array
     */
    public function setParams(array $params)
    {
        $this->params = array_merge($this->params, $params);

        return $this->params;
    }

    /**
     * Makes data in an array safe to use
     *
     * @param array $source    The source array containing the data to be cleaned
     * @param array $variables Array of variable names and types we want to extract from the source array
     *
     * @return array
     */
    public static function cleanArray($source, $variables)
    {
        $return = [];

        foreach ($variables as $varname => $vartype) {
            $return[$varname] = static::clean($source[$varname], $vartype, isset($source[$varname]));
        }

        return $return;
    }

    /**
     * Makes GPC variables safe to use
     *
     * @param string $gpc       Either, g, p, c, r, f (corresponding to get, post, cookie, request, files)
     * @param array  $variables Array of variable names and types we want to extract from the source array
     *
     * @return array
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

        if ($gpc === 'r') {
            $requestOrder = ini_get('request_order') ?: ini_get('variables_order');
            $requestOrder = preg_replace('#[^cgp]#', '', strtolower($requestOrder)) ?: 'gp';

            $data = [];

            foreach (str_split($requestOrder) as $order) {
                $prop = $superglobal[$order];
                $data = array_merge($data, $this->{$prop}->all());
            }
        } else {
            $prop = $superglobal[$gpc];
            $data = $this->{$prop}->all();
        }

        $input = [];

        foreach ($variables as $varname => $vartype) {
            $input[$varname] = static::clean($data[$varname], $vartype, isset($data[$varname]));
        }

        return $input;
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
            $var = static::doClean($var, $vartype);
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
                $data = ($data = intval($data)) < 0 ? 0 : $data;
                break;
            case INPUT_TYPE_NUM:
                $data = strval($data) + 0;
                break;
            case INPUT_TYPE_UNUM:
                $data = (($data = strval($data) + 0) < 0) ? 0 : $data;
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
                $data = in_array(strtolower($data), $booltypes) ? 1 : 0;
                break;
            case INPUT_TYPE_ARRAY:
                $data = (is_array($data)) ? $data : [];
                break;
            case INPUT_TYPE_FILE:

                // perhaps redundant :p
                if (is_array($data)) {
                    if (is_array($data['name'])) {
                        $files = count($data['name']);
                        for ($index = 0; $index < $files; ++$index) {
                            $data['name']["{$index}"] = trim(strval($data['name']["{$index}"]));
                            $data['type']["{$index}"] = trim(strval($data['type']["{$index}"]));
                            $data['tmp_name']["{$index}"] = trim(strval($data['tmp_name']["{$index}"]));
                            $data['error']["{$index}"] = intval($data['error']["{$index}"]);
                            $data['size']["{$index}"] = intval($data['size']["{$index}"]);
                        }
                    } else {
                        $data['name'] = trim(strval($data['name']));
                        $data['type'] = trim(strval($data['type']));
                        $data['tmp_name'] = trim(strval($data['tmp_name']));
                        $data['error'] = intval($data['error']);
                        $data['size'] = intval($data['size']);
                    }
                } else {
                    $data = [
                        'name' => '',
                        'type' => '',
                        'tmp_name' => '',
                        'error' => 0,
                        'size' => 4, // UPLOAD_ERR_NO_FILE
                    ];
                }
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
                    $data = static::clean($data, INPUT_TYPE_UINT);
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
     * fetch url of current page without the query string
     * 
     * @return string The url of current page
     */
    public function script()
    {
        $script_path = $this->getRequestUri();;

        $quest_pos = strpos($script_path, '?');

        return $quest_pos !== false ? substr($script_path, 0, $quest_pos) : $script_path;
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
        return $this->headers->get('referer');
    }

    /**
     * User Agent
     *
     * @return null|string The user agent
     */
    public function userAgent()
    {
        return $this->headers->get('user-agent');
    }
}
