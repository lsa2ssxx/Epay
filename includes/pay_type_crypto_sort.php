<?php
/**
 * 支付方式「加密货币语义」排序（后台列表展示用）
 *
 * BEpusdt：按链生态聚类 — 同链先 USDT、再 USDC、再该链原生币；链顺序大致为
 * TRON → Ethereum → Polygon → BSC → Arbitrum → Base → Solana → Aptos → X Layer → Plasma。
 * TokenPay：同类聚类（TRON / ETH / BSC / Polygon），同生态内先原生或按 USDT→USDC。
 */
if (!function_exists('pay_type_bepusdt_deep_order')) {
	function pay_type_bepusdt_deep_order(): array
	{
		return [
			'usdt.trc20', 'usdc.trc20', 'tron.trx',
			'usdt.erc20', 'usdc.erc20', 'ethereum.eth',
			'usdt.polygon', 'usdc.polygon',
			'usdt.bep20', 'usdc.bep20', 'bsc.bnb',
			'usdt.arbitrum', 'usdc.arbitrum',
			'usdc.base',
			'usdt.solana', 'usdc.solana',
			'usdt.aptos', 'usdc.aptos',
			'usdt.xlayer', 'usdc.xlayer',
			'usdt.plasma',
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
			'USDT_TRC20', 'TRX',
			'EVM_ETH_ETH', 'EVM_ETH_USDT_ERC20', 'EVM_ETH_USDC_ERC20',
			'EVM_BSC_BNB', 'EVM_BSC_USDT_BEP20', 'EVM_BSC_USDC_BEP20',
			'EVM_Polygon_POL', 'EVM_Polygon_USDT_ERC20', 'EVM_Polygon_USDC_ERC20',
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
