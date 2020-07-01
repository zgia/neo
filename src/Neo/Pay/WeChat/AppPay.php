<?php

namespace Neo\Pay\WeChat;

use Neo\Pay\PayInterface;
use Neo\Str;

/**
 *  微信APP支付
 *
 * Class AppPay
 */
class AppPay extends Pay implements PayInterface
{
    /**
     * AppPay constructor
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
     * 创建订单
     *
     * @see https://pay.weixin.qq.com/wiki/doc/api/app.php?chapter=9_1
     *
     * @param string $payOrderId        系统订单号
     * @param string $body              商品或支付单简要描述
     * @param int    $fee               订单总金额，单位为分
     * @param string $notifyAbsoluteUrl 接收微信支付异步通知回调地址
     * @param string $ip                用户端ip
     * @param int    $timeExpire
     *
     * @throws \Exception
     * @return array
     */
    public function buildOrder($payOrderId, $body, $fee, $notifyAbsoluteUrl, $ip, $timeExpire)
    {
        $timeExpire = $timeExpire && $timeExpire > 5 ? time() + $timeExpire * 60 : time() + 900;
        $data = [
            'appid' => $this->app_id,
            'mch_id' => $this->partner_id,
            'nonce_str' => Str::randString(32),
            'body' => $body,
            'out_trade_no' => $payOrderId,
            'total_fee' => (int) $fee,
            'spbill_create_ip' => $ip,
            'notify_url' => $notifyAbsoluteUrl,
            'trade_type' => 'APP',
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
     * @throws \Exception
     * @return array
     */
    private function _buildPrepayQueryParameters($prepayId)
    {
        if (! $prepayId) {
            throw new \Exception('Invalid prepayId.');
        }

        $data = [
            'appid' => $this->app_id,
            'partnerid' => $this->partner_id,
            'prepayid' => $prepayId,
            'noncestr' => Str::randString(32),
            'timestamp' => time(),
            'package' => 'Sign=WXPay',
        ];
        $data['sign'] = $this->getSign($data);

        return $data;
    }
}
