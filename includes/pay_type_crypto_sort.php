<?php
/**
 * 支付方式「加密货币语义」排序（后台列表展示用）
 *
 * BEpusdt：先整块 USDT（各链按 TRC→ETH→Polygon→BSC→Arb→Sol→Aptos→XL→Plasma），
 * 再整块 USDC（同上链序，另含 Base），最后原生币 TRX / ETH / BNB。
 * TokenPay：先全部 USDT 类型，再全部 USDC，再各链原生（TRX、ETH、BNB、POL）。
 */
if (!function_exists('pay_type_bepusdt_deep_order')) {
	function pay_type_bepusdt_deep_order(): array
	{
		return [
			'usdt.trc20', 'usdt.erc20', 'usdt.polygon', 'usdt.bep20', 'usdt.arbitrum', 'usdt.solana', 'usdt.aptos', 'usdt.xlayer', 'usdt.plasma',
			'usdc.trc20', 'usdc.erc20', 'usdc.polygon', 'usdc.bep20', 'usdc.arbitrum', 'usdc.base', 'usdc.solana', 'usdc.aptos', 'usdc.xlayer',
			'tron.trx', 'ethereum.eth', 'bsc.bnb',
		];
	}

	/**
	 * @param array<int,array<string,mixed>> $rows
	 * @return array<int,array<string,mixed>>
	 */
	function pay_type_sort_rows_bepusdt_deep(array $rows): array
	{
		$fp = PLUGIN_ROOT.'bepusdt/bepusdt_plugin.php';
		if (is_file($fp)) {
			include_once $fp;
		}
		$deep = pay_type_bepusdt_deep_order();
		$deepFlip = array_flip($deep);
		$catalogFlip = [];
		if (class_exists('bepusdt_plugin', false)) {
			foreach (bepusdt_plugin::tradeTypeCatalog() as $i => $row) {
				$catalogFlip[$row['name']] = $i;
			}
		}
		usort($rows, function ($a, $b) use ($deepFlip, $catalogFlip) {
			$na = $a['name'] ?? '';
			$nb = $b['name'] ?? '';
			$ia = isset($deepFlip[$na]) ? $deepFlip[$na] : (2000 + ($catalogFlip[$na] ?? 999));
			$ib = isset($deepFlip[$nb]) ? $deepFlip[$nb] : (2000 + ($catalogFlip[$nb] ?? 999));
			if ($ia !== $ib) {
				return $ia <=> $ib;
			}

			return strcmp($na, $nb);
		});

		return $rows;
	}

	function pay_type_tokenpay_deep_order(): array
	{
		return [
			'USDT_TRC20', 'EVM_ETH_USDT_ERC20', 'EVM_BSC_USDT_BEP20', 'EVM_Polygon_USDT_ERC20',
			'EVM_ETH_USDC_ERC20', 'EVM_BSC_USDC_BEP20', 'EVM_Polygon_USDC_ERC20',
			'TRX', 'EVM_ETH_ETH', 'EVM_BSC_BNB', 'EVM_Polygon_POL',
		];
	}

	/**
	 * @param array<int,array<string,mixed>> $rows
	 * @return array<int,array<string,mixed>>
	 */
	function pay_type_sort_rows_tokenpay_deep(array $rows): array
	{
		$fp = PLUGIN_ROOT.'TokenPay/TokenPay_plugin.php';
		if (is_file($fp)) {
			include_once $fp;
		}
		$deep = pay_type_tokenpay_deep_order();
		$deepFlip = array_flip($deep);
		$catalogFlip = [];
		if (class_exists('TokenPay_plugin', false)) {
			foreach (TokenPay_plugin::$info['types'] as $i => $t) {
				$catalogFlip[$t] = $i;
			}
		}
		usort($rows, function ($a, $b) use ($deepFlip, $catalogFlip) {
			$na = $a['name'] ?? '';
			$nb = $b['name'] ?? '';
			$ia = isset($deepFlip[$na]) ? $deepFlip[$na] : (2000 + ($catalogFlip[$na] ?? 999));
			$ib = isset($deepFlip[$nb]) ? $deepFlip[$nb] : (2000 + ($catalogFlip[$nb] ?? 999));
			if ($ia !== $ib) {
				return $ia <=> $ib;
			}

			return strcmp($na, $nb);
		});

		return $rows;
	}
}
