/**
 * 与 includes/pay_type_icon.php 中逻辑一致（供前台/后台 JS 拼接 HTML）
 */
(function (global) {
	'use strict';

	var CHAIN_LOGOS = {
		trc20: '/assets/icon/chain/tron.svg',
		erc20: '/assets/icon/chain/erc20.svg',
		bep20: '/assets/icon/chain/bsc.svg',
		polygon: '/assets/icon/chain/polygon.svg',
		arbitrum: '/assets/icon/chain/arbitrum.png',
		solana: '/assets/icon/chain/solana.svg',
		aptos: '/assets/icon/chain/aptos.png',
		xlayer: '/assets/icon/chain/xlayer.png',
		base: '/assets/icon/chain/base.png',
		plasma: '/assets/icon/chain/plasma.png'
	};

	function payTypeIconSrc(typename) {
		var s = String(typename || '').replace(/[^A-Za-z0-9._\-]/g, '');
		return '/assets/icon/' + s + '.ico';
	}

	function chainTextFallback(typename) {
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
		var title = token + ' · ' + chain;
		if (b) {
			return { label: b.label, bg: b.bg, title: title };
		}
		return {
			label: chain.substring(0, 3).toUpperCase(),
			bg: '#555555',
			title: title
		};
	}

	function chainOverlayFor(typename) {
		var m = /^(usdt|usdc)\.([a-z0-9]+)$/.exec(String(typename || ''));
		if (!m) return null;
		var token = m[1].toUpperCase();
		var chain = m[2];
		var title = token + ' · ' + chain;
		if (CHAIN_LOGOS[chain]) {
			return { type: 'logo', src: CHAIN_LOGOS[chain], title: title };
		}
		var badge = chainTextFallback(typename);
		if (!badge) return null;
		return { type: 'text', badge: badge, title: title };
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
		var overlay = chainOverlayFor(typename);
		var extra = extraAttrs || '';
		var imgCls = cls + (overlay ? ' pay-type-icon-token' : '');
		var img = '<img src="' + src + '" class="' + escAttr(imgCls) + '" alt="" onerror="this.style.display=\'none\'"' + extra + '>';
		if (!overlay) {
			return img;
		}
		var html =
			'<span class="pay-type-icon-stack" title="' + escAttr(overlay.title) + '">' + img;
		if (overlay.type === 'logo') {
			html +=
				'<img src="' +
				escAttr(overlay.src) +
				'" class="pay-type-chain-logo" alt="" loading="lazy" decoding="async" onerror="this.style.visibility=\'hidden\'">';
		} else {
			var b = overlay.badge;
			html +=
				'<span class="pay-type-chain-badge" style="background:' +
				escAttr(b.bg) +
				'">' +
				escAttr(b.label) +
				'</span>';
		}
		html += '</span>';
		return html;
	}

	global.payTypeIconSrc = payTypeIconSrc;
	global.payTypeIconHtml = payTypeIconHtml;
	global.payTypeChainOverlayFor = chainOverlayFor;
	global.payTypeChainBadgeFor = chainTextFallback;
})(typeof window !== 'undefined' ? window : this);
