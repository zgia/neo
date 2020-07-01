<?php

namespace Neo\Pay\WeChat;

use Neo\Exception\ParamException;
use Neo\Exception\WeChatException;
use Neo\Http\Request;
use Neo\NeoLog;
use Neo\Str;
use Spatie\ArrayToXml\ArrayToXml;

/**
 * Class Pay
 */
class Pay extends AbstractPay
{
    // 微信支付URL
    protected $pay_url = 'https://api.mch.weixin.qq.com/';

    protected $app_id;

    protected $app_pay_key;

    protected $partner_id;

    protected $cert_file_path;

    protected $key_file_path;

    // 支付重试时的原始数据
    protected $originRequestData = [];

    /**
     * Pay constructor
     *
     * @param array $config 参数
     *                      $config = [
     *                      "app_id" => "wxd930eaxxxxxxx",
     *                      "partner_id" => "190xxxxxxx",
     *                      "app_pay_key" => "192006250b4c09xxxxxxxxxxxx",
     *                      "cert_file_path" => "/tmp/apiclient_cert.pem",
     *                      "key_file_path" => "/tmp/apiclient_key.pem"
     *                      ]
     *
     * @throws WeChatException
     */
    public function __construct(array $config)
    {
        if (! isset($config['app_id']) || ! isset($config['partner_id']) || ! isset($config['app_pay_key'])) {
            throw new WeChatException('Invalid config array.');
        }

        $this->app_id = $config['app_id'];
        $this->partner_id = $config['partner_id'];
        $this->app_pay_key = $config['app_pay_key'];

        $this->cert_file_path = isset($config['cert_file_path']) ? $config['cert_file_path'] : '';
        $this->key_file_path = isset($config['key_file_path']) ? $config['key_file_path'] : '';

        if (isset($config['timeout'])) {
            $this->timeout = $config['timeout'];
        }
    }

    /**
     * 退款
     *
     * @see  https://pay.weixin.qq.com/wiki/doc/api/jsapi.php?chapter=9_4
     *
     * @param string $orderId       系统支付订单号
     * @param int    $refundFee     退款总金额
     * @param int    $totalFee      订单总金额，单位为分
     * @param string $transactionId 微信生成的订单号，在支付通知中有返回
     * @param int    $suborderid    子订单号
     * @param int    $time          当前时间
     *
     * @return array 微信退款返回
     */
    public function refund($orderId, $refundFee, $totalFee, $transactionId, $suborderid = 0, $time = 0)
    {
        $data = [
            'appid' => $this->app_id,
            'mch_id' => $this->partner_id,
            'nonce_str' => Str::randString(32),
            'op_user_id' => $this->partner_id,
            'out_refund_no' => $orderId . $suborderid . ($time ?: time()),
            'out_trade_no' => $orderId,
            'refund_fee' => $refundFee,
            'total_fee' => $totalFee,
            'transaction_id' => $transactionId,
        ];
        $data['sign'] = $this->getSign($data);

        return $this->getPayData('secapi/pay/refund', $data, ['is_ssl' => true]);
    }

    /**
     *  用户提现
     *
     * @see  https://pay.weixin.qq.com/wiki/doc/api/tools/mch_pay.php?chapter=14_2
     *
     * @param string $tradeno  系统提现码
     * @param int    $openid   提现用户的openid
     * @param int    $amount   提现金额，单位为分
     * @param string $noncestr 随机串
     * @param string $ip       IP地址
     *
     * @return array
     */
    public function withdraw($tradeno, $openid, $amount, $noncestr, $ip = '')
    {
        $data = [
            'mch_appid' => $this->app_id,
            'mchid' => $this->partner_id,
            'nonce_str' => $noncestr,
            'partner_trade_no' => $tradeno,
            'openid' => $openid,
            'check_name' => 'NO_CHECK',
            'amount' => $amount,
            'desc' => '用户提现',
            'spbill_create_ip' => $ip ?: Request::ip(),
        ];
        $data['sign'] = $this->getSign($data);

        return $this->getPayData('mmpaymkttransfers/promotion/transfers', $data, ['is_ssl' => true]);
    }

    /**
     * 提现查询
     *
     * @see https://pay.weixin.qq.com/wiki/doc/api/tools/mch_pay.php?chapter=14_3
     *
     * @param string $tradeno
     * @param string $noncestr
     *
     * @return null|array
     */
    public function withdrawQuery($tradeno, $noncestr)
    {
        $data = [
            'appid' => $this->app_id,
            'mch_id' => $this->partner_id,
            'nonce_str' => $noncestr,
            'partner_trade_no' => $tradeno,
        ];
        $data['sign'] = $this->getSign($data);

        return $this->getPayData('mmpaymkttransfers/gettransferinfo', $data, ['is_ssl' => true]);
    }

    /**
     * 退款查询
     *
     * @see https://pay.weixin.qq.com/wiki/doc/api/jsapi.php?chapter=9_5
     *
     * @param string $outtradeno
     *
     * @return array
     */
    public function refundquery($outtradeno)
    {
        $data = [
            'appid' => $this->app_id,
            'mch_id' => $this->partner_id,
            'nonce_str' => Str::randString(32),
            'out_trade_no' => $outtradeno,
        ];
        $data['sign'] = $this->getSign($data);

        return $this->getPayData('pay/refundquery', $data);
    }

    /**
     * 订单查询
     *
     * @see https://pay.weixin.qq.com/wiki/doc/api/jsapi.php?chapter=9_2
     *
     * @param string $out_trade_no   商户系统内部订单号
     * @param string $transaction_id 微信订单号
     *
     * @return array
     */
    public function orderquery($out_trade_no, $transaction_id = '')
    {
        $data = [
            'appid' => $this->app_id,
            'mch_id' => $this->partner_id,
            'nonce_str' => Str::randString(32),
        ];

        if ($out_trade_no) {
            $data['out_trade_no'] = $out_trade_no;
        }
        if ($transaction_id) {
            $data['transaction_id'] = $transaction_id;
        }

        $data['sign'] = $this->getSign($data);

        return $this->getPayData('pay/orderquery', $data);
    }

    /**
     *  获取请求参数签名
     *
     * @see https://pay.weixin.qq.com/wiki/doc/api/app/app.php?chapter=4_3
     * @see https://pay.weixin.qq.com/wiki/doc/api/tools/mch_pay.php?chapter=4_3
     *
     * @param array $params
     *
     * @return string
     */
    public function getSign(array $params)
    {
        $queryString = $this->buildSignQueryString($params);

        return strtoupper(md5($queryString . '&key=' . $this->app_pay_key));
    }

    /**
     *  验证数据有效性
     *
     * @param array $data
     *
     * @throws ParamException
     * @return bool
     */
    public function verifyData($data)
    {
        if (! isset($data['sign']) || empty($data['sign'])) {
            throw new ParamException('sign签名不存在或为空');
        }

        $sign = $data['sign'];
        unset($data['sign']);
        $calcSign = $this->getSign($data);

        return $calcSign == $sign;
    }

    /**
     *  支付通知
     *
     * @param array $data
     *
     * @throws ParamException
     * @return array
     */
    public function notify($data)
    {
        $this->parseResponseResult($data);

        if ($this->verifyData($data)) {
            return $data;
        }

        throw new ParamException('签名无效');
    }

    /**
     * @param array $data
     * @param array $more
     *
     * @throws ParamException
     */
    protected function parseResponseResult($data, $more = [])
    {
        if (! $data || ! isset($data['return_code'])) {
            throw new ParamException('无效参数。', self::ERR_CODE_WEIXIN_NO_DATA);
        }

        if ($data['return_code'] == 'SUCCESS') {
            if ($data['result_code'] == 'SUCCESS') {
                return;
            }

            $exceptionMessage = "{$data['return_msg']}{$data['err_code_des']}({$data['err_code']})";

            $err_code = strtoupper($data['err_code']);

            if ($err_code == 'SYSTEMERROR' || $err_code == 'BIZERR_NEED_RETRY') {
                $exCode = self::ERR_CODE_WEIXIN_SYSTEMERROR;
            } elseif ($err_code == 'ORDERPAID') {
                $exCode = self::ERR_CODE_WEIXIN_ORDERPAID;
            } else {
                $exCode = self::ERR_CODE_WEIXIN_OTHER_FAIL;
            }
        } else {
            $exceptionMessage = $data['return_msg'];
            $exCode = self::ERR_CODE_WEIXIN_BOTH_FAIL;
        }

        $pe = new ParamException($exceptionMessage, $exCode);
        $pe->setMore($more);

        throw $pe;
    }

    /**
     * 获取微信支付相关数据
     *
     * @see https://pay.weixin.qq.com/wiki/doc/api/jsapi.php?chapter=9_5
     *
     * @param string       $uri
     * @param array|string $data
     * @param array        $options
     * @param string       $responseFormat
     * @param string       $requestFormat
     * @param string       $requestMethod
     *
     * @throws ParamException
     * @return null|array|mixed
     */
    protected function getPayData($uri, $data, $options = [], $responseFormat = self::PAY_DATA_FORMAT_XML, $requestFormat = self::REQUEST_DATA_FORMAT_XML, $requestMethod = 'post')
    {
        NeoLog::info('pay', 'request', func_get_args());

        if ($options['is_ssl']) {
            $options['cert'] = $this->cert_file_path;
            $options['ssl_key'] = $this->key_file_path;
        }

        unset($options['is_ssl']);

        $options['verify'] = false;

        // 默认开始
        $code = -1;
        $message = '';
        $retry = 1;

        while (in_array(
            $code,
            [
                -1,
                self::ERR_CODE_WEIXIN_SYSTEMERROR,
                self::ERR_CODE_TIMEOUT,
            ]
        ) && $retry <= $this->retryTimes) {
            try {
                if (is_array($data)) {
                    if ($requestFormat == self::REQUEST_DATA_FORMAT_JSON) {
                        $data = json_encode($data);
                    } elseif ($requestFormat == self::REQUEST_DATA_FORMAT_XML) {
                        $data = ArrayToXml::convert($data);
                    }

                    $this->originRequestData = ['data' => $data, 'format' => $requestFormat];
                }

                $response = $this->httpRequest(
                    $this->pay_url . $uri,
                    $data,
                    $options,
                    $responseFormat,
                    $requestMethod
                );

                if ($responseFormat != self::PAY_DATA_FORMAT_ORIGINAL) {
                    $response || $response = [];
                    NeoLog::info('pay', 'response', $response);

                    $more = [
                        'toweixin' => $data,
                        'fromweixin' => $response,
                    ];

                    $this->parseResponseResult($response, $more);
                }

                return $response;
            } catch (WeChatException $ex) {
                NeoLog::error('pay', 'exception', $ex->getMessage());
                if ($ex->getMore()) {
                    NeoLog::error('pay', 'exception', $ex->getMore());
                }

                $code = $ex->getCode();
                $message = $ex->getMessage();
            }

            ++$retry;
        }

        throw new ParamException($message, $code);
    }
}
