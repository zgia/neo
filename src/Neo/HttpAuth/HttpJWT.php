<?php

namespace Neo\HttpAuth;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Neo\Exception\AuthException;
use Neo\Exception\LogicException;
use Neo\NeoLog;

/**
 * HTTP JWT 验证
 */
class HttpJWT extends Auth implements AuthInterface
{
    // 验证间隔时间
    private int $intervalTime = 0;

    // 过期时间
    private int $expiredTime = 518400;

    // 加密串
    private string $secretKey = '';

    // Algorithm used to sign the token, see https://tools.ietf.org/html/draft-ietf-jose-json-web-algorithms-40#section-3
    private string $algorithm = 'HS512';

    /**
     * JWT 校验后返回的数据信息
     *
     * @var array<string,mixed>
     */
    private array $data;

    /**
     * HttpJWT constructor.
     *
     * @param array<string,mixed> $config
     */
    public function __construct(array $config = [])
    {
        parent::__construct($config);

        if (empty($config['secret_key'])) {
            throw new LogicException('You must config secretKey for JWT encode.');
        }

        $this->secretKey = $config['secret_key'];

        if (! empty($config['algorithm'])) {
            $this->algorithm = $config['algorithm'];
        }

        if (! empty($config['expired_time'])) {
            $this->expiredTime = $config['expired_time'];
        }

        if (! empty($config['interval_time'])) {
            $this->intervalTime = $config['interval_time'];
        }
    }

    /**
     * 验证用户
     *
     * @param string              $authorization
     * @param array<string,mixed> $params
     *
     * @return bool
     *
     * @throws LogicException
     */
    public function authenticate(string $authorization = '', array $params = [])
    {
        $authed = false;

        try {
            $authed = $this->auth($authorization);
        } catch (\Throwable $ex) {
            NeoLog::error('auth', __FUNCTION__, [neo()->getRequest()->neoServer('authorization'), $ex->getMessage()]);

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
        $args = func_get_args();
        $authorization = $args[0];

        [$jwt] = sscanf($authorization, 'Authorization: Bearer %s');

        // No token was able to be extracted from the authorization header
        if (! $jwt) {
            throw new LogicException('HTTP/1.0 400 Bad Request', 400);
        }

        try {
            $authed = JWT::decode(
                $jwt,
                new Key($this->secretKey, $this->algorithm)
            );

            $this->data = ['uid' => $authed->uid, 'uname' => $authed->unm, 'exp' => $authed->exp];
        } catch (\Throwable $ex) {
            throw new LogicException($ex->getMessage(), 401, $ex);
        }

        return true;
    }

    /**
     * @param string $server
     * @param int    $userid
     * @param string $username
     *
     * @return mixed
     *
     * @throws LogicException
     */
    public function getUserToken($server, $userid, $username)
    {
        try {
            // Json Token Id: an unique identifier for the token
            $tokenId = base64_encode(random_bytes(32));
            // Issued at: time when the token was generated
            $issuedAt = time();
            // 项目请求没有间隔,登录后立刻验证
            $notBefore = $issuedAt + $this->intervalTime;
            $expire = $notBefore + $this->expiredTime;
            $issuer = $server;

            // Create the token as an array
            $data = [
                'iat' => $issuedAt,
                'jti' => $tokenId,
                'iss' => $issuer,
                'nbf' => $notBefore,
                'exp' => $expire,
                'uid' => $userid,
                'unm' => $username,
            ];

            /*
             * Encode the array to a JWT string.
             * Second parameter is the key to encode the token.
             *
             * The output string can be validated at http://jwt.io/
             */
            return JWT::encode($data, $this->secretKey, $this->algorithm);
        } catch (\Exception $ex) {
            throw new LogicException($ex->getMessage(), $ex->getCode());
        }
    }

    /**
     * 获取JWT验证后的数据
     *
     * @return array<string,mixed>
     */
    public function getData()
    {
        return (array) $this->data;
    }
}
