<?php
/**
 * 统一加密货币收银台
 *
 * 被 \lib\Payment::echoDefault() 以 type=page 方式加载，
 * 适用于所有通过后端 API 生成加密支付订单的插件（bepusdt / TokenPay 等）。
 *
 * 上下文变量（由 Payment::echoDefault 的 extract($result['data']) 注入，或从 pre_order.ext 回读）：
 *   $pay_plugin   string  插件名（bepusdt / TokenPay 等），用于显示
 *   $pay_address  string  收款钱包地址
 *   $pay_amount   string  加密币实付数量（已精确到小数位）
 *   $pay_currency string  加密币种（USDT-TRC20 / TRX / USDT-BEP20 等，用于展示）
 *   $pay_chain    string  区块链网络（Tron / Ethereum / BSC 等）
 *   $pay_fiat     string  法币币种（CNY / USD 等）
 *   $pay_fiat_amount string 法币金额
 *   $pay_expire_at int    UNIX 时间戳，支付到期时间
 *   $pay_qrcode   string  可选：二维码图片（base64 data URI 或 http URL），没有则前端 jquery.qrcode 兜底生成
 *   $pay_fallback_url string 可选：第三方 Checkout 原始 URL（兜底跳转用）
 *
 * 全局上下文：
 *   $order, $conf, $sitename, $ordername, $siteurl, $cdnpublic
 */
if (!defined('IN_PLUGIN')) exit();

// 兜底：若上下文中未带支付信息，则从订单扩展数据回读（刷新页面场景）
if (empty($pay_address) && isset($order['ext']) && $order['ext']) {
	$__ext = @unserialize($order['ext']);
	if (is_array($__ext)) {
		$pay_plugin       = $pay_plugin       ?? ($__ext['plugin']       ?? '');
		$pay_address      = $pay_address      ?? ($__ext['address']      ?? '');
		$pay_amount       = $pay_amount       ?? ($__ext['amount']       ?? '');
		$pay_currency     = $pay_currency     ?? ($__ext['currency']     ?? '');
		$pay_chain        = $pay_chain        ?? ($__ext['chain']        ?? '');
		$pay_fiat         = $pay_fiat         ?? ($__ext['fiat']         ?? 'CNY');
		$pay_fiat_amount  = $pay_fiat_amount  ?? ($__ext['fiat_amount']  ?? $order['realmoney']);
		$pay_expire_at    = $pay_expire_at    ?? ($__ext['expire_at']    ?? 0);
		$pay_qrcode       = $pay_qrcode       ?? ($__ext['qrcode']       ?? '');
		$pay_fallback_url = $pay_fallback_url ?? ($__ext['fallback_url'] ?? '');
	}
}

$pay_plugin       = isset($pay_plugin)       ? (string) $pay_plugin       : '';
$pay_address      = isset($pay_address)      ? (string) $pay_address      : '';
$pay_amount       = isset($pay_amount)       ? (string) $pay_amount       : '';
$pay_currency     = isset($pay_currency)     ? (string) $pay_currency     : '';
$pay_chain        = isset($pay_chain)        ? (string) $pay_chain        : '';
$pay_fiat         = isset($pay_fiat) && $pay_fiat !== '' ? (string) $pay_fiat : 'CNY';
$pay_fiat_amount  = isset($pay_fiat_amount)  ? (string) $pay_fiat_amount  : (string) $order['realmoney'];
$pay_expire_at    = isset($pay_expire_at)    ? (int)    $pay_expire_at    : 0;
$pay_qrcode       = isset($pay_qrcode)       ? (string) $pay_qrcode       : '';
$pay_fallback_url = isset($pay_fallback_url) ? (string) $pay_fallback_url : '';

if ($pay_address === '' || $pay_amount === '') {
	sysmsg('支付信息缺失，请返回重新下单。');
}

$cm_site_name = isset($sitename) && $sitename !== ''
	? (string) $sitename
	: (isset($conf['sitename']) ? (string) $conf['sitename'] : 'Epay');

// 超时计算：优先使用插件侧给出的到期时间，否则退回订单生命周期
$cm_addtime_ts = isset($order['addtime']) ? strtotime($order['addtime']) : 0;
$cm_lifetime = isset($conf['order_lifetime']) && (int) $conf['order_lifetime'] > 0
	? (int) $conf['order_lifetime']
	: 1800;
$cm_expire_ts = $pay_expire_at > 0 ? $pay_expire_at : ($cm_addtime_ts > 0 ? $cm_addtime_ts + $cm_lifetime : 0);

$cm_return_url = isset($order['return_url']) && $order['return_url'] ? $order['return_url'] : '/';
$cm_back_url = '/cashier.php?trade_no=' . urlencode((string) $order['trade_no']);

$cm_out_trade_no = isset($order['out_trade_no']) && $order['out_trade_no'] ? (string) $order['out_trade_no'] : (string) $order['trade_no'];
$cm_trade_no = (string) $order['trade_no'];
$cm_product_name = isset($order['name']) ? (string) $order['name'] : (string) $ordername;
$cm_addtime_text = isset($order['addtime']) ? (string) $order['addtime'] : '';

// 根据币种/链推断展示用 badge，映射参考 cashier.php::cm_chain_tag
if (!function_exists('crypto_chain_label')) {
function crypto_chain_label(string $currency, string $chain): string
{
	if ($chain !== '') return strtoupper($chain) === 'TRON' ? 'Tron' : $chain;
	$n = strtolower($currency);
	$map = [
		'trc20' => 'Tron', 'tron' => 'Tron', '.trx' => 'Tron',
		'erc20' => 'Ethereum', '.eth' => 'Ethereum', 'ethereum' => 'Ethereum',
		'bep20' => 'BSC', 'bsc' => 'BSC', '.bnb' => 'BSC',
		'polygon' => 'Polygon', '.pol' => 'Polygon',
		'arbitrum' => 'Arbitrum', 'solana' => 'Solana', 'aptos' => 'Aptos',
		'xlayer' => 'X Layer', 'base' => 'Base', 'plasma' => 'Plasma',
	];
	foreach ($map as $kw => $tag) {
		if (strpos($n, $kw) !== false) return $tag;
	}
	return '';
}
}

if (!function_exists('crypto_display_currency')) {
function crypto_display_currency(string $currency): string
{
	// usdt.trc20 -> USDT-TRC20, USDT_TRC20 -> USDT-TRC20
	$c = strtoupper(trim($currency));
	$c = str_replace(['.', '_'], '-', $c);
	return $c;
}
}

$chain_label = crypto_chain_label($pay_currency, $pay_chain);
$currency_display = crypto_display_currency($pay_currency);

$h = function ($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); };
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=0">
<meta http-equiv="Content-Language" content="zh-cn">
<meta name="renderer" content="webkit">
<title>加密货币支付 | <?php echo $h($cm_site_name); ?></title>
<link rel="stylesheet" type="text/css" href="/assets/css/cashier-modern.css?v=2">
<style>
/* 加密收银台局部样式，复用 cashier-modern 设计语言 */
.cc-amount-hero{padding:16px 0 4px;text-align:center;}
.cc-amount-hero .cc-num{font-size:28px;font-weight:700;letter-spacing:-0.5px;color:var(--cm-text,#111);word-break:break-all;}
.cc-amount-hero .cc-num small{font-size:14px;font-weight:500;color:var(--cm-text-muted,#666);margin-left:6px;}
.cc-amount-hero .cc-fiat{font-size:13px;color:var(--cm-text-muted,#666);margin-top:4px;}
.cc-copy-row{display:flex;align-items:center;gap:8px;padding:12px 14px;background:#f6f7f9;border-radius:10px;margin-top:10px;font-size:13px;word-break:break-all;line-height:1.5;}
.cc-copy-row .cc-val{flex:1;color:#111;font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;}
.cc-copy-row .cc-lbl{color:#888;font-size:12px;flex-shrink:0;}
.cc-copy-btn{flex-shrink:0;border:1px solid #d7dbe0;background:#fff;color:#111;padding:5px 10px;border-radius:6px;font-size:12px;cursor:pointer;}
.cc-copy-btn:hover{background:#f0f2f5;}
.cc-copy-btn.is-done{background:#e8fff1;border-color:#4ccf7a;color:#1f9a47;}
.cc-qr-wrap{margin:18px auto 10px;display:flex;align-items:center;justify-content:center;}
.cc-qr-wrap #qrcode{padding:10px;background:#fff;border-radius:10px;box-shadow:0 2px 12px rgba(0,0,0,.06);}
.cc-qr-wrap #qrcode img{width:200px;height:200px;display:block;}
.cc-qr-wrap #qrcode canvas{display:block;}
.cc-tip{font-size:12px;color:var(--cm-text-muted,#666);text-align:center;line-height:1.7;padding:0 8px 4px;}
.cc-tip strong{color:#e8550a;}
.cc-actions{display:flex;gap:10px;margin-top:14px;}
.cc-actions .cm-btn{flex:1;}
.cc-fallback{margin-top:10px;text-align:center;font-size:12px;}
.cc-fallback a{color:#6b7280;text-decoration:underline;}
</style>
</head>
<body class="cm-page cm-qr">

<div class="cm-qr-shell">

	<div class="cm-qr-header">
		<a class="cm-qr-back" href="<?php echo $h($cm_back_url); ?>" aria-label="Back">
			<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
		</a>
		<div class="cm-qr-merchant"><?php echo $h($cm_site_name); ?></div>
	</div>

	<p class="cm-qr-subtitle">请使用钱包向以下地址转账 <strong><?php echo $h($currency_display); ?></strong><?php if ($chain_label !== '') { ?>（<?php echo $h($chain_label); ?> 网络）<?php } ?>，到账后系统自动确认。</p>

	<div class="cm-qr-card">

		<div class="cc-amount-hero">
			<div class="cc-num"><?php echo $h($pay_amount); ?><small><?php echo $h($currency_display); ?></small></div>
			<div class="cc-fiat">≈ <?php echo $h($pay_fiat_amount); ?> <?php echo $h(strtoupper($pay_fiat)); ?></div>
		</div>

		<div class="cc-qr-wrap">
			<div id="qrcode"></div>
		</div>

		<div class="cc-copy-row">
			<span class="cc-lbl">金额</span>
			<span class="cc-val" id="cc-amount-val"><?php echo $h($pay_amount); ?></span>
			<button type="button" class="cc-copy-btn" data-copy-target="cc-amount-val">复制</button>
		</div>

		<div class="cc-copy-row">
			<span class="cc-lbl">地址</span>
			<span class="cc-val" id="cc-address-val"><?php echo $h($pay_address); ?></span>
			<button type="button" class="cc-copy-btn" data-copy-target="cc-address-val">复制</button>
		</div>

		<?php if ($chain_label !== '') { ?>
		<div class="cm-qr-row" style="margin-top:10px;">
			<span class="cm-qr-row-label">Network</span>
			<span class="cm-qr-row-value"><?php echo $h($chain_label); ?></span>
		</div>
		<?php } ?>

		<div class="cm-qr-warning" style="margin-top:14px;">
			<svg class="cm-qr-warning-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
			<div>
				仅支持 <strong><?php echo $h($currency_display); ?></strong> <?php if ($chain_label !== '') { ?>通过 <strong><?php echo $h($chain_label); ?></strong> 网络<?php } ?>转账，请严格按上方「金额」与「地址」操作。<br>
				转错网络或转错币种将导致资产丢失，后果由付款方承担。
			</div>
		</div>

		<div class="cc-actions">
			<a class="cm-btn cm-btn-ghost" href="<?php echo $h($cm_back_url); ?>">切换支付方式</a>
			<a class="cm-btn cm-btn-primary" href="javascript:checkresult();">我已完成转账</a>
		</div>

		<?php if ($pay_fallback_url !== '') { ?>
		<div class="cc-fallback">
			扫码失败？<a href="<?php echo $h($pay_fallback_url); ?>" target="_blank" rel="noopener">前往收银页</a>
		</div>
		<?php } ?>

	</div>

	<div id="cm-qr-details" class="cm-qr-details is-open">
		<div class="cm-qr-details-head">
			<span class="cm-qr-details-title">
				<svg class="cm-qr-details-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"></polyline></svg>
				Details
			</span>
			<span id="cm-qr-countdown" class="cm-qr-countdown">--:--</span>
		</div>
		<div class="cm-qr-details-body">
			<div class="cm-qr-row">
				<span class="cm-qr-row-label">Payment ID</span>
				<span class="cm-qr-row-value">
					<span data-truncate="<?php echo $h($cm_out_trade_no); ?>" data-truncate-head="5" data-truncate-tail="5">…</span>
					<button type="button" class="cm-qr-addr-btn" data-copy="<?php echo $h($cm_out_trade_no); ?>" title="Copy">
						<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>
					</button>
				</span>
			</div>
			<div class="cm-qr-row">
				<span class="cm-qr-row-label">Fiat amount</span>
				<span class="cm-qr-row-value"><?php echo $h($pay_fiat_amount); ?> <?php echo $h(strtoupper($pay_fiat)); ?></span>
			</div>
			<?php if ($cm_product_name !== '') { ?>
			<div class="cm-qr-row">
				<span class="cm-qr-row-label">Product</span>
				<span class="cm-qr-row-value"><?php echo $h($cm_product_name); ?></span>
			</div>
			<?php } ?>
			<?php if ($cm_addtime_text !== '') { ?>
			<div class="cm-qr-row">
				<span class="cm-qr-row-label">Created at</span>
				<span class="cm-qr-row-value"><?php echo $h($cm_addtime_text); ?></span>
			</div>
			<?php } ?>
			<?php if ($pay_plugin !== '') { ?>
			<div class="cm-qr-row">
				<span class="cm-qr-row-label">Gateway</span>
				<span class="cm-qr-row-value"><?php echo $h($pay_plugin); ?></span>
			</div>
			<?php } ?>
		</div>
	</div>

</div>

<?php require SYSTEM_ROOT . 'pages/cashier_shell.php'; ?>

<script>
window.CM_CONFIG = {
	returnUrl: <?php echo json_encode($cm_return_url, JSON_UNESCAPED_SLASHES); ?>,
	expireAt: <?php echo (int) $cm_expire_ts; ?>
};
</script>

<script src="<?php echo $cdnpublic; ?>jquery/1.12.4/jquery.min.js"></script>
<script src="<?php echo $cdnpublic; ?>layer/3.1.1/layer.js"></script>
<script src="<?php echo $cdnpublic; ?>jquery.qrcode/1.0/jquery.qrcode.min.js"></script>
<script src="/assets/js/cashier-modern.js?v=2"></script>
<script>
(function(){
	var tradeNo   = <?php echo json_encode($cm_trade_no); ?>;
	var address   = <?php echo json_encode($pay_address); ?>;
	var amount    = <?php echo json_encode($pay_amount); ?>;
	var qrImage   = <?php echo json_encode($pay_qrcode); ?>;

	// 渲染二维码：优先使用插件返回的图片，否则本地生成（内容只放纯地址，兼容性最好）
	if (qrImage && qrImage.indexOf('data:image/') === 0) {
		$('#qrcode').html('<img src="' + qrImage + '" alt="QR"/>');
	} else if (qrImage && /^https?:\/\//i.test(qrImage)) {
		$('#qrcode').html('<img src="' + qrImage + '" alt="QR"/>');
	} else {
		$('#qrcode').qrcode({
			text: address,
			width: 200,
			height: 200,
			foreground: '#000000',
			background: '#ffffff',
			typeNumber: -1
		});
	}

	// 复制按钮
	$(document).on('click', '.cc-copy-btn', function(){
		var target = $('#' + $(this).data('copy-target')).text();
		var $btn = $(this);
		copyText(target, function(ok){
			if (ok) {
				$btn.addClass('is-done').text('已复制');
				setTimeout(function(){ $btn.removeClass('is-done').text('复制'); }, 1500);
			} else {
				layer.msg('复制失败，请手动选中复制', {shade:0, time:1500});
			}
		});
	});

	function copyText(text, cb){
		if (navigator.clipboard && navigator.clipboard.writeText) {
			navigator.clipboard.writeText(text).then(function(){cb(true);}, function(){fallbackCopy(text, cb);});
		} else {
			fallbackCopy(text, cb);
		}
	}
	function fallbackCopy(text, cb){
		try {
			var ta = document.createElement('textarea');
			ta.value = text;
			ta.style.position = 'fixed';
			ta.style.left = '-9999px';
			document.body.appendChild(ta);
			ta.select();
			var ok = document.execCommand('copy');
			document.body.removeChild(ta);
			cb(!!ok);
		} catch(e) { cb(false); }
	}

	// 根据 getshop.php 返回的状态决定下一跳：
	//   code=1/2 → 一律先进入「付款已检测」过渡页，由 paysuccess.php 保证至少展示一段时间后再切到「付款已完成」
	//   code=1（无 paysuccess_url）→ 兼容非加密通道老逻辑直接回跳商户
	function routeByResp(data, isUserCheck){
		if (!data) return false;
		if (data.code == 1) {
			if (data.paysuccess_url) {
				// 服务端可能直接返回 completed URL，这里强制先走 detected 过渡页
				window.location.href = '/paysuccess.php?trade_no=' + encodeURIComponent(tradeNo) + '&state=detected';
			} else {
				if (isUserCheck) layer.msg('支付成功，正在跳转中...', { icon: 16, shade: 0.1, time: 15000 });
				setTimeout(function(){ window.location.href = data.backurl; }, isUserCheck ? 1000 : 600);
			}
			return true;
		}
		if (data.code == 2 && data.paysuccess_url) {
			window.location.href = data.paysuccess_url;
			return true;
		}
		return false;
	}

	function loadmsg() {
		$.ajax({
			type: 'GET',
			dataType: 'json',
			url: '/getshop.php',
			data: { type: 'crypto', trade_no: tradeNo },
			success: function (data) {
				if (!routeByResp(data, false)) {
					setTimeout(loadmsg, 3000);
				}
			},
			error: function () { setTimeout(loadmsg, 3000); }
		});
	}
	window.checkresult = function () {
		$.ajax({
			type: 'GET',
			dataType: 'json',
			url: '/getshop.php',
			data: { type: 'crypto', trade_no: tradeNo },
			success: function (data) {
				if (!routeByResp(data, true)) {
					layer.msg('您还未完成付款，请继续付款', { shade: 0, time: 1500 });
				}
			},
			error: function () { layer.msg('服务器错误'); }
		});
	};
	setTimeout(loadmsg, 3000);
})();
</script>
</body>
</html>
