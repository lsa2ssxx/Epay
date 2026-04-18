<?php
/**
 * 支付方式图标：usdt.* / usdc.* 仅显示链 Logo（或文字回退）；其它类型显示对应 .ico
 */
if (!function_exists('pay_type_icon_src')) {
	function pay_type_icon_src($typename)
	{
		$safe = preg_replace('/[^A-Za-z0-9._\-]/', '', (string) $typename);

		return '/assets/icon/' . $safe . '.ico';
	}

	/**
	 * @return array<string,string>
	 */
	function pay_type_chain_logo_map()
	{
		return [
			'trc20' => '/assets/icon/chain/tron.svg',
			'erc20' => '/assets/icon/chain/erc20.svg',
			'bep20' => '/assets/icon/chain/bsc.svg',
			'polygon' => '/assets/icon/chain/polygon.svg',
			'arbitrum' => '/assets/icon/chain/arbitrum.png',
			'solana' => '/assets/icon/chain/solana.svg',
			'aptos' => '/assets/icon/chain/aptos.png',
			'xlayer' => '/assets/icon/chain/xlayer.png',
			'base' => '/assets/icon/chain/base.png',
			'plasma' => '/assets/icon/chain/plasma.png',
		];
	}

	/**
	 * @return array{label:string,bg:string,title:string}|null
	 */
	function pay_type_chain_text_fallback($typename)
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

	/**
	 * @return array{type:'logo',src:string,title:string}|array{type:'text',badge:array{label:string,bg:string,title:string},title:string}|null
	 */
	function pay_type_chain_overlay_for($typename)
	{
		if (!preg_match('/^(usdt|usdc)\.([a-z0-9]+)$/', (string) $typename, $m)) {
			return null;
		}
		$token = strtoupper($m[1]);
		$chain = $m[2];
		$title = $token . ' · ' . $chain;
		$logos = pay_type_chain_logo_map();
		if (isset($logos[$chain])) {
			return ['type' => 'logo', 'src' => $logos[$chain], 'title' => $title];
		}
		$badge = pay_type_chain_text_fallback($typename);
		if (!$badge) {
			return null;
		}

		return ['type' => 'text', 'badge' => $badge, 'title' => $title];
	}

	/** @deprecated 使用 pay_type_chain_overlay_for */
	function pay_type_chain_badge_for($typename)
	{
		return pay_type_chain_text_fallback($typename);
	}

	function pay_type_icon_html($typename, $imgClass = 'type-logo', $extraAttrs = '')
	{
		$tn = (string) $typename;
		$overlay = pay_type_chain_overlay_for($tn);
		$cls = htmlspecialchars($imgClass, ENT_QUOTES, 'UTF-8');

		if (!$overlay) {
			$src = htmlspecialchars(pay_type_icon_src($tn), ENT_QUOTES, 'UTF-8');

			return '<img src="' . $src . '" class="' . $cls . '" alt="" onerror="this.style.display=\'none\'"' . $extraAttrs . '>';
		}

		$title = htmlspecialchars($overlay['title'], ENT_QUOTES, 'UTF-8');
		$html = '<span class="pay-type-icon-stack pay-type-icon-chain-only" title="' . $title . '">';
		if ($overlay['type'] === 'logo') {
			$lsrc = htmlspecialchars($overlay['src'], ENT_QUOTES, 'UTF-8');
			$html .= '<img src="' . $lsrc . '" class="' . $cls . ' pay-type-chain-logo pay-type-chain-logo--solo" alt="" loading="lazy" decoding="async" onerror="this.style.visibility=\'hidden\'"' . $extraAttrs . '>';
		} else {
			$b = $overlay['badge'];
			$label = htmlspecialchars($b['label'], ENT_QUOTES, 'UTF-8');
			$bg = htmlspecialchars($b['bg'], ENT_QUOTES, 'UTF-8');
			$html .= '<span class="pay-type-chain-badge pay-type-chain-badge--solo" style="background:' . $bg . '">' . $label . '</span>';
		}
		$html .= '</span>';

		return $html;
	}
}
