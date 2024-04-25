<?php

namespace Neo\Http;

use Symfony\Component\HttpFoundation\Cookie as SymfonyCookie;

/**
 * Class NeoCookie
 */
class Cookie
{
    /**
     * Sets a cookie based on Album environmental settings
     *
     * @param string      $name     The name of the cookie
     * @param null|string $value    The value of the cookie
     * @param int         $expire   The time the cookie expires
     * @param string      $path     The path on the server in which the cookie will be available on
     * @param null|string $domain   The domain that the cookie is available to
     * @param null|bool   $secure   Whether the client should send back the cookie only over HTTPS or null to auto-enable this when the request is already using HTTPS
     * @param bool        $httpOnly Whether the cookie will be made accessible only through the HTTP protocol
     */
    public static function set(string $name, string $value = null, int $expire = 0, string $path = '/', string $domain = null, bool $secure = null, bool $httpOnly = true): void
    {
        if (defined('SKIP_COOKIE')) {
            return;
        }

        $request = neo()->getRequest();
        $response = neo()->getResponse();

        $expire = $expire ? time() + $expire : 0;

        $path || $path = '/';

        $domain || $domain = $request->getHost();

        if (is_null($secure)) {
            $secure = $request->isSecure();
        }

        $cookie = SymfonyCookie::create($name, $value, $expire, $path, $domain, $secure, $httpOnly);

        $response->headers->setCookie($cookie);
    }

    /**
     * 获取COOKIE
     *
     * @param string  $name    Cookie name
     * @param Request $request
     *
     * @return string
     */
    public static function get(string $name, ?Request $request = null)
    {
        $request || $request = neo()->getRequest();

        return $request->cookies->get($name);
    }
}
