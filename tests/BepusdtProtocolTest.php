<?php

use PHPUnit\Framework\TestCase;

final class BepusdtProtocolTest extends TestCase
{
    private const TOKEN = 'test-secret';

    public function testSignatureKeepsZeroAndExcludesOnlyDocumentedFields(): void
    {
        $payload = [
            'z' => '',
            'amount' => 0,
            'name' => 'order',
            'nullable' => null,
            'signature' => 'ignored',
        ];

        self::assertSame(md5('amount=0&name=order'.self::TOKEN), BepusdtProtocol::sign($payload, self::TOKEN));
    }

    public function testGatewayUrlMustBeCleanHttpsUrl(): void
    {
        self::assertSame('https://pay.example.com/', BepusdtProtocol::normalizeGatewayUrl('https://pay.example.com'));

        foreach ([
            'http://pay.example.com',
            'https://user:pass@pay.example.com',
            'https://pay.example.com/?token=secret',
            'https://pay.example.com/#fragment',
        ] as $url) {
            try {
                BepusdtProtocol::normalizeGatewayUrl($url);
                self::fail('非法 URL 应被拒绝：'.$url);
            } catch (BepusdtProtocolException $e) {
                self::assertSame(400, $e->getHttpStatus());
            }
        }
    }

    public function testCreateResponseIsStrictlyValidated(): void
    {
        $response = [
            'status_code' => 200,
            'data' => [
                'fiat' => 'CNY',
                'trade_type' => 'usdt.ton',
                'trade_id' => 'gateway-1',
                'order_id' => '2026072800000000001',
                'status' => 1,
                'amount' => '10.00',
                'actual_amount' => '1.2345',
                'token' => 'wallet-address',
                'expiration_time' => 1200,
                'payment_url' => 'https://pay.example.com/checkout/1',
            ],
        ];

        self::assertSame(
            'gateway-1',
            BepusdtProtocol::validateCreateResponse(
                $response,
                '2026072800000000001',
                'usdt.ton',
                'CNY',
                '10'
            )['trade_id']
        );

        $wrongAmount = $response;
        $wrongAmount['data']['amount'] = '11.00';
        try {
            BepusdtProtocol::validateCreateResponse(
                $wrongAmount,
                '2026072800000000001',
                'usdt.ton',
                'CNY',
                '10.00'
            );
            self::fail('网关返回金额与请求不一致时必须失败');
        } catch (BepusdtProtocolException $e) {
            self::assertSame(502, $e->getHttpStatus());
        }

        $response['data']['order_id'] = 'another-order';
        $this->expectException(BepusdtProtocolException::class);
        BepusdtProtocol::validateCreateResponse($response, '2026072800000000001', 'usdt.ton');
    }

    /**
     * @dataProvider callbackStatusProvider
     */
    public function testSignedCallbackStatusIsParsed(int $status, string $blockId): void
    {
        $payload = $this->callbackPayload($status, $blockId);
        $payload['signature'] = BepusdtProtocol::sign($payload, self::TOKEN);

        $event = BepusdtProtocol::parseCallback(
            json_encode($payload, JSON_UNESCAPED_SLASHES),
            self::TOKEN,
            '2026072800000000001'
        );

        self::assertSame($status, $event['status']);
        self::assertSame($blockId, $event['block_transaction_id']);
    }

    public function callbackStatusProvider(): array
    {
        return [
            'waiting does not require block hash' => [1, ''],
            'success requires block hash' => [2, '0xabc'],
            'timeout does not require block hash' => [3, ''],
        ];
    }

    /**
     * @dataProvider callbackDispositionProvider
     */
    public function testOnlySuccessStatusHasSettlementDisposition(int $status, string $expected): void
    {
        self::assertSame($expected, BepusdtProtocol::callbackDisposition(['status'=>$status]));
    }

    public function callbackDispositionProvider(): array
    {
        return [
            'waiting is read-only' => [1, 'wait'],
            'success settles' => [2, 'settle'],
            'timeout only records expiry' => [3, 'timeout'],
        ];
    }

    public function testCallbackRejectsBadSignature(): void
    {
        $payload = $this->callbackPayload(2, '0xabc');
        $payload['signature'] = str_repeat('0', 32);

        try {
            BepusdtProtocol::parseCallback(json_encode($payload), self::TOKEN, '2026072800000000001');
            self::fail('错误签名必须被拒绝');
        } catch (BepusdtProtocolException $e) {
            self::assertSame(403, $e->getHttpStatus());
        }
    }

    public function testSuccessCallbackRequiresBlockHash(): void
    {
        $payload = $this->callbackPayload(2, '');
        $payload['signature'] = BepusdtProtocol::sign($payload, self::TOKEN);

        $this->expectException(BepusdtProtocolException::class);
        BepusdtProtocol::parseCallback(json_encode($payload), self::TOKEN, '2026072800000000001');
    }

    private function callbackPayload(int $status, string $blockId): array
    {
        return [
            'order_id' => '2026072800000000001',
            'trade_id' => 'gateway-1',
            'amount' => '10.00',
            'actual_amount' => '1.2345',
            'token' => 'wallet-address',
            'status' => $status,
            'block_transaction_id' => $blockId,
        ];
    }
}
