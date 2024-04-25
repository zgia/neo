<?php

namespace Neo\HttpAuth;

use Neo\Exception\AuthException;
use Neo\Exception\LogicException;

/**
 * HTTP DIGEST 验证
 */
class HttpDigest extends Auth implements AuthInterface
{
    public const REST_REALM = 'Neo Rest API';

    /**
     * @var array<string,string>
     */
    private $validUsers = ['neo' => 'hdsGh3j9a92'];

    /**
     * HttpDigest constructor.
     *
     * @param array<string,mixed> $config
     */
    public function __construct(array $config = [])
    {
        parent::__construct($config);

        if (isset($config['http_digest']['users'])) {
            $this->validUsers = $config['http_digest']['users'];
        }
    }

    /**
     * 验证用户
     *
     * @param string              $authorization
     * @param array<string,mixed> $params
     *
     * @return bool
     */
    public function authenticate(string $authorization = '', array $params = [])
    {
        // [HTTP_AUTHORIZATION] => Digest username="neo", realm="Neo Rest API", nonce="5667eaa09eb15", uri="/api/xxxx/xxxxx/", cnonce="ODUzOWFkDVhYzEyMzc=", nc=00000001, qop=auth, response="c5b809db146316fe4071de307a4e69a8", opaque="f5837d57b1ded009f166a468773813d1"

        $authed = false;

        try {
            $authed = $this->auth();
        } catch (\Throwable $ex) {
            $this->forceLogin($ex->getMessage(), $ex->getCode());

            throw new AuthException($ex->getMessage(), $ex->getCode(), $ex);
        }

        return $authed;
    }

    /**
     * 验证
     *
     * @return bool
     *
     * @throws LogicException
     */
    protected function auth()
    {
        $uniqid = uniqid('');

        $server = neo()->getRequest()->neoServer();

        if ($server['PHP_AUTH_DIGEST']) {
            $digestString = $server['PHP_AUTH_DIGEST'];
        } elseif ($server['HTTP_AUTHORIZATION']) {
            $digestString = $server['HTTP_AUTHORIZATION'];
        } else {
            $digestString = '';
        }

        if (empty($digestString)) {
            throw new LogicException(__f('%s: digest empty.', $uniqid));
        }

        // 取出验证信息
        $matches = [];
        preg_match_all('@(username|nonce|uri|nc|cnonce|qop|response)=[\'"]?([^\'",]+)@', $digestString, $matches);
        $digest = (empty($matches[1]) || empty($matches[2])) ? [] : array_combine($matches[1], $matches[2]);

        // 校验
        // ① 用户名:realm:密码　⇒　A1
        // ② HTTP方法:URI　⇒　A2
        // ③ A1:nonce:nc:cnonce:qop:A2　⇒　A3

        // Digest 验证需要返回: md5(username:restrealm:password)
        $A1 = md5($digest['username'] . ':' . static::REST_REALM . ':' . (isset($this->validUsers[$digest['username']]) ? $this->validUsers[$digest['username']] : ''));
        if (! array_key_exists('username', $digest)) {
            throw new LogicException(__f('%s: digest error.', $uniqid));
        }

        $A2 = md5(strtoupper($server['REQUEST_METHOD']) . ':' . $digest['uri']);
        $A3 = md5($A1 . ':' . $digest['nonce'] . ':' . $digest['nc'] . ':' . $digest['cnonce'] . ':' . $digest['qop'] . ':' . $A2);

        if ($digest['response'] != $A3) {
            throw new LogicException(__('A3 authenticate failed.'));
        }

        return true;
    }

    /**
     * 响应客户端
     *
     * @param string $nonce
     * @param int    $code
     */
    private function forceLogin($nonce = '', $code = 0): void
    {
        if ($code == 100) {
            header('WWW-Authenticate: Digest realm="' . static::REST_REALM . '", qop="auth", nonce="' . $nonce . '", opaque="' . md5(static::REST_REALM) . '"');
        }
    }
}
