<?php

namespace Neo\Pay\WeChat;

use Neo\Pay\PayException;
use Neo\Pay\PayInterface;
use Neo\Str;

/**
 *  微信JS支付
 *
 * Class JsApiPay
 */
class JsApiPay extends Pay implements PayInterface
{
    /**
     * JsApiPay constructor
     *
     * @param array $config 参数
     *                      $config = [
     *                      "app_id" => "wxd930ea5d5a258f4f",
     *                      "partner_id" => "1900000109",
     *                      "app_pay_key" => "192006250b4c09247ec02edce69f6a2d",
     *                      "cert_file_path" => "/tmp/apiclient_cert.pem",
     *                      "key_file_path" => "/tmp/apiclient_key.pem"
     *                      ]
     *
     * @throws \Exception
     */
    public function __construct(array $config)
    {
        parent::__construct($config);
    }

    /**
     * 在微信支付服务后台生成预支付交易单
     *
     * 统一下单 接口
     * @see https://pay.weixin.qq.com/wiki/doc/api/jsapi.php?chapter=9_1
     *
     * @param string $orderId           系统订单号
     * @param string $body              商品或支付单简要描述
     * @param int    $fee               订单总金额，单位为分
     * @param string $notifyAbsoluteUrl 接收微信支付异步通知回调地址
     * @param string $ip                用户端ip
     * @param int    $timeExpire        支付超时时间
     * @param string $openId            微信openid
     *
     * @return array
     */
    public function buildOrder($orderId, $body, $fee, $notifyAbsoluteUrl, $ip, $timeExpire, $openId = '')
    {
        $timeExpire = $timeExpire && $timeExpire > 5 ? time() + $timeExpire * 60 : time() + 900;
        $data = [
            'appid' => $this->app_id,
            'mch_id' => $this->partner_id,
            'nonce_str' => Str::randString(32),
            'body' => $body,
            'out_trade_no' => $orderId,
            'total_fee' => (int) $fee,
            'spbill_create_ip' => $ip,
            'notify_url' => $notifyAbsoluteUrl,
            'trade_type' => 'JSAPI',
            'openid' => $openId ?: '',
            'time_expire' => formatDate('YmdHis', $timeExpire),
        ];
        $data['sign'] = $this->getSign($data);

        $response = $this->getPayData('pay/unifiedorder', $data);

        return [
            $response,
            $this->_buildPrepayQueryParameters($response['prepay_id']),
        ];
    }

    /**
     * 生成预支付参数
     *
     * @param string $prepayId
     *
     * @throws PayException
     * @return array
     */
    private function _buildPrepayQueryParameters($prepayId)
    {
        if (! $prepayId) {
            throw new PayException('Invalid prepayId.');
        }

        $data = [
            'appId' => $this->app_id,
            'timeStamp' => time(),
            'nonceStr' => Str::randString(32),
            'package' => "prepay_id={$prepayId}",
            'signType' => 'MD5',
        ];
        $data['paySign'] = $this->getSign($data);

        // 需要给微信传字符串
        $data['timeStamp'] = (string) $data['timeStamp'];

        return $data;
    }
}
