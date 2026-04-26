<?php

class bepusdt_plugin
{
    public static $info = [
        'name'     => 'bepusdt',
        'showname' => 'BEpusdt USDT/USDC 个人收款',
        'author'   => 'V03413',
        'link'     => 'https://github.com/v03413/BEpusdt',
        // 与 tradeTypeCatalog() 同步；权威列表见 https://github.com/v03413/BEpusdt/blob/main/docs/trade-type.md
        'types'    => [
            'usdt.trc20',
            'usdc.trc20',
            'tron.trx',
            'usdt.erc20',
            'usdc.erc20',
            'ethereum.eth',
            'usdt.polygon',
            'usdc.polygon',
            'usdt.bep20',
            'usdc.bep20',
            'bsc.bnb',
            'usdt.aptos',
            'usdc.aptos',
            'usdt.solana',
            'usdc.solana',
            'usdt.xlayer',
            'usdc.xlayer',
            'usdt.arbitrum',
            'usdc.arbitrum',
            'usdc.base',
            'usdt.plasma',
        ],
        'inputs'   => [
            'appurl'  => [
                'name' => '接口地址',
                'type' => 'input',
                'note' => '必须以http://或https://开头，以/结尾',
            ],
            'appkey'  => [
                'name' => '认证Token',
                'type' => 'input',
                'note' => 'BEpusdt后台【系统管理→基本设置→API设置→对接令牌】获取，非Docker环境变量',
            ],
            'address' => [
                'name' => '收款地址',
                'type' => 'input',
                'note' => '可以留空 留空则由BEpusdt自动分配，切勿乱填 注意空格',
            ],
            'timeout' => [
                'name' => '订单超时',
                'type' => 'input',
                'note' => '可以留空 填写整数(单位秒)、推荐 1200',
            ],
            'rate'    => [
                'name' => '订单汇率',
                'type' => 'input',
                'note' => '可以留空 例如：7.4 ~1.02 ~0.98（不明白切勿乱填）',
            ],
            'unified_cashier' => [
                'name' => '统一收银台',
                'type' => 'select',
                'options' => [
                    '0' => '关闭（跳转 BEpusdt 官方收银页）',
                    '1' => '开启（在本站内渲染地址+金额+二维码）',
                ],
                'note' => '开启后：下单后直接在本站收银台展示收款地址与金额，保持站点品牌与 UI 一致；回调链路不变。',
            ],
        ],
        'select'   => null,
        'note'     => '', //支付密钥填写说明
    ];

    /**
     * BEpusdt API trade_type 与 Epay 支付方式展示名（用于后台一键导入）。
     * 顺序与官方文档表格一致，便于对照维护。
     *
     * @return array<int, array{name:string, showname:string}>
     */
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
        ];
    }

    public static function submit(): array
    {
        global $siteurl, $channel, $order, $conf;

        // BEpusdt 签名规则：空值不参与签名，仅发送有值的参数以确保双方签名一致
        $parameter = [
            'order_id'     => TRADE_NO,
            'amount'       => floatval($order['realmoney']),
            'notify_url'   => $conf['localurl'] . 'pay/notify/' . TRADE_NO . '/',
            'redirect_url' => $siteurl . 'pay/return/' . TRADE_NO . '/',
        ];

        $trade_type = self::_normalizeTradeType($order['typename']);
        if ($trade_type) {
            $parameter['trade_type'] = $trade_type;
        }

        $address = trim($channel['address'] ?? '');
        if ($address !== '') {
            $parameter['address'] = $address;
        }

        $name = trim($order['name'] ?? '');
        if ($name !== '') {
            $parameter['name'] = $name;
        }

        $timeout = intval($channel['timeout'] ?? 0);
        if ($timeout > 0) {
            $parameter['timeout'] = $timeout;
        }

        $rate = trim(strval($channel['rate'] ?? ''));
        if ($rate !== '') {
            $parameter['rate'] = $rate;
        }

        $parameter['signature'] = self::_toSign($parameter, $channel['appkey']);

        $url  = trim($channel['appurl']) . 'api/v1/order/create-transaction';
        $data = self::_post($url, $parameter);
        if (!is_array($data)) {

            return ['type' => 'error', 'msg' => '请求失败，请检查服务器是否能正常请求 BEpusdt 网关！'];
        }

        if ($data['status_code'] != 200) {

            return ['type' => 'error', 'msg' => '请求失败，错误信息：' . $data['message']];
        }

        $payload = is_array($data['data']) ? $data['data'] : [];
        $payment_url = $payload['payment_url'] ?? '';

        // 统一收银台：保留完整支付上下文并渲染站内页面
        if (!empty($channel['unified_cashier'])) {
            $payinfo = self::_buildUnifiedPayInfo($payload, $trade_type ?? '');
            \lib\Payment::updateOrderExt(TRADE_NO, $payinfo);
            if (!empty($payload['trade_id'])) {
                \lib\Payment::updateOrder(TRADE_NO, $payload['trade_id']);
            }

            return [
                'type' => 'page',
                'page' => 'crypto',
                'data' => self::_unifiedPageData($payinfo),
            ];
        }

        return ['type' => 'jump', 'url' => $payment_url];
    }

    /**
     * API 模式下，若开启统一收银台则返回 crypto 类型，由 Payment::echoJson 序列化为 pay_info 对象。
     * 未开启统一收银台时回退为 submit 流程（走站内跳转页）。
     */
    public static function mapi(): array
    {
        global $channel;

        $result = self::submit();
        if (!empty($channel['unified_cashier']) && isset($result['type']) && $result['type'] === 'page') {
            return [
                'type' => 'crypto',
                'data' => $result['data'],
            ];
        }
        return $result;
    }

    /**
     * 将 BEpusdt 响应整理为统一收银台所需的上下文
     *
     * @param array<string, mixed> $payload   BEpusdt /create-transaction 响应的 data 段
     * @param string               $trade_type 请求时的 trade_type（如 usdt.trc20），用于展示
     * @return array<string, mixed>
     */
    private static function _buildUnifiedPayInfo(array $payload, string $trade_type): array
    {
        $currency = $trade_type !== '' ? $trade_type : (string) ($payload['trade_type'] ?? '');
        $expire_sec = intval($payload['expiration_time'] ?? 0);
        return [
            'plugin'        => 'bepusdt',
            'address'       => (string) ($payload['token'] ?? ''),
            'amount'        => (string) ($payload['actual_amount'] ?? ''),
            'currency'      => $currency,
            'chain'         => '', // BEpusdt 的 trade_type 已包含链信息，chain_label 由前端自行推断
            'fiat'          => (string) ($payload['fiat'] ?? 'CNY'),
            'fiat_amount'   => (string) ($payload['amount'] ?? ''),
            'expire_at'     => $expire_sec > 0 ? time() + $expire_sec : 0,
            'qrcode'        => '', // BEpusdt 不直接返回二维码图片，前端用地址本地生成
            'fallback_url'  => (string) ($payload['payment_url'] ?? ''),
            'api_trade_no'  => (string) ($payload['trade_id'] ?? ''),
        ];
    }

    /**
     * 将扩展数据展开为 type=page 所需的局部变量键
     *
     * @param array<string, mixed> $ext
     * @return array<string, mixed>
     */
    private static function _unifiedPageData(array $ext): array
    {
        return [
            'pay_plugin'       => 'BEpusdt',
            'pay_address'      => $ext['address'] ?? '',
            'pay_amount'       => $ext['amount'] ?? '',
            'pay_currency'     => $ext['currency'] ?? '',
            'pay_chain'        => $ext['chain'] ?? '',
            'pay_fiat'         => $ext['fiat'] ?? 'CNY',
            'pay_fiat_amount'  => $ext['fiat_amount'] ?? '',
            'pay_expire_at'    => (int) ($ext['expire_at'] ?? 0),
            'pay_qrcode'       => $ext['qrcode'] ?? '',
            'pay_fallback_url' => $ext['fallback_url'] ?? '',
        ];
    }

    public static function notify()
    {
        global $channel, $order;

        ob_clean();
        header('Content-Type: plain/text; charset=utf-8');

        $data = json_decode(file_get_contents('php://input'), true);
        $sign = $data['signature'] ?? '';
        if ($sign != self::_toSign($data, $channel['appkey'])) {
            // 签名验证失败

            exit('fail - sign error');
        }

        $out_trade_no = $data['order_id'];    // 商户订单号
        $trade_no     = $data['trade_id'];    // BEpusdt 交易ID
        $buyer        = mb_substr($data['buyer'] ?? '', -28);
        $status       = isset($data['status']) ? (int) $data['status'] : 0;

        if ($out_trade_no != TRADE_NO) {
            exit('fail - order mismatch');
        }

        // status=1 链上已检测但未确认：仅登记检测状态，不修改订单支付状态
        if ($status === 1) {
            self::_markDetected($order, $trade_no, $buyer);
            exit('ok');
        }

        if ($status === 2) {
            // 确认前补登检测状态，保证前端能先走 detected → completed 的流式过渡
            self::_markDetected($order, $trade_no, $buyer);
            processNotify($order, $trade_no, $buyer);

            exit('ok');
        }

        exit('fail - status error');
    }

    /**
     * 将链上检测信息回写到 pre_order.ext，供收银台前端读取展示。
     * 不会覆盖同一订单的历史 detection 记录（例如重复回调）。
     *
     * @param array  $order    当前订单行
     * @param string $trade_no BEpusdt 交易 ID（作为 detection tx）
     * @param string $buyer    付款方钱包地址
     */
    private static function _markDetected(array $order, string $trade_no, string $buyer): void
    {
        global $DB;

        $ext = [];
        if (!empty($order['ext'])) {
            $decoded = @unserialize($order['ext']);
            if (is_array($decoded)) {
                $ext = $decoded;
            }
        }

        // 若已记录 detected_at 则跳过，避免回调重试覆盖首个检测时间
        if (!empty($ext['detected_at'])) {
            return;
        }

        $ext['detected_at']    = date('Y-m-d H:i:s');
        $ext['detected_tx']    = $trade_no;
        $ext['detected_buyer'] = $buyer;

        $DB->update('order', ['ext' => serialize($ext)], ['trade_no' => $order['trade_no']]);
    }

    public static function return(): array
    {
        return ['type' => 'page', 'page' => 'return'];
    }

    /**
     * 将 Epay 支付方式名称转为 BEpusdt 要求的 trade_type 格式（小写+点号）
     * 如：usdt-polygon -> usdt.polygon, USDT-TRC20 -> usdt.trc20
     */
    private static function _normalizeTradeType(string $typename): string
    {
        $t = strtolower(trim($typename));
        $t = str_replace(['-', '_'], '.', $t);
        return $t;
    }

    private static function _toSign(array $parameter, string $token): string
    {
        ksort($parameter);

        $sign = '';

        foreach ($parameter as $key => $val) {
            if ($key === 'signature') continue;
            // BEpusdt 规则：空值不参与签名（包括 null、空字符串、0）
            if ($val === '' || $val === null) continue;
            if ($val === 0 && $key !== 'amount') continue;

            if ($sign !== '') {
                $sign .= '&';
            }
            $sign .= $key . '=' . $val;
        }

        return md5($sign . $token);
    }

    private static function _post(string $url, array $json)
    {

        $header[] = 'Accept: */*';
        $header[] = 'Accept-Language: zh-CN,zh;q=0.8';
        $header[] = 'Connection: close';
        $header[] = 'Content-Type: application/json';

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($json));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_HEADER, false);
        $resp = curl_exec($ch);
        curl_close($ch);

        return json_decode($resp, true);
    }
}

bepusdt_plugin::$info['types'] = array_column(bepusdt_plugin::tradeTypeCatalog(), 'name');
