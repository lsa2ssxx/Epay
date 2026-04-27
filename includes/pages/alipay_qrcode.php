<?php
/**
 * 官方支付宝 - 最终收款码展示页（Coinify 风格）
 *
 * 上下文变量（由 \lib\Payment::echoDefault 注入）：
 *   $cdnpublic, $order, $conf, $sitename, $ordername, $siteurl, $code_url
 */
if (!defined('IN_PLUGIN')) exit();

$cm_site_name = isset($sitename) && $sitename !== ''
	? (string) $sitename
	: (isset($conf['sitename']) ? (string) $conf['sitename'] : 'Epay');

$cm_merchant = $cm_site_name;

$cm_addtime_ts = isset($order['addtime']) ? strtotime($order['addtime']) : 0;
$cm_lifetime = isset($conf['order_lifetime']) && (int) $conf['order_lifetime'] > 0
	? (int) $conf['order_lifetime']
	: 1800;
$cm_expire_ts = $cm_addtime_ts > 0 ? $cm_addtime_ts + $cm_lifetime : 0;

$cm_return_url = isset($order['return_url']) && $order['return_url'] ? $order['return_url'] : '/';
$cm_back_url = '/cashier.php?trade_no=' . urlencode((string) $order['trade_no']);

$cm_pay_amount = isset($order['realmoney']) ? $order['realmoney'] : (isset($order['money']) ? $order['money'] : '0.00');
$cm_pay_amount_h = htmlspecialchars((string) $cm_pay_amount, ENT_QUOTES, 'UTF-8');

$cm_out_trade_no = isset($order['out_trade_no']) ? (string) $order['out_trade_no'] : (string) $order['trade_no'];
$cm_trade_no = (string) $order['trade_no'];

$cm_product_name = isset($order['name']) ? (string) $order['name'] : (string) $ordername;
$cm_product_name_h = htmlspecialchars($cm_product_name, ENT_QUOTES, 'UTF-8');

$cm_addtime_h = htmlspecialchars(isset($order['addtime']) ? (string) $order['addtime'] : '', ENT_QUOTES, 'UTF-8');

$cm_code_url_h = htmlspecialchars((string) $code_url, ENT_QUOTES, 'UTF-8');
$cm_merchant_h = htmlspecialchars($cm_merchant, ENT_QUOTES, 'UTF-8');
$cm_out_trade_no_h = htmlspecialchars($cm_out_trade_no, ENT_QUOTES, 'UTF-8');
$cm_trade_no_h = htmlspecialchars($cm_trade_no, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=0">
<meta http-equiv="Content-Language" content="zh-cn">
<meta name="renderer" content="webkit">
<title>支付宝扫码支付 | <?php echo $cm_merchant_h; ?></title>
<link rel="stylesheet" type="text/css" href="/assets/css/cashier-modern.css?v=2">
</head>
<body class="cm-page cm-qr">

<div class="cm-qr-shell">

	<div class="cm-qr-header">
		<a class="cm-qr-back" href="<?php echo htmlspecialchars($cm_back_url, ENT_QUOTES, 'UTF-8'); ?>" aria-label="Back">
			<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
		</a>
		<div class="cm-qr-merchant"><?php echo $cm_merchant_h; ?></div>
	</div>

	<p class="cm-qr-subtitle">使用支付宝扫码或在支付宝内打开链接，复制下方信息完成付款。</p>

	<div class="cm-qr-card">

		<div class="cm-qr-row">
			<span class="cm-qr-row-label">Payment amount</span>
			<span class="cm-qr-row-value">
				<span class="cm-qr-amount-num">¥<?php echo $cm_pay_amount_h; ?></span>
				<span class="cm-qr-currency-badge" title="CNY">¥</span>
			</span>
		</div>

		<div class="cm-qr-row">
			<span class="cm-qr-row-label">Network</span>
			<span class="cm-qr-row-value">
				<span class="cm-qr-network-icon"><img src="/assets/icon/alipay.ico" alt="Alipay"></span>
				<span>Alipay</span>
			</span>
		</div>

		<div class="cm-qr-row">
			<span class="cm-qr-row-label">Wallet address</span>
			<span class="cm-qr-row-value">
				<span data-truncate="<?php echo $cm_code_url_h; ?>" data-truncate-head="6" data-truncate-tail="6">…</span>
				<button type="button" class="cm-qr-addr-btn" data-copy="<?php echo $cm_code_url_h; ?>" title="Copy payment URL">
					<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>
				</button>
			</span>
		</div>

		<div class="cm-qr-canvas">
			<div id="qrcode"></div>
			<div class="cm-qr-canvas-logo"><img src="/assets/icon/alipay.ico" alt="Alipay"></div>
		</div>

		<div class="cm-qr-warning">
			<svg class="cm-qr-warning-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
			<div>
				请使用 <strong>支付宝</strong> App 扫一扫支付，或在支付宝内打开支付链接。<br>
				通过其他渠道支付可能无法到账，由此产生的资金风险将由付款方自行承担。
			</div>
		</div>

		<div class="cm-qr-actions open_app" style="display:none;">
			<a class="cm-btn cm-btn-primary btn-open-app">打开支付宝 App 继续付款</a>
			<a class="cm-btn cm-btn-ghost btn-check" href="javascript:checkresult();">我已付款，返回查看订单</a>
		</div>

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
					<span data-truncate="<?php echo $cm_out_trade_no_h; ?>" data-truncate-head="5" data-truncate-tail="5">…</span>
					<button type="button" class="cm-qr-addr-btn" data-copy="<?php echo $cm_out_trade_no_h; ?>" title="Copy">
						<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>
					</button>
				</span>
			</div>
			<div class="cm-qr-row">
				<span class="cm-qr-row-label">Payment amount</span>
				<span class="cm-qr-row-value">¥<?php echo $cm_pay_amount_h; ?> CNY</span>
			</div>
			<?php if ($cm_product_name !== '') { ?>
			<div class="cm-qr-row">
				<span class="cm-qr-row-label">Product</span>
				<span class="cm-qr-row-value"><?php echo $cm_product_name_h; ?></span>
			</div>
			<?php } ?>
			<?php if ($cm_addtime_h !== '') { ?>
			<div class="cm-qr-row">
				<span class="cm-qr-row-label">Created at</span>
				<span class="cm-qr-row-value"><?php echo $cm_addtime_h; ?></span>
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
<script src="/assets/js/pay-success-bridge.js?v=1"></script>
<script src="<?php echo $cdnpublic; ?>jquery.qrcode/1.0/jquery.qrcode.min.js"></script>
<script src="/assets/js/cashier-modern.js?v=2"></script>
<script>
	var code_url = <?php echo json_encode((string) $code_url, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
	var code_type = code_url.indexOf('data:image/') > -1 ? 1 : 0;
	if (code_type === 0) {
		var url_scheme = 'alipays://platformapi/startapp?appId=20000067&url=' + encodeURIComponent(code_url);
		$('#qrcode').qrcode({
			text: code_url,
			width: 200,
			height: 200,
			foreground: '#000000',
			background: '#ffffff',
			typeNumber: -1
		});
	} else {
		$('#qrcode').html('<img src="' + code_url + '" alt="QR" style="width:200px;height:200px;"/>');
	}

	function loadmsg() {
		$.ajax({
			type: 'GET',
			dataType: 'json',
			url: '/getshop.php',
			data: { type: 'alipay', trade_no: <?php echo json_encode($cm_trade_no); ?> },
			success: function (data) {
				if (window.epayOnPaid && epayOnPaid(data)) return;
				if (data.code == 1) {
					layer.msg('支付成功，正在跳转中...', { icon: 16, shade: 0.1, time: 15000 });
					setTimeout(function () { window.location.href = data.backurl; }, 1000);
				} else {
					setTimeout('loadmsg()', 2000);
				}
			},
			error: function () { setTimeout('loadmsg()', 2000); }
		});
	}
	function checkresult() {
		$.ajax({
			type: 'GET',
			dataType: 'json',
			url: '/getshop.php',
			data: { type: 'alipay', trade_no: <?php echo json_encode($cm_trade_no); ?> },
			success: function (data) {
				if (window.epayOnPaid && epayOnPaid(data)) return;
				if (data.code == 1) {
					layer.msg('支付成功，正在跳转中...', { icon: 16, shade: 0.1, time: 15000 });
					setTimeout(function () { window.location.href = data.backurl; }, 1000);
				} else {
					layer.msg('您还未完成付款，请继续付款', { shade: 0, time: 1500 });
				}
			},
			error: function () { layer.msg('服务器错误'); }
		});
	}

	var isMobile = function () {
		var ua = navigator.userAgent;
		var ipad = ua.match(/(iPad).*OS\s([\d_]+)/);
		var isIphone = !ipad && ua.match(/(iPhone\sOS)\s([\d_]+)/);
		var isAndroid = ua.match(/(Android)\s+([\d.]+)/);
		return isIphone || isAndroid;
	};

	function wx_open() {
		layer.alert('请点击屏幕右上角，<b>在浏览器打开</b>即可跳转支付。<br/><font color="red">支付成功后，回到微信查看结果</font>', { title: '支付提示' });
	}

	window.onload = function () {
		if (isMobile()) {
			window.onpopstate = function (e) {
				if (e.state == 'forward' || confirm('是否取消支付并返回？')) {
					window.history.back();
				} else {
					e.preventDefault();
					window.history.pushState('forward', null, '');
				}
			};
			window.history.pushState('forward', null, '');
		}
		if (isMobile() && code_type === 0) {
			$('.open_app').show().css('display', 'flex');
			if (navigator.userAgent.indexOf('MicroMessenger/') > 0) {
				$('.btn-open-app').attr('href', 'javascript:wx_open()');
			} else {
				$('.btn-open-app').attr('href', url_scheme);
				if (navigator.userAgent.indexOf('EdgA/') === -1 && $(window).height() > $(window).width()) {
					setTimeout(function () { window.location.href = url_scheme; }, 1000);
				}
			}
		}
		setTimeout('loadmsg()', 2000);
	};
</script>
</body>
</html>
