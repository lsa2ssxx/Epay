/**
 * 与 includes/pay_type_icon.php 中逻辑一致（供前台/后台 JS 拼接 HTML）
 */
(function (global) {
	'use strict';

	function payTypeIconSrc(typename) {
		var s = String(typename || '').replace(/[^A-Za-z0-9._\-]/g, '');
		return '/assets/icon/' + s + '.ico';
	}

	function chainBadgeFor(typename) {
		var m = /^(usdt|usdc)\.([a-z0-9]+)$/.exec(String(typename || ''));
		if (!m) return null;
		var token = m[1].toUpperCase();
		var chain = m[2];
		var map = {
			trc20: { label: 'TRC', bg: '#FF0013' },
			erc20: { label: 'ETH', bg: '#627EEA' },
			bep20: { label: 'BSC', bg: '#F0B90B' },
			polygon: { label: 'POL', bg: '#8247E5' },
			arbitrum: { label: 'ARB', bg: '#28A0F0' },
			solana: { label: 'SOL', bg: '#9945FF' },
			aptos: { label: 'APT', bg: '#111111' },
			xlayer: { label: 'XL', bg: '#2d2d2d' },
			base: { label: 'BAS', bg: '#0052FF' },
			plasma: { label: 'PLM', bg: '#6B46C1' }
		};
		var b = map[chain];
		if (b) {
			return { label: b.label, bg: b.bg, title: token + ' · ' + chain };
		}
		return {
			label: chain.substring(0, 3).toUpperCase(),
			bg: '#555555',
			title: token + ' · ' + chain
		};
	}

	function escAttr(s) {
		return String(s)
			.replace(/&/g, '&amp;')
			.replace(/"/g, '&quot;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;');
	}

	/**
	 * @param {string} typename
	 * @param {string} [imgClass]
	 * @param {string} [extraAttrs]  例如 ' width="16"'
	 */
	function payTypeIconHtml(typename, imgClass, extraAttrs) {
		var cls = imgClass || 'type-logo';
		var src = escAttr(payTypeIconSrc(typename));
		var badge = chainBadgeFor(typename);
		var extra = extraAttrs || '';
		var imgCls = cls + (badge ? ' pay-type-icon-token' : '');
		var img = '<img src="' + src + '" class="' + escAttr(imgCls) + '" alt="" onerror="this.style.display=\'none\'"' + extra + '>';
		if (!badge) {
			return img;
		}
		return (
			'<span class="pay-type-icon-stack" title="' +
			escAttr(badge.title) +
			'">' +
			img +
			'<span class="pay-type-chain-badge" style="background:' +
			escAttr(badge.bg) +
			'">' +
			escAttr(badge.label) +
			'</span></span>'
		);
	}

	global.payTypeIconSrc = payTypeIconSrc;
	global.payTypeIconHtml = payTypeIconHtml;
	global.payTypeChainBadgeFor = chainBadgeFor;
})(typeof window !== 'undefined' ? window : this);
