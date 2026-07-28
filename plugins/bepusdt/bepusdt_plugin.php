<?php

require_once __DIR__ . '/BepusdtProtocol.php';

class bepusdt_plugin
{
    public static $info = [
        'name' => 'bepusdt',
        'showname' => 'BEpusdt 加密货币个人收款',
        'author' => 'V03413',
        'link' => 'https://github.com/v03413/BEpusdt',
        'types' => [],
        'inputs' => [
            'appurl' => [
                'name' => '接口地址',
                'type' => 'input',
                'note' => '必须是有效 HTTPS 地址，并以 / 结尾',
            ],
            'appkey' => [
                'name' => '认证Token',
                'type' => 'input',
                'note' => 'BEpusdt后台【系统管理→基本设置→API设置→对接令牌】获取',
            ],
            'address' => [
                'name' => '收款地址',
                'type' => 'input',
                'note' => '可以留空，留空则由 BEpusdt 自动分配',
            ],
            'timeout' => [
                'name' => '订单超时',
                'type' => 'input',
                'note' => '可以留空；单位秒，最低 120，推荐 1200',
            ],
            'rate' => [
                'name' => '订单汇率',
                'type' => 'input',
                'note' => '可以留空，例如 7.4、~1.02、~0.98',
            ],
            'unified_cashier' => [
                'name' => '统一收银台',
                'type' => 'select',
                'options' => [
                    '0' => '关闭（跳转 BEpusdt 官方收银页）',
                    '1' => '开启（在本站内展示地址、金额和二维码）',
                ],
                'note' => '开启后使用本站收银台；回调链路不变。',
            ],
        ],
        'select' => null,
        'note' => '',
    ];

    public static function tradeTypeCatalog(): array
    {
        return [
            ['name' => 'usdt.trc20', 'showname' => 'USDT-TRC20'],
            ['name' => 'usdc.trc20', 'showname' => 'USDC-TRC20'],
            ['name' => 'tron.trx', 'showname' => 'TRX'],
            ['name' => 'usdt.erc20', 'showname' => 'USDT-ERC20'],
            ['name' => 'usdc.erc20', 'showname' => 'USDC-ERC20'],
            ['name' => 'ethereum.eth', 'showname' => 'ETH'],
            ['name' => 'usdt.polygon', 'showname' => 'USDT-Polygon'],
            ['name' => 'usdc.polygon', 'showname' => 'USDC-Polygon'],
            ['name' => 'usdt.bep20', 'showname' => 'USDT-BEP20'],
            ['name' => 'usdc.bep20', 'showname' => 'USDC-BEP20'],
            ['name' => 'bsc.bnb', 'showname' => 'BNB'],
            ['name' => 'usdt.aptos', 'showname' => 'USDT-Aptos'],
            ['name' => 'usdc.aptos', 'showname' => 'USDC-Aptos'],
            ['name' => 'usdt.solana', 'showname' => 'USDT-Solana'],
            ['name' => 'usdc.solana', 'showname' => 'USDC-Solana'],
            ['name' => 'usdt.xlayer', 'showname' => 'USDT-X Layer'],
            ['name' => 'usdc.xlayer', 'showname' => 'USDC-X Layer'],
            ['name' => 'usdt.arbitrum', 'showname' => 'USDT-Arbitrum'],
            ['name' => 'usdc.arbitrum', 'showname' => 'USDC-Arbitrum'],
            ['name' => 'usdc.base', 'showname' => 'USDC-Base'],
            ['name' => 'usdt.plasma', 'showname' => 'USDT-Plasma'],
            ['name' => 'usdt.ton', 'showname' => 'USDT-TON'],
            ['name' => 'ton.gram', 'showname' => 'GRAM-TON'],
        ];
    }

    public static function submit(): array
    {
        global $siteurl, $channel, $order, $conf;

        try {
            $tradeType = BepusdtProtocol::normalizeTradeType((string) $order['typename']);
            if (!in_array($tradeType, array_column(self::tradeTypeCatalog(), 'name'), true)) {
                throw new BepusdtProtocolException('当前 BEpusdt 插件不支持该支付类型');
            }

            $appUrl = BepusdtProtocol::normalizeGatewayUrl((string) ($channel['appurl'] ?? ''));
            $appKey = trim((string) ($channel['appkey'] ?? ''));
            if ($appKey === '') {
                throw new BepusdtProtocolException('BEpusdt 认证 Token 不能为空');
            }

            $notifyUrl = rtrim((string) $conf['localurl'], '/') . '/pay/notify/' . TRADE_NO . '/';
            $redirectUrl = rtrim((string) $siteurl, '/') . '/pay/return/' . TRADE_NO . '/';
            if(!BepusdtProtocol::isHttpsUrl($notifyUrl) || !BepusdtProtocol::isHttpsUrl($redirectUrl)){
                throw new BepusdtProtocolException('Epay 回调地址和返回地址必须使用 HTTPS');
            }

            $parameter = [
                'order_id' => (string) TRADE_NO,
                'amount' => (float) $order['realmoney'],
                'fiat' => 'CNY',
                'trade_type' => $tradeType,
                'notify_url' => $notifyUrl,
                'redirect_url' => $redirectUrl,
            ];

            $rate = trim((string) ($channel['rate'] ?? ''));
            if($rate !== '' && (
                !preg_match('/^~?(?:0|[1-9]\d*)(?:\.\d+)?$/', $rate)
                || (float)ltrim($rate, '~') <= 0
            )){
                throw new BepusdtProtocolException('BEpusdt 订单汇率格式不合法');
            }
            $optional = [
                'address' => trim((string) ($channel['address'] ?? '')),
                'name' => trim((string) ($order['name'] ?? '')),
                'rate' => $rate,
            ];
            foreach ($optional as $key => $value) {
                if ($value !== '') {
                    $parameter[$key] = $value;
                }
            }

            $timeout = (int) ($channel['timeout'] ?? 0);
            if ($timeout > 0) {
                if ($timeout < 120) {
                    throw new BepusdtProtocolException('BEpusdt 订单超时不能低于 120 秒');
                }
                $parameter['timeout'] = $timeout;
            }

            $parameter['signature'] = BepusdtProtocol::sign($parameter, $appKey);
            $response = BepusdtHttpClient::postJson($appUrl . 'api/v1/order/create-transaction', $parameter);
            $payload = BepusdtProtocol::validateCreateResponse(
                $response,
                (string) TRADE_NO,
                $tradeType,
                'CNY',
                (string) $order['realmoney']
            );

            \lib\Payment::updateOrder(TRADE_NO, (string) $payload['trade_id']);

            if (!empty($channel['unified_cashier'])) {
                $payInfo = self::buildUnifiedPayInfo($payload, $tradeType);
                \lib\Payment::mergeOrderExt(TRADE_NO, $payInfo);

                return [
                    'type' => 'page',
                    'page' => 'crypto',
                    'data' => self::unifiedPageData($payInfo),
                ];
            }

            return ['type' => 'jump', 'url' => (string) $payload['payment_url']];
        } catch (BepusdtProtocolException $e) {
            return ['type' => 'error', 'msg' => $e->getMessage()];
        } catch (\Throwable $e) {
            error_log('BEpusdt submit failed: '.$e->getMessage());
            return ['type' => 'error', 'msg' => 'BEpusdt 下单失败，请稍后重试'];
        }
    }

    public static function mapi(): array
    {
        global $channel;

        $result = self::submit();
        if (!empty($channel['unified_cashier']) && ($result['type'] ?? '') === 'page') {
            return ['type' => 'crypto', 'data' => $result['data']];
        }
        return $result;
    }

    public static function notify()
    {
        global $channel, $order;

        if (ob_get_level() > 0) {
            ob_clean();
        }

        try {
            $event = BepusdtProtocol::parseCallback(
                (string) file_get_contents('php://input'),
                (string) ($channel['appkey'] ?? ''),
                (string) TRADE_NO
            );
            if(!empty($order['api_trade_no']) && !hash_equals((string)$order['api_trade_no'], $event['trade_id'])){
                throw new BepusdtProtocolException('回调网关订单号不匹配', 400);
            }

            $disposition = BepusdtProtocol::callbackDisposition($event);
            if ($disposition === 'settle') {
                processNotify($order, $event['trade_id'], null, $event['block_transaction_id']);
            } elseif ($disposition === 'timeout' && in_array((int)$order['status'], [0, 4], true)) {
                \lib\Payment::mergeOrderExt((string) TRADE_NO, [
                    'gateway_status' => 'timeout',
                    'gateway_expired_at' => date('Y-m-d H:i:s'),
                ]);
            }

            self::respond(200, 'ok');
        } catch (BepusdtProtocolException $e) {
            self::respond($e->getHttpStatus(), 'fail');
        } catch (\Throwable $e) {
            error_log('BEpusdt callback failed: ' . $e->getMessage());
            self::respond(500, 'fail');
        }
    }

    public static function return(): array
    {
        return ['type' => 'page', 'page' => 'return'];
    }

    private static function buildUnifiedPayInfo(array $payload, string $tradeType): array
    {
        return [
            'plugin' => 'bepusdt',
            'address' => (string) $payload['token'],
            'amount' => (string) $payload['actual_amount'],
            'currency' => $tradeType,
            'chain' => '',
            'fiat' => 'CNY',
            'fiat_amount' => (string) $payload['amount'],
            'expire_at' => time() + (int) $payload['expiration_time'],
            'qrcode' => '',
            'fallback_url' => (string) $payload['payment_url'],
            'api_trade_no' => (string) $payload['trade_id'],
        ];
    }

    private static function unifiedPageData(array $ext): array
    {
        return [
            'pay_plugin' => 'BEpusdt',
            'pay_address' => $ext['address'],
            'pay_amount' => $ext['amount'],
            'pay_currency' => $ext['currency'],
            'pay_chain' => $ext['chain'],
            'pay_fiat' => $ext['fiat'],
            'pay_fiat_amount' => $ext['fiat_amount'],
            'pay_expire_at' => (int) $ext['expire_at'],
            'pay_qrcode' => $ext['qrcode'],
            'pay_fallback_url' => $ext['fallback_url'],
        ];
    }

    private static function respond(int $status, string $body): void
    {
        http_response_code($status);
        header('Content-Type: text/plain; charset=utf-8');
        exit($body);
    }
}

bepusdt_plugin::$info['types'] = array_column(bepusdt_plugin::tradeTypeCatalog(), 'name');
