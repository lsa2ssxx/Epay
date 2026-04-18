<?php
/**
 * 支付方式图标：稳定币主图 + 链角标（usdt.* / usdc.* 细化网络）
 */
if (!function_exists('pay_type_icon_src')) {
	function pay_type_icon_src($typename)
	{
		$safe = preg_replace('/[^A-Za-z0-9._\-]/', '', (string) $typename);

		return '/assets/icon/' . $safe . '.ico';
	}

	/**
	 * @return array{label:string,bg:string,title:string}|null
	 */
	function pay_type_chain_badge_for($typename)
	{
		if (!preg_match('/^(usdt|usdc)\.([a-z0-9]+)$/', (string) $typename, $m)) {
			return null;
		}
		$token = strtoupper($m[1]);
		$chain = $m[2];
		$map = [
			'trc20' => ['label' => 'TRC', 'bg' => '#FF0013'],
			'erc20' => ['label' => 'ETH', 'bg' => '#627EEA'],
			'bep20' => ['label' => 'BSC', 'bg' => '#F0B90B'],
			'polygon' => ['label' => 'POL', 'bg' => '#8247E5'],
			'arbitrum' => ['label' => 'ARB', 'bg' => '#28A0F0'],
			'solana' => ['label' => 'SOL', 'bg' => '#9945FF'],
			'aptos' => ['label' => 'APT', 'bg' => '#111111'],
			'xlayer' => ['label' => 'XL', 'bg' => '#2d2d2d'],
			'base' => ['label' => 'BAS', 'bg' => '#0052FF'],
			'plasma' => ['label' => 'PLM', 'bg' => '#6B46C1'],
		];
		if (isset($map[$chain])) {
			$b = $map[$chain];

			return [
				'label' => $b['label'],
				'bg' => $b['bg'],
				'title' => $token . ' · ' . $chain,
			];
		}

		return [
			'label' => strtoupper(substr($chain, 0, 3)),
			'bg' => '#555555',
			'title' => $token . ' · ' . $chain,
		];
	}

	function pay_type_icon_html($typename, $imgClass = 'type-logo', $extraAttrs = '')
	{
		$tn = (string) $typename;
		$src = htmlspecialchars(pay_type_icon_src($tn), ENT_QUOTES, 'UTF-8');
		$badge = pay_type_chain_badge_for($tn);
		$cls = htmlspecialchars($imgClass, ENT_QUOTES, 'UTF-8');
		$img = '<img src="' . $src . '" class="' . $cls . ($badge ? ' pay-type-icon-token' : '') . '" alt="" onerror="this.style.display=\'none\'"' . $extraAttrs . '>';
		if (!$badge) {
			return $img;
		}
		$title = htmlspecialchars($badge['title'], ENT_QUOTES, 'UTF-8');
		$label = htmlspecialchars($badge['label'], ENT_QUOTES, 'UTF-8');
		$bg = htmlspecialchars($badge['bg'], ENT_QUOTES, 'UTF-8');

		return '<span class="pay-type-icon-stack" title="' . $title . '">' . $img . '<span class="pay-type-chain-badge" style="background:' . $bg . '">' . $label . '</span></span>';
	}
}
