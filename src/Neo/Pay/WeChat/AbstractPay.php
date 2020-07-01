<?php

namespace Neo\Pay\WeChat;

use GuzzleHttp\Client as GuzzleHttpClient;
use Neo\Exception\NeoException;
use Neo\Exception\WeChatException;
use Neo\NeoLog;
use Neo\Traiter\GuzzleHttpClientTraiter;

/**
 * Class AbstractPay
 */
abstract class AbstractPay
{
    use GuzzleHttpClientTraiter;

    /**
     * 支付数据返回格式
     */
    // 以xml格式返回
    const PAY_DATA_FORMAT_XML = 'xml';

    // 以json格式返回
    const PAY_DATA_FORMAT_JSON = 'json';

    // 原始返回，不做任何处理
    const PAY_DATA_FORMAT_ORIGINAL = 'original';

    // 以xml格式请求
    const REQUEST_DATA_FORMAT_XML = 'xml';

    // 以json格式请求
    const REQUEST_DATA_FORMAT_JSON = 'json';

    // 原始数据请求，不做任何处理
    const REQUEST_DATA_FORMAT_ORIGINAL = 'original';

    // 支付URL
    protected $pay_url = '';

    /**
     * HTTP 连接超时
     *
     * @var int
     */
    protected $timeout = 3;

    /**
     * HTTP 连接重试次数
     *
     * @var int
     */
    protected $retryTimes = 3;

    /**
     * HTTP 连接超时错误码
     */
    const ERR_CODE_TIMEOUT = 28;

    /**
     * 微信返回SYSTEMERROR，BIZERR_NEED_RETRY
     * SYSTEMERROR： 接口返回错误，系统超时等，请不要更换商户退款单号，请使用相同参数再次调用API。
     * BIZERR_NEED_RETRY：退款业务流程错误，需要商户触发重试来解决，并发情况下，业务被拒绝，商户重试即可解决，请不要更换商户退款单号，请使用相同参数再次调用API。
     */
    const ERR_CODE_WEIXIN_SYSTEMERROR = 900999;

    const ERR_CODE_WEIXIN_BIZERR_NEED_RETRY = 900999;

    /**
     * return_code 和 result_code 都为FAIL
     */
    const ERR_CODE_WEIXIN_BOTH_FAIL = 900000;

    /**
     * return_code 为 SUCCESS 和 result_code 为FAIL
     * 但是err_code不是SYSTEMERROR或者BIZERR_NEED_RETRY
     */
    const ERR_CODE_WEIXIN_OTHER_FAIL = 900100;

    /**
     * 商户订单已支付，无需重复操作
     */
    const ERR_CODE_WEIXIN_ORDERPAID = 900200;

    /**
     * 没有数据返回
     */
    const ERR_CODE_WEIXIN_NO_DATA = 900500;

    /**
     * 设置重试次数
     *
     * @param int $times
     */
    public function setRetryTimes($times = 3)
    {
        $this->retryTimes = $times;
    }

    /**
     * 设置支付URL
     *
     * @param string $url
     */
    public function setPayURL($url)
    {
        $this->pay_url = $url;
    }

    /**
     * 使用私钥签名数据
     *
     * @param string $signStr    待签名的数据
     * @param string $privateKey 私钥
     * @param int    $algo       签名使用的算法
     *
     * @return string
     */
    public function signDataByPrivateKey($signStr, $privateKey, $algo = OPENSSL_ALGO_SHA1)
    {
        // 加载私钥
        $privatekey = openssl_pkey_get_private(file_get_contents($privateKey));

        // 签名
        $signature = '';
        openssl_sign($signStr, $signature, $privatekey, $algo);

        openssl_free_key($privatekey);

        return base64_encode($signature);
    }

    /**
     * 使用公钥验证签名
     *
     * @param string $signedStr     待验证的数据
     * @param string $authorization 用于验证的签名
     * @param string $publicKey     公钥
     * @param int    $algo          签名使用的算法
     *
     * @return bool
     */
    public function verifyDataByPublicKey($signedStr, $authorization, $publicKey, $algo = OPENSSL_ALGO_SHA1)
    {
        $publickey = openssl_pkey_get_public(file_get_contents($publicKey));

        // 获取签名
        $authorization = base64_decode($authorization);

        // 验签
        $verify = openssl_verify($signedStr, $authorization, $publickey, $algo);

        openssl_free_key($publickey);

        return $verify == 1;
    }

    /**
     * 生成待签名字符串
     *
     * @see https://pay.weixin.qq.com/wiki/doc/api/app.php?chapter=4_3
     * @see https://pay.weixin.qq.com/wiki/doc/api/mch_pay.php?chapter=4_3
     *
     * @param array $queryParams
     *
     * @return string
     */
    protected function buildSignQueryString(array $queryParams)
    {
        $queryParams = array_filter(
            $queryParams,
            function ($v, $k) {
                return $v != '' && ! is_array($v);
            },
            ARRAY_FILTER_USE_BOTH
        );
        ksort($queryParams);

        return urldecode(http_build_query($queryParams));
    }

    /**
     * Http Client
     *
     * @return \GuzzleHttp\Client
     */
    protected function getHttpClient()
    {
        if ($this->httpClient == null) {
            $this->httpClient = new GuzzleHttpClient([
                'timeout' => $this->timeout,
            ]);
        }

        return $this->httpClient;
    }

    /**
     * Http Request
     *
     * @param            $url
     * @param null       $params
     * @param null|array $options
     * @param string     $responseFormat
     * @param string     $requestMethod
     *
     * @return null|array|mixed|string
     */
    public function httpRequest($url, $params = null, array $options = null, $responseFormat = self::PAY_DATA_FORMAT_XML, $requestMethod = 'post')
    {
        $response = null;

        try {
            $form_params = [];

            if ($requestMethod == 'post') {
                if ($params) {
                    if (is_array($params)) {
                        $form_params = ['form_params' => $params];
                    } else {
                        $form_params = ['body' => $params];
                    }
                }

                if (is_array($options)) {
                    $form_params = array_merge($form_params, $options);
                }
            } elseif ($requestMethod == 'get') {
                $form_param = http_build_query($params);

                if (strpos($url, '?') == false) {
                    $url = $url . '?' . $form_param;
                }
            } else {
                throw new NeoException(__f('Method(%s) Not Allowed.', $requestMethod), 405);
            }

            NeoLog::info('pay', 'request', [$requestMethod, $url, $form_params]);

            $response = $this->httpClient($requestMethod, $url, $form_params);

            NeoLog::info('pay', 'response', $response);

            // 格式化处理
            if ($responseFormat == self::PAY_DATA_FORMAT_XML) {
                $response = (array) simplexml_load_string($response, 'SimpleXMLElement', LIBXML_NOCDATA);
            } elseif ($responseFormat == self::PAY_DATA_FORMAT_ORIGINAL) {
                // 原样返回
            } else {
                $response = json_decode($response, true);
            }
        } catch (NeoException | \Exception $ex) {
            throw new WeChatException($ex->getMessage(), $ex->getCode());
        }

        return $response;
    }
}
