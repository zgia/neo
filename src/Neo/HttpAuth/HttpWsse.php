<?php

namespace Neo\HttpAuth;

use Neo\Exception\AuthException;
use Neo\Exception\LogicException;

/**
 * HTTP WSSE 验证
 */
class HttpWsse extends Auth implements AuthInterface
{
    /**
     * HttpWsse constructor.
     *
     * @param array<string,mixed> $config
     */
    public function __construct(array $config = [])
    {
        parent::__construct($config);
    }

    /**
     * 验证用户
     *
     * @param string              $password
     * @param array<string,mixed> $userToken
     *
     * @return bool
     */
    public function authenticate(string $password = '', array $userToken = [])
    {
        $authed = false;

        try {
            $authed = $this->auth(
                $password,
                $userToken['digest'],
                $userToken['nonce'],
                $userToken['created']
            );
        } catch (\Throwable $ex) {
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
        [$secret, $digest, $nonce, $created] = func_get_args();

        $clientTime = strtotime($created);
        $serverTime = time() + 60;
        if ($clientTime > $serverTime || ($serverTime - $clientTime > 300)) {
            throw new LogicException(__('Time error.'));
        }

        $expected = base64_encode(sha1(base64_decode($nonce) . $created . $secret, true));

        if ($digest != $expected) {
            throw new LogicException(__('Authenticate failed.'));
        }

        return true;
    }

    /**
     * 获取用户 Token
     *
     * @param string $wsse
     *
     * @return array<string,mixed>|bool
     */
    public function getUserToken(string $wsse)
    {
        $wsseRegex = '/UsernameToken Username="([^"]+)", PasswordDigest="([^"]+)", Nonce="([^"]+)", Created="([^"]+)"/';

        if (preg_match($wsseRegex, $wsse, $matches) !== 1) {
            return false;
        }

        return [
            'username' => $matches[1],
            'digest' => $matches[2],
            'nonce' => $matches[3],
            'created' => $matches[4],
        ];
    }
}
