<?php

namespace Neo\Pay;

use Neo\Exception\ParamException;

/**
 * Interface PayInterface
 */
interface PayInterface
{
    /**
     *  获取请求签名串
     *
     * @param array $queryParams
     *
     * @return string
     */
    public function getSign(array $queryParams);

    /**
     *  创建订单
     *
     * @param string $payOrderId
     * @param string $body
     * @param int    $fee
     * @param string $notifyAbsoluteUrl
     * @param string $ip
     * @param int    $timeExpire
     *
     * @return mixed
     */
    public function buildOrder($payOrderId, $body, $fee, $notifyAbsoluteUrl, $ip, $timeExpire);

    /**
     *  请求退款
     *
     * @param string $payId         支付id
     * @param int    $refundFee     退款总金额
     * @param int    $totalFee      订单总金额，单位为分
     * @param string $transactionId 支付平台流水id
     *
     * @return mixed
     */
    public function refund($payId, $refundFee, $totalFee, $transactionId);

    /**
     *  验证数据有效性
     *
     * @param array $data
     *
     * @throws ParamException
     * @return bool
     */
    public function verifyData($data);

    /**
     *  支付通知
     *
     * @param array $data
     *
     * @throws ParamException
     * @return array
     */
    public function notify($data);
}
