<?php
declare (strict_types = 1);

namespace app\library\payment\drivers;

use app\library\payment\PaymentDriver;
use think\facade\Log;

class CaihongPay extends PaymentDriver
{
    public function createOrder(array $order): array
    {
        $apiUrl = $this->config['api_url'] ?? '';
        $pid = $this->config['pid'] ?? '';
        $key = $this->config['key'] ?? '';

        $params = [
            'pid' => $pid,
            'type' => $order['pay_type'] ?? 'alipay',
            'out_trade_no' => $order['order_no'],
            'notify_url' => $order['notify_url'] ?? '',
            'return_url' => $order['return_url'] ?? '',
            'name' => $order['product_name'] ?? '商品支付',
            'money' => $order['amount'],
            'clientip' => $order['client_ip'] ?? request()->ip(),
        ];

        $params['sign'] = $this->generateSign($params, $key);
        $params['sign_type'] = 'MD5';

        $result = $this->httpPost($apiUrl . 'mapi.php', $params);

        Log::info('caihong_pay_create_order', [
            'params' => $params,
            'result' => $result,
        ]);

        if ($result && is_array($result) && ($result['code'] ?? 0) == 1) {
            return [
                'success' => true,
                'pay_url' => $result['payurl'] ?? '',
                'trade_no' => $result['trade_no'] ?? '',
                'raw' => $result,
            ];
        }

        return [
            'success' => false,
            'message' => $result['msg'] ?? '支付下单失败',
            'raw' => $result,
        ];
    }

    public function queryOrder(string $orderNo): array
    {
        $apiUrl = $this->config['api_url'] ?? '';
        $pid = $this->config['pid'] ?? '';
        $key = $this->config['key'] ?? '';

        $params = [
            'pid' => $pid,
            'out_trade_no' => $orderNo,
        ];

        $params['sign'] = $this->generateSign($params, $key);
        $params['sign_type'] = 'MD5';

        $result = $this->httpPost($apiUrl . 'api.php?act=order', $params);

        Log::info('caihong_pay_query_order', [
            'order_no' => $orderNo,
            'result' => $result,
        ]);

        if ($result && is_array($result) && ($result['code'] ?? 0) == 1) {
            return [
                'success' => true,
                'trade_status' => $result['status'] ?? 0,
                'trade_no' => $result['trade_no'] ?? '',
                'pay_time' => $result['pay_time'] ?? '',
                'raw' => $result,
            ];
        }

        return [
            'success' => false,
            'message' => $result['msg'] ?? '订单查询失败',
            'raw' => $result,
        ];
    }

    public function refund(array $order): array
    {
        return [
            'success' => false,
            'message' => '暂不支持退款接口',
        ];
    }

    public function verifyNotify(array $data): bool
    {
        $key = $this->config['key'] ?? '';

        if (!isset($data['sign']) || !isset($data['pid']) || !isset($data['trade_no']) || !isset($data['out_trade_no'])) {
            return false;
        }

        $sign = $data['sign'];
        $calculatedSign = $this->generateSign($data, $key);

        return $sign === $calculatedSign;
    }

    public function getNotifyData(): array
    {
        return request()->param();
    }

    protected function generateSign(array $params, string $key): string
    {
        ksort($params);
        reset($params);

        $signStr = '';
        foreach ($params as $k => $v) {
            if ($k == 'sign' || $k == 'sign_type' || $v === '' || $v === null) {
                continue;
            }
            $signStr .= $k . '=' . $v . '&';
        }
        $signStr = trim($signStr, '&');
        $signStr .= $key;

        return md5($signStr);
    }

    protected function httpPost(string $url, array $params): ?array
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode != 200 || !$response) {
            Log::error('caihong_pay_http_error', [
                'url' => $url,
                'http_code' => $httpCode,
                'response' => $response,
            ]);
            return null;
        }

        $result = json_decode($response, true);
        if (!is_array($result)) {
            parse_str($response, $result);
        }

        return $result;
    }
}
