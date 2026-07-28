<?php

class BepusdtProtocolException extends \RuntimeException
{
    private $httpStatus;

    public function __construct(string $message, int $httpStatus = 400)
    {
        parent::__construct($message);
        $this->httpStatus = $httpStatus;
    }

    public function getHttpStatus(): int
    {
        return $this->httpStatus;
    }
}

final class BepusdtProtocol
{
    public static function normalizeTradeType(string $typename): string
    {
        return str_replace(['-', '_'], '.', strtolower(trim($typename)));
    }

    public static function sign(array $parameters, string $token): string
    {
        ksort($parameters, SORT_STRING);
        $parts = [];

        foreach ($parameters as $key => $value) {
            if ($key === 'signature' || $value === null || $value === '') {
                continue;
            }
            if (is_array($value) || is_object($value) || is_resource($value)) {
                throw new BepusdtProtocolException('签名字段类型不合法');
            }
            if (is_bool($value)) {
                $value = $value ? 'true' : 'false';
            }
            $parts[] = $key . '=' . (string) $value;
        }

        return md5(implode('&', $parts) . $token);
    }

    public static function normalizeGatewayUrl(string $url): string
    {
        $url = trim($url);
        $parts = parse_url($url);
        if (!is_array($parts)
            || strtolower((string) ($parts['scheme'] ?? '')) !== 'https'
            || empty($parts['host'])
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['query'])
            || isset($parts['fragment'])) {
            throw new BepusdtProtocolException('BEpusdt 接口地址必须是无凭据、查询参数和片段的 HTTPS URL');
        }

        return rtrim($url, '/') . '/';
    }

    public static function validateCreateResponse(
        array $response,
        string $expectedOrderId,
        string $expectedTradeType,
        string $expectedFiat = 'CNY',
        ?string $expectedAmount = null
    ): array {
        if (($response['status_code'] ?? null) !== 200 || !is_array($response['data'] ?? null)) {
            throw new BepusdtProtocolException('BEpusdt 返回业务错误', 502);
        }

        $data = $response['data'];
        foreach (['fiat', 'trade_type', 'trade_id', 'order_id', 'status', 'amount', 'actual_amount', 'token', 'expiration_time', 'payment_url'] as $field) {
            if (!array_key_exists($field, $data)) {
                throw new BepusdtProtocolException('BEpusdt 响应缺少字段：' . $field, 502);
            }
        }

        if ((string) $data['order_id'] !== $expectedOrderId
            || self::normalizeTradeType((string) $data['trade_type']) !== $expectedTradeType
            || strtoupper((string) $data['fiat']) !== strtoupper($expectedFiat)
            || (int) $data['status'] !== 1) {
            throw new BepusdtProtocolException('BEpusdt 响应与请求不一致', 502);
        }
        if ($expectedAmount !== null && !self::decimalEquals($data['amount'], $expectedAmount)) {
            throw new BepusdtProtocolException('BEpusdt 响应金额与请求不一致', 502);
        }

        if (!self::isNonEmptyString($data['trade_id'])
            || !self::isNonEmptyString($data['token'])
            || !self::isPositiveDecimal($data['amount'])
            || !self::isPositiveDecimal($data['actual_amount'])
            || !is_numeric($data['expiration_time'])
            || (int) $data['expiration_time'] <= 0
            || !self::isHttpsUrl((string) $data['payment_url'])) {
            throw new BepusdtProtocolException('BEpusdt 响应字段不合法', 502);
        }

        return $data;
    }

    public static function parseCallback(string $rawBody, string $token, string $expectedOrderId): array
    {
        $data = json_decode($rawBody, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($data) || self::isList($data)) {
            throw new BepusdtProtocolException('回调 JSON 不合法', 400);
        }

        foreach (['signature', 'order_id', 'trade_id', 'amount', 'actual_amount', 'token', 'status', 'block_transaction_id'] as $field) {
            if (!array_key_exists($field, $data)) {
                throw new BepusdtProtocolException('回调缺少字段：' . $field, 400);
            }
        }

        if (!is_string($data['signature']) || strlen($data['signature']) !== 32) {
            throw new BepusdtProtocolException('回调签名格式不合法', 403);
        }

        $expectedSignature = self::sign($data, $token);
        if (!hash_equals($expectedSignature, strtolower($data['signature']))) {
            throw new BepusdtProtocolException('回调签名错误', 403);
        }

        if ((string) $data['order_id'] !== $expectedOrderId) {
            throw new BepusdtProtocolException('回调订单号不匹配', 400);
        }

        if (!is_int($data['status']) || !in_array($data['status'], [1, 2, 3], true)
            || !self::isNonEmptyString($data['trade_id'])
            || !self::isNonEmptyString($data['token'])
            || !self::isNonNegativeDecimal($data['amount'])
            || !self::isNonNegativeDecimal($data['actual_amount'])
            || (!is_string($data['block_transaction_id']) && $data['block_transaction_id'] !== null)) {
            throw new BepusdtProtocolException('回调字段类型不合法', 400);
        }

        if ($data['status'] === 2 && trim((string) $data['block_transaction_id']) === '') {
            throw new BepusdtProtocolException('支付成功回调缺少链上交易哈希', 400);
        }

        return [
            'status' => $data['status'],
            'trade_id' => (string) $data['trade_id'],
            'block_transaction_id' => trim((string) $data['block_transaction_id']),
        ];
    }

    public static function callbackDisposition(array $event): string
    {
        $status = $event['status'] ?? null;
        if ($status === 1) return 'wait';
        if ($status === 2) return 'settle';
        if ($status === 3) return 'timeout';
        throw new BepusdtProtocolException('未知回调状态', 400);
    }

    public static function isHttpUrl(string $url): bool
    {
        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            return false;
        }
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        return $scheme === 'http' || $scheme === 'https';
    }

    public static function isHttpsUrl(string $url): bool
    {
        return self::isHttpUrl($url)
            && strtolower((string) parse_url($url, PHP_URL_SCHEME)) === 'https';
    }

    private static function isPositiveDecimal($value): bool
    {
        return self::isNonNegativeDecimal($value) && (float) $value > 0;
    }

    private static function isNonNegativeDecimal($value): bool
    {
        return (is_string($value) || is_int($value) || is_float($value))
            && preg_match('/^(?:0|[1-9]\d*)(?:\.\d+)?$/', (string) $value) === 1;
    }

    private static function decimalEquals($left, $right): bool
    {
        if (!self::isNonNegativeDecimal($left) || !self::isNonNegativeDecimal($right)) {
            return false;
        }
        $normalize = static function ($value): string {
            $parts = explode('.', (string) $value, 2);
            $integer = ltrim($parts[0], '0');
            if ($integer === '') $integer = '0';
            $fraction = isset($parts[1]) ? rtrim($parts[1], '0') : '';
            return $fraction === '' ? $integer : $integer.'.'.$fraction;
        };
        return $normalize($left) === $normalize($right);
    }

    private static function isNonEmptyString($value): bool
    {
        return is_string($value) && trim($value) !== '';
    }

    private static function isList(array $value): bool
    {
        if ($value === []) {
            return true;
        }
        return array_keys($value) === range(0, count($value) - 1);
    }
}

final class BepusdtHttpClient
{
    private const MAX_RESPONSE_BYTES = 1048576;

    public static function postJson(string $url, array $payload): array
    {
        $body = json_encode($payload, JSON_UNESCAPED_SLASHES);
        if ($body === false) {
            throw new BepusdtProtocolException('BEpusdt 请求序列化失败', 500);
        }

        $ch = curl_init($url);
        if ($ch === false) {
            throw new BepusdtProtocolException('BEpusdt HTTP 客户端初始化失败', 500);
        }

        $response = '';
        $tooLarge = false;
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Connection: close',
                'Content-Type: application/json',
            ],
            CURLOPT_WRITEFUNCTION => static function ($handle, string $chunk) use (&$response, &$tooLarge): int {
                if (strlen($response) + strlen($chunk) > self::MAX_RESPONSE_BYTES) {
                    $tooLarge = true;
                    return 0;
                }
                $response .= $chunk;
                return strlen($chunk);
            },
        ]);

        $ok = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($tooLarge) {
            throw new BepusdtProtocolException('BEpusdt 响应超过大小限制', 502);
        }
        if ($ok === false) {
            error_log('BEpusdt request failed: curl_errno=' . $errno . ' error=' . $error);
            throw new BepusdtProtocolException('无法连接 BEpusdt 网关', 502);
        }
        if ($status < 200 || $status >= 300) {
            error_log('BEpusdt request failed: http_status=' . $status);
            throw new BepusdtProtocolException('BEpusdt 网关返回异常 HTTP 状态', 502);
        }
        if ($response === '') {
            throw new BepusdtProtocolException('BEpusdt 网关返回空响应', 502);
        }

        $decoded = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
            throw new BepusdtProtocolException('BEpusdt 网关返回非法 JSON', 502);
        }

        return $decoded;
    }
}
