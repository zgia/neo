<?php

namespace Neo\Traiter;

use GuzzleHttp\Client as GuzzleHttpClient;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request;
use Neo\Debug;
use Neo\Exception\LogicException;
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
    public function setTimeout(int $timeout)
    {
        $this->timeout = $timeout;
    }

    /**
     * 设置是否重连超时时间，每次加载后，需要重新设置
     *
     * @param bool $reload
     */
    public function setReload(bool $reload)
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
            $this->httpClient = new GuzzleHttpClient(['timeout' => $this->timeout]);

            $this->reload = false;
        }

        return $this->httpClient;
    }

    /**
     * POST
     *
     * @param string $url
     * @param array  $params
     * @param bool   $origin  返回数据是否需要json_decode
     * @param array  $options
     *
     * @throws LogicException
     * @return null|mixed
     */
    public function post(string $url, array $params = [], array $options = [], bool $origin = false)
    {
        if ($params) {
            $options['form_params'] = $params;
        }
        return $this->doRequest('post', $url, $options, $origin);
    }

    /**
     * GET
     *
     * @param string $url
     * @param array  $params
     * @param array  $options
     * @param bool   $origin  返回数据是否需要json_decode
     *
     * @throws LogicException
     * @return null|mixed
     */
    public function get(string $url, array $params = [], array $options = [], bool $origin = false)
    {
        if ($params) {
            $options['query'] = $params;
        }
        return $this->doRequest('get', $url, $options, $origin);
    }

    /**
     * 发起HTTP请求
     *
     * @param string            $method  POST, GET, DELETE, etc
     * @param string            $url     URL
     * @param array             $options More options
     * @param bool              $origin  返回数据是否需要json_decode
     * @param ResponseInterface $result  Response对象
     *
     * @throws LogicException
     * @return null|array|string
     */
    public function doRequest(string $method, string $url, array $options = [], bool $origin = false, ResponseInterface &$result = null)
    {
        $response = null;
        try {
            $request = new Request($method, $url);

            /**
             * @var ResponseInterface $result
             */
            $result = $this->getGuzzleHttpClient()->send($request, $options);

            $response = $result->getBody()->getContents();

            return $origin ? $response : json_decode($response, true);
        } catch (RequestException|\Exception $ex) {
            throw $this->log($ex);
        }
    }

    /**
     * 记录错误日志
     *
     * @param RequestException $ex
     *
     * @return LogicException
     */
    protected function log($ex)
    {
        $data = Debug::simplifyException($ex);

        if ($ex instanceof RequestException) {
            $data['_request'] = \GuzzleHttp\Psr7\Message::toString($ex->getRequest());
            if ($ex->hasResponse()) {
                $data['_response'] = \GuzzleHttp\Psr7\Message::toString($ex->getResponse());
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

        return new LogicException($msg, $code, $ex);
    }
}
