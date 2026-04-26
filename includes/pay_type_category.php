<?php
/**
 * 支付方式「币种 / 网络」分类推导器
 *
 * 用于收银台三级菜单：
 *   一级 currency（USDT/USDC/Alipay/...）
 *   二级 network（TRC20/ERC20/Polygon/...，可空）
 *   三级 支付通道（落到 submit2.php）
 *
 * 优先使用 pre_type.currency / pre_type.network；
 * 当数据库字段为空（旧库或未配置）时，按本文件的启发式规则从 pre_type.name 推导。
 */
if (!function_exists('pay_type_category_currency_meta')) {
	/**
	 * 各币种的展示元数据（图标 / 显示名 / 默认排序）
	 *
	 * @return array<string,array{name:string,icon:string,kind:string,sort:int}>
	 */
	function pay_type_category_currency_meta()
	{
		return [
			'USDT'    => ['name' => 'USDT',      'icon' => 'usdt',         'kind' => 'crypto', 'sort' => 10],
			'USDC'    => ['name' => 'USDC',      'icon' => 'usdc',         'kind' => 'crypto', 'sort' => 11],
			'BTC'     => ['name' => 'Bitcoin',   'icon' => 'btc',          'kind' => 'crypto', 'sort' => 12],
			'ETH'     => ['name' => 'Ethereum',  'icon' => 'ethereum.eth', 'kind' => 'crypto', 'sort' => 13],
			'TRX'     => ['name' => 'TRON',      'icon' => 'tron.trx',     'kind' => 'crypto', 'sort' => 14],
			'BNB'     => ['name' => 'BNB',       'icon' => 'bsc.bnb',      'kind' => 'crypto', 'sort' => 15],
			'POL'     => ['name' => 'Polygon',   'icon' => 'polygon.pol',  'kind' => 'crypto', 'sort' => 16],
			'SOL'     => ['name' => 'Solana',    'icon' => 'solana.sol',   'kind' => 'crypto', 'sort' => 17],
			'APT'     => ['name' => 'Aptos',     'icon' => 'aptos.apt',    'kind' => 'crypto', 'sort' => 18],
			'Alipay'  => ['name' => '支付宝',    'icon' => 'alipay',       'kind' => 'fiat',   'sort' => 80],
			'WeChat'  => ['name' => '微信支付',  'icon' => 'wxpay',        'kind' => 'fiat',   'sort' => 81],
			'QQ'      => ['name' => 'QQ 钱包',   'icon' => 'qqpay',        'kind' => 'fiat',   'sort' => 82],
			'JD'      => ['name' => '京东支付',  'icon' => 'jdpay',        'kind' => 'fiat',   'sort' => 83],
			'Bank'    => ['name' => '网银支付',  'icon' => 'bank',         'kind' => 'fiat',   'sort' => 84],
			'PayPal'  => ['name' => 'PayPal',    'icon' => 'paypal',       'kind' => 'fiat',   'sort' => 85],
		];
	}

	/**
	 * 从 pre_type.name 推导出 [currency, network]。
	 * - 形如 `usdt.trc20` → ['USDT', 'TRC20']
	 * - 形如 `tron.trx`   → ['TRX',  null]（原生币只有一个网络，归并到 currency 自身）
	 * - BEpusdt/TokenPay 命名 USDT_TRC20 / EVM_ETH_USDT_ERC20 → ['USDT', 'TRC20'/'ERC20']
	 * - 法币 alipay/wxpay 等 → ['Alipay'/'WeChat'/...,  null]
	 *
	 * @param string $name
	 * @return array{0:string|null,1:string|null}
	 */
	function pay_type_category_derive($name)
	{
		$raw = (string) $name;
		$n = strtolower(trim($raw));
		if ($n === '') {
			return [null, null];
		}

		// 1) 法币 / 通用方式：精确匹配
		$fiat = [
			'alipay' => 'Alipay',
			'wxpay'  => 'WeChat',
			'wechat' => 'WeChat',
			'qqpay'  => 'QQ',
			'jdpay'  => 'JD',
			'bank'   => 'Bank',
			'paypal' => 'PayPal',
		];
		if (isset($fiat[$n])) {
			return [$fiat[$n], null];
		}

		// 2) `<token>.<chain>` 形（usdt.trc20、usdc.polygon、tron.trx ...）
		if (preg_match('/^([a-z0-9]+)\.([a-z0-9]+)$/', $n, $m)) {
			$token = $m[1];
			$chain = $m[2];
			$tokenU = strtoupper($token);
			$chainU = strtoupper($chain);

			// 原生币：链.币 同一项时按 currency 处理（tron.trx → TRX）
			$native = [
				'tron.trx'      => 'TRX',
				'ethereum.eth'  => 'ETH',
				'bsc.bnb'       => 'BNB',
				'polygon.pol'   => 'POL',
				'solana.sol'    => 'SOL',
				'aptos.apt'     => 'APT',
				'btc.btc'       => 'BTC',
			];
			if (isset($native[$n])) {
				return [$native[$n], null];
			}

			// 稳定币 / 资产：currency = 大写 token；network = 大写 chain
			return [$tokenU, $chainU];
		}

		// 3) BEpusdt/TokenPay 风格：USDT_TRC20、USDC_POLYGON、EVM_ETH_USDT_ERC20、EVM_BSC_BNB ...
		$tokens = ['USDT', 'USDC', 'DAI', 'BTC', 'ETH', 'TRX', 'BNB', 'POL', 'SOL', 'APT'];
		$chains = ['TRC20', 'ERC20', 'BEP20', 'POLYGON', 'BSC', 'ARBITRUM', 'SOLANA', 'APTOS', 'XLAYER', 'BASE', 'PLASMA', 'TRON', 'ETHEREUM'];
		$rawU = strtoupper($raw);
		$foundToken = null;
		$foundChain = null;
		foreach ($tokens as $t) {
			if (strpos($rawU, $t) !== false) {
				$foundToken = $t;
				break;
			}
		}
		foreach ($chains as $c) {
			if (strpos($rawU, $c) !== false) {
				$foundChain = $c;
				if ($foundChain === 'TRON') $foundChain = 'TRC20';
				if ($foundChain === 'ETHEREUM') $foundChain = 'ERC20';
				break;
			}
		}
		if ($foundToken !== null && $foundChain !== null) {
			return [$foundToken, $foundChain];
		}
		if ($foundToken !== null) {
			return [$foundToken, null];
		}

		// 4) 兜底：把整个 name 当作 currency 自身一个独立分组
		$fallback = preg_replace('/[^A-Za-z0-9]/', '', $raw);
		if ($fallback === '') {
			return [null, null];
		}

		return [strtoupper($fallback), null];
	}

	/**
	 * 解析单个 pre_type 行的 currency/network：
	 * 数据库列 currency / network 优先；为空时退回到推导器。
	 *
	 * @param array<string,mixed> $row pre_type 行（必须含 name）
	 * @return array{currency:string,network:string|null,kind:string,currency_meta:array}
	 */
	function pay_type_category_resolve(array $row)
	{
		$cur = isset($row['currency']) ? trim((string) $row['currency']) : '';
		$net = isset($row['network'])  ? trim((string) $row['network'])  : '';
		if ($cur === '' || $net === '') {
			$derived = pay_type_category_derive($row['name'] ?? '');
			if ($cur === '') {
				$cur = (string) ($derived[0] ?? '');
			}
			if ($net === '') {
				$net = (string) ($derived[1] ?? '');
			}
		}
		if ($cur === '') {
			$cur = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) ($row['name'] ?? '')));
		}
		if ($cur === '') {
			$cur = 'OTHER';
		}

		$meta = pay_type_category_currency_meta();
		$cm = $meta[$cur] ?? [
			'name' => $cur,
			'icon' => strtolower($cur),
			'kind' => 'other',
			'sort' => 999,
		];
		// 仅在用户未显式设置 currency 排序时才使用元数据排序
		if (!empty($row['currency_sort']) && (int) $row['currency_sort'] > 0) {
			$cm['sort'] = (int) $row['currency_sort'];
		}

		return [
			'currency'      => $cur,
			'network'       => $net !== '' ? $net : null,
			'kind'          => $cm['kind'],
			'currency_meta' => $cm,
		];
	}

	/**
	 * 网络默认排序（在二级页面内部用）
	 *
	 * @return array<string,int>
	 */
	function pay_type_category_network_sort_map()
	{
		return [
			'TRC20'    => 10,
			'ERC20'    => 20,
			'POLYGON'  => 30,
			'BEP20'    => 40,
			'BSC'      => 41,
			'ARBITRUM' => 50,
			'BASE'     => 60,
			'SOLANA'   => 70,
			'APTOS'    => 80,
			'XLAYER'   => 90,
			'PLASMA'   => 95,
		];
	}
}
