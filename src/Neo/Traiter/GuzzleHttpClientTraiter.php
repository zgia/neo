<?php

namespace Neo\Traiter;

use GuzzleHttp\Client as GuzzleHttpClient;
use GuzzleHttp\Exception\RequestException;
use Neo\Debug;
use Neo\Exception\NeoException;
use Neo\NeoLog;
use Psr\Http\Message\ResponseInterface;

/**
 * Class GuzzleHttpClientTraiter
 */
trait GuzzleHttpClientTraiter
{
    /**
     * HTTP 链接超时
     *
     * @var int
     */
    private $timeout = 3;

    /**
     * 重新初始化HTTP链接
     *
     * @var bool
     */
    private $reload = false;

    /**
     * @var GuzzleHttpClient
     */
    private $httpClient;

    /**
     * 获取超时时间
     *
     * @return int
     */
    public function getTimeout()
    {
        return $this->timeout;
    }

    /**
     * 设置超时时间
     *
     * @param int $timeout
     */
    public function setTimeout($timeout)
    {
        $this->timeout = (int) $timeout;
    }

    /**
     * 设置是否重连超时时间，每次加载后，需要重新设置
     *
     * @param bool $reload
     */
    public function setReload($reload)
    {
        $this->reload = $reload;
    }

    /**
     * Guzzle Http Client
     *
     * @return GuzzleHttpClient
     */
    protected function getGuzzleHttpClient()
    {
        if ($this->reload || $this->httpClient == null) {
            $this->httpClient = new GuzzleHttpClient();

            $this->reload = false;
        }

        return $this->httpClient;
    }

    /**
     * POST
     *
     * @param string $url
     * @param array  $params
     * @param bool   $origin 返回数据是否需要json_decode
     *
     * @throws NeoException
     * @return null|mixed
     */
    public function post($url, array $params = [], $origin = false)
    {
        return $this->httpClient('post', $url, $params, $origin);
    }

    /**
     * GET
     *
     * @param string $url
     * @param array  $query
     * @param bool   $origin 返回数据是否需要json_decode
     *
     * @throws NeoException
     * @return null|mixed
     */
    public function get($url, array $query = [], $origin = false)
    {
        return $this->httpClient('get', $url, $query, $origin);
    }

    /**
     * 发起HTTP请求
     *
     * @param string            $verb    POST, GET, DELETE, etc
     * @param string            $url     URL
     * @param array             $options More options
     * @param bool              $origin  返回数据是否需要json_decode
     * @param ResponseInterface $result  Response对象
     *
     * @throws NeoException
     * @return null|array|string
     */
    public function httpClient($verb, $url, array $options = [], $origin = false, &$result = null)
    {
        $options['timeout'] || $options['timeout'] = $this->timeout;

        $response = null;
        try {
            /**
             * @var ResponseInterface $result
             */
            $result = $this->getGuzzleHttpClient()
                ->{$verb}($url, $options);

            $response = $result->getBody()
                ->getContents();

            return $origin ? $response : json_decode($response, true);
        } catch (RequestException | \Exception $ex) {
            throw $this->log($ex);
        }
    }

    /**
     * 记录错误日志
     *
     * @param RequestException $ex
     *
     * @return NeoException
     */
    protected function log($ex)
    {
        $data = Debug::simplifyException($ex);

        if ($ex instanceof RequestException) {
            $data['_request'] = \GuzzleHttp\Psr7\str($ex->getRequest());
            if ($ex->hasResponse()) {
                $data['_response'] = \GuzzleHttp\Psr7\str($ex->getResponse());
            }

            $data['_context'] = $ex->getHandlerContext();

            // 是否超时？
            $code = $data['_context']['errno'];
            $msg = $data['_context']['error'] ?: $data['message'];
        } else {
            $code = $ex->getCode();
            $msg = $ex->getMessage();
        }

        NeoLog::error('guzzle', __FUNCTION__, $data);

        return new NeoException($msg, $code, $ex);
    }
}
