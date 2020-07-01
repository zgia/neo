<?php

namespace Neo\HttpAuth;

use Firebase\JWT\JWT;
use Neo\Exception\NeoException;
use Neo\NeoLog;

/**
 * HTTP JWT 验证
 */
class HttpJWT extends Auth implements AuthInterface
{
    // 验证间隔时间
    private $jwt_interval_time = 0;

    // 过期时间
    private $jwt_expired_time = 518400;

    // 加密串
    private $jwt_secretkey = '';

    // Algorithm used to sign the token, see https://tools.ietf.org/html/draft-ietf-jose-json-web-algorithms-40#section-3
    private $jwt_algorithm = 'HS512';

    // JWT 校验后返回的数据信息
    private $data;

    /**
     * HttpJWT constructor.
     *
     * @param array $config
     */
    public function __construct(array $config = [])
    {
        parent::__construct($config);

        if (! $config['jwt_secretkey']) {
            throw new NeoException('You must config secretkey for JWT encode.');
        }

        $this->jwt_secretkey = $config['jwt_secretkey'];

        if ($config['jwt_algorithm']) {
            $this->jwt_algorithm = $config['jwt_algorithm'];
        }

        if ($config['jwt_expired_time']) {
            $this->jwt_expired_time = (int) $config['jwt_expired_time'];
        }

        if ($config['jwt_interval_time']) {
            $this->jwt_interval_time = (int) $config['jwt_interval_time'];
        }
    }

    /**
     * 验证用户
     *
     * @param string $authorization
     * @param array  $params
     *
     * @throws NeoException
     * @return bool
     */
    public function authenticate(string $authorization = '', array $params = [])
    {
        $authed = false;

        try {
            $authed = $this->auth($authorization);
        } catch (\Exception $ex) {
            NeoLog::error('auth', __FUNCTION__, [$_SERVER['HTTP_AUTHORIZATION'], $ex->getMessage()]);

            $this->unauth();
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
        $args = func_get_args();
        $authorization = $args[0];

        [$jwt] = sscanf($authorization, 'Authorization: Bearer %s');

        // No token was able to be extracted from the authorization header
        if (! $jwt) {
            throw new NeoException('HTTP/1.0 400 Bad Request', 400);
        }

        try {
            $authed = JWT::decode(
                $jwt,
                $this->jwt_secretkey,
                [$this->jwt_algorithm]
            );

            $this->data = ['userId' => $authed->uid, 'userName' => $authed->unm, 'exp' => $authed->exp];
        } catch (\Exception $ex) {
            throw new NeoException($ex->getMessage(), 401, $ex);
        }

        return true;
    }

    /**
     * @param string $server
     * @param int    $userid
     * @param string $username
     *
     * @throws NeoException
     * @return mixed
     */
    public function getUserToken($server, $userid, $username)
    {
        try {
            // Json Token Id: an unique identifier for the token
            $tokenId = base64_encode(random_bytes(32));
            // Issued at: time when the token was generated
            $issuedAt = time();
            // 项目请求没有间隔,登录后立刻验证
            $notBefore = $issuedAt + $this->jwt_interval_time;
            $expire = $notBefore + $this->jwt_expired_time;
            $issuer = $server;

            /*
             * Create the token as an array
             */
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
            return JWT::encode($data, $this->jwt_secretkey, $this->jwt_algorithm);
        } catch (\Exception $ex) {
            throw new NeoException($ex->getMessage(), $ex->getCode());
        }
    }

    /**
     * 获取JWT验证后的数据
     *
     * @return array
     */
    public function getData()
    {
        return (array) $this->data;
    }
}
