<?php

class PayPalClient
{
    private static $api_url = [
        0 => 'https://api-m.paypal.com',
        1 => 'https://api-m.sandbox.paypal.com',
    ];

    private $gateway_url;
    private $client_id;
    private $client_secret;
    private $access_token;

    public function __construct($client_id, $client_secret, $mode)
    {
        $this->gateway_url = self::$api_url[$mode];
        $this->client_id = $client_id;
        $this->client_secret = $client_secret;
        $this->access_token = $this->getAccessToken();
    }

    //获取Token
    public function getAccessToken(){
        $path = '/v1/oauth2/token';
        $post = 'grant_type=client_credentials';
        $result = $this->curl($this->gateway_url . $path, $post, true);
        $this->access_token = $result['access_token'];
        return $this->access_token;
    }

    //创建订单
    public function createOrder($params){
        $path = '/v2/checkout/orders';
        return $this->curl($this->gateway_url . $path, $params);
    }

    //支付订单
    public function captureOrder($id){
        $path = '/v2/checkout/orders/'.$id.'/capture';
        return $this->curl($this->gateway_url . $path, '');
    }

    //查询订单
    public function orderDetail($id){
        $path = '/v2/checkout/orders/'.$id;
        return $this->curl($this->gateway_url . $path);
    }

    //查询支付
    public function paymentDetail($capture_id){
        $path = '/v2/payments/captures/'.$capture_id;
        return $this->curl($this->gateway_url . $path);
    }

    //退款
    public function refundPayment($capture_id, $params){
        $path = '/v2/payments/captures/'.$capture_id.'/refund';
        return $this->curl($this->gateway_url . $path, $params);
    }

    //退款查询
    public function refundDetail($refund_id){
        $path = '/v2/payments/refunds/'.$refund_id;
        return $this->curl($this->gateway_url . $path);
    }

    public static function isAllowedWebhookCertificateUrl($url)
    {
        if(!is_string($url) || $url === '' || strlen($url) > 500){
            return false;
        }

        $parts = parse_url($url);
        if($parts === false || !isset($parts['scheme'], $parts['host'], $parts['path'])){
            return false;
        }
        if(strtolower($parts['scheme']) !== 'https' || isset($parts['user']) || isset($parts['pass']) ||
            isset($parts['port']) || isset($parts['query']) || isset($parts['fragment'])){
            return false;
        }

        $allowedHosts = [
            'api.paypal.com',
            'api-m.paypal.com',
            'api.sandbox.paypal.com',
            'api-m.sandbox.paypal.com',
        ];
        if(!in_array(strtolower($parts['host']), $allowedHosts, true)){
            return false;
        }

        return preg_match('#^/v1/notifications/certs/[A-Za-z0-9._-]+$#D', $parts['path']) === 1;
    }

    public function verifyWebhookSignature(array $headers, $webhookId, array $event)
    {
        $required = ['auth_algo', 'cert_url', 'transmission_id', 'transmission_sig', 'transmission_time'];
        foreach($required as $key){
            if(!isset($headers[$key]) || !is_string($headers[$key]) || $headers[$key] === ''){
                throw new InvalidArgumentException('PayPal webhook签名参数不完整');
            }
        }
        if(!self::isAllowedWebhookCertificateUrl($headers['cert_url'])){
            throw new InvalidArgumentException('PayPal webhook证书地址不可信');
        }
        if(!is_string($webhookId) || $webhookId === ''){
            throw new InvalidArgumentException('PayPal webhook ID未配置');
        }

        $payload = [
            'auth_algo' => $headers['auth_algo'],
            'cert_url' => $headers['cert_url'],
            'transmission_id' => $headers['transmission_id'],
            'transmission_sig' => $headers['transmission_sig'],
            'transmission_time' => $headers['transmission_time'],
            'webhook_id' => $webhookId,
            'webhook_event' => $event,
        ];
        $result = $this->curl($this->gateway_url.'/v1/notifications/verify-webhook-signature', $payload);
        return isset($result['verification_status']) && $result['verification_status'] === 'SUCCESS';
    }

    private function curl($url, $data = null, $auth = false)
    {
        $header[] = 'Accept: application/json';
        $header[] = 'Content-Type: application/json';
        if(!empty($this->access_token))
        {
            $header[] = 'Authorization: Bearer '.$this->access_token;
        }
        if($data !== null && is_array($data)){
            $data = json_encode($data);
        }

        $curlVersion = curl_version();
        $ua = 'PayPalSDK/PayPal-PHP-SDK 1.14.0 (platform-ver='.PHP_VERSION.'; os='.str_replace(' ', '_', php_uname('s') . ' ' . php_uname('r')).'; machine='.php_uname('m').'; curl='.$curlVersion['version'].')';

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_FAILONERROR, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        if($data !== null){
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLOPT_USERAGENT, $ua);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        // 获取token
        if ($auth) {
            curl_setopt($ch, CURLOPT_USERPWD, $this->client_id.':'.$this->client_secret);
        }
        $response = curl_exec($ch);
        if (curl_errno($ch)) {
            throw new Exception('curl error: '.curl_error($ch));
        }
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $result = json_decode($response, true);
        if($httpCode>=200 && $httpCode<300){
            return $result;
        }else{
            if(isset($result['error_description'])){
                throw new Exception('['.$result['error'].']'.$result['error_description']);
            }elseif(isset($result['message'])){
                throw new Exception('['.$result['name'].']'.$result['message']);
            }else{
                throw new Exception('返回数据解析失败(httpCode='.$httpCode.')');
            }
        }
    }
}
