<?php

namespace Neo\Http;

/*
 * Ways of cleaning input. Should be mostly self-explanatory.
 */

// no change
define('TYPE_NOCLEAN', 0);
// force boolean
define('TYPE_BOOL', 1);
// force integer
define('TYPE_INT', 2);
// force unsigned integer
define('TYPE_UINT', 3);
// force number
define('TYPE_NUM', 4);
// force unsigned number
define('TYPE_UNUM', 5);
// force unix datestamp (unsigned integer)
define('TYPE_UNIXTIME', 6);
// force trimmed string
define('TYPE_STR', 7);
// force string - no trim
define('TYPE_NOTRIM', 8);
// force trimmed string with HTML made safe
define('TYPE_NOHTML', 9);
// force array
define('TYPE_ARRAY', 10);
// force file
define('TYPE_FILE', 11);
// force binary string
define('TYPE_BINARY', 12);

/**
 * Class to handle and sanitize variables from GET, POST and COOKIE etc
 */
class Request
{
    /**
     * Request constructor.
     */
    private function __construct()
    {
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
            $return[$varname] = self::clean($source[$varname], $vartype, isset($source[$varname]));
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
    public static function cleanGPC($gpc, $variables)
    {
        $request = neo()->request;

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
                $data = array_merge($data, $request->{$prop}->all());
            }
        } else {
            $prop = $superglobal[$gpc];
            $data = $request->{$prop}->all();
        }

        $input = [];

        foreach ($variables as $varname => $vartype) {
            $input[$varname] = self::clean($data[$varname], $vartype, isset($data[$varname]));
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
    private static function clean($var, $vartype = TYPE_NOCLEAN, $exists = true)
    {
        if ($exists) {
            $var = self::doClean($var, $vartype);
        } else {
            switch ($vartype) {
                case TYPE_INT:
                case TYPE_UINT:
                case TYPE_NUM:
                case TYPE_UNUM:
                case TYPE_UNIXTIME:
                case TYPE_BOOL:

                    $var = 0;
                    break;
                case TYPE_STR:
                case TYPE_NOHTML:
                case TYPE_NOTRIM:

                    $var = '';
                    break;
                case TYPE_ARRAY:
                case TYPE_FILE:

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
            case TYPE_INT:
                $data = intval($data);
                break;
            case TYPE_UINT:
                $data = ($data = intval($data)) < 0 ? 0 : $data;
                break;
            case TYPE_NUM:
                $data = strval($data) + 0;
                break;
            case TYPE_UNUM:
                $data = (($data = strval($data) + 0) < 0) ? 0 : $data;
                break;
            case TYPE_BINARY:
            case TYPE_NOTRIM:
                $data = strval($data);
                break;
            case TYPE_STR:
                $data = trim(strval($data));
                break;
            case TYPE_NOHTML:
                $data = htmlentities(trim(strval($data)), ENT_QUOTES);
                break;
            case TYPE_BOOL:
                $data = in_array(strtolower($data), $booltypes) ? 1 : 0;
                break;
            case TYPE_ARRAY:
                $data = (is_array($data)) ? $data : [];
                break;
            case TYPE_FILE:

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
            case TYPE_UNIXTIME:

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
                    $data = self::clean($data, TYPE_UINT);
                }
                break;
        }

        // strip out characters that really have no business being in non-binary data
        switch ($type) {
            case TYPE_STR:
            case TYPE_NOTRIM:
            case TYPE_NOHTML:
                $data = str_replace(chr(0), '', $data);
        }

        return $data;
    }

    /**
     * Fetches the requested URI (path and query string)
     *
     * @return string
     */
    public static function uri()
    {
        return neo()->request->getRequestUri();
    }

    /**
     * fetch url of current page without the query string
     */
    public static function script()
    {
        $script_path = self::uri();

        $quest_pos = strpos($script_path, '?');

        return $quest_pos !== false ? substr($script_path, 0, $quest_pos) : $script_path;
    }

    /**
     * Fetches an alternate IP address of the current visitor
     *
     * @return string
     */
    public static function altIp()
    {
        $ips = neo()->request->getClientIps();

        return $ips[1] ?? $ips[0];
    }

    /**
     * Fetches the IP address of the current visitor, attempting to detect proxies etc.
     *
     * @return string
     */
    public static function ip()
    {
        return neo()->request->getClientIp();
    }

    /**
     * Http Referer
     *
     * @return string
     */
    public static function referer()
    {
        return neo()->request->headers->get('referer');
    }

    /**
     * User Agent
     *
     * @return null|string
     */
    public static function userAgent()
    {
        return neo()->request->headers->get('user-agent');
    }

    /**
     * Request Method
     *
     * @return null|string
     */
    public static function method()
    {
        return neo()->request->getMethod();
    }

    /**
     * Http Host
     */
    public static function host()
    {
        return neo()->request->getHost();
    }

    /**
     * Http Host with Port
     */
    public static function httpHost()
    {
        return neo()->request->getHttpHost();
    }

    /**
     * Scheme: https or http
     */
    public static function scheme()
    {
        return neo()->request->getScheme();
    }
}
