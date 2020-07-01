<?php

namespace Neo\HttpAuth;

use Neo\Exception\NeoException;
use Neo\NeoLog;

/**
 * HTTP WSSE 验证
 */
class HttpWsse extends Auth implements AuthInterface
{
    /**
     * HttpWsse constructor.
     *
     * @param array $config
     */
    public function __construct(array $config = [])
    {
        parent::__construct($config);
    }

    /**
     * 验证用户
     *
     * @param string $password
     * @param array  $userToken
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
        } catch (\Exception $ex) {
            NeoLog::error('auth', 'httpwsse', $ex);
        }

        return $authed;
    }

    /**
     * 验证
     *
     * @throws NeoException
     * @return bool
     */
    protected function auth()
    {
        [$secret, $digest, $nonce, $created] = func_get_args();

        $clientTime = strtotime($created);
        $serverTime = time() + 60;
        if ($clientTime > $serverTime || ($serverTime - $clientTime > 300)) {
            throw new NeoException(__('Time error.'));
        }

        $expected = base64_encode(sha1(base64_decode($nonce) . $created . $secret, true));

        if ($digest != $expected) {
            throw new NeoException(__('Authenticate failed.'));
        }

        return true;
    }

    /**
     * 获取用户 Token
     *
     * @param $wsse
     *
     * @return array|bool
     */
    public function getUserToken($wsse)
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
