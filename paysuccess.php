<?php
/**
 * 加密货币收银台支付结果过渡页
 *
 * 统一展示两个中间态：
 *   - state=detected  链上已检测到付款，等待确认
 *   - state=completed 付款已确认完成，可返回商家
 *
 * 页面风格延续 cashier-modern（Coinify 风格），仅加密货币通道会自动跳转到此页。
 * 其它支付方式仍沿用原 getshop.php 回跳商户的流程。
 */
$is_defend = true;
$nosession = true;
require './includes/common.php';

@header('Content-Type: text/html; charset=UTF-8');

$trade_no = isset($_GET['trade_no']) ? daddslashes($_GET['trade_no']) : '';
$state    = isset($_GET['state']) ? daddslashes($_GET['state']) : 'completed';
if ($state !== 'detected' && $state !== 'completed') {
	$state = 'completed';
}

if ($trade_no === '') {
	sysmsg('缺少订单号参数');
	exit;
}

$row = $DB->getRow("SELECT * FROM pre_order WHERE trade_no='{$trade_no}' limit 1");
if (!$row) {
	sysmsg('该订单号不存在，请返回来源地重新发起请求！');
	exit;
}

// 根据订单真实状态与 ext 中 detection 信息，修正 URL 中用户传入的 state
$ext = [];
if (!empty($row['ext'])) {
	$decoded = @unserialize($row['ext']);
	if (is_array($decoded)) $ext = $decoded;
}

$has_detected = !empty($ext['detected_at']);
$order_paid   = $row['status'] >= 1;

if ($order_paid) {
	$state = 'completed';
} elseif ($state === 'completed' && !$order_paid) {
	// 前端传 completed 但实际未确认：退回 detected（若有检测记录）
	$state = $has_detected ? 'detected' : 'detected';
}

/* ---------- 展示字段组装 ---------- */
$cm_site_name = isset($conf['sitename']) && $conf['sitename'] !== ''
	? (string) $conf['sitename']
	: 'Epay';

$payment_id    = $row['out_trade_no'] ? (string) $row['out_trade_no'] : (string) $row['trade_no'];
$transaction_id = (string) ($ext['detected_tx'] ?? ($row['api_trade_no'] ?? ''));
$product_name  = (string) ($row['name'] ?? '');

$detected_at = $has_detected ? (string) $ext['detected_at'] : '';
$completed_at = $row['endtime'] ? (string) $row['endtime'] : ($detected_at ?: (string) $row['addtime']);
$show_time_label = $state === 'completed' ? '完成时间' : '检测时间';
$show_time_value = $state === 'completed' ? $completed_at : ($detected_at ?: $completed_at);

$pay_currency = (string) ($ext['currency'] ?? '');
$pay_amount   = (string) ($ext['amount'] ?? '');
$fiat         = strtoupper((string) ($ext['fiat'] ?? 'CNY'));
$fiat_amount  = (string) ($ext['fiat_amount'] ?? $row['realmoney']);
$fiat_unit    = $fiat === 'USD' ? '美元' : ($fiat === 'CNY' ? '元' : $fiat);

if (!function_exists('paysuccess_display_currency')) {
	function paysuccess_display_currency(string $currency): string {
		$c = strtoupper(trim($currency));
		if ($c === '') return '';
		$c = str_replace(['.', '_'], '-', $c);
		return $c;
	}
}
$currency_display = paysuccess_display_currency($pay_currency);

/* ---------- 文案：按 issue 截图匹配，中文版 ---------- */
if ($state === 'completed') {
	$cm_title   = '付款已完成';
	$cm_desc    = $product_name !== ''
		? ('您已成功完成向 ' . $product_name . ' 的付款。')
		: '您已成功完成本次付款。';
	$cm_btn     = '返回商家';
} else {
	$cm_title   = '付款已检测';
	$cm_desc    = '您的付款已出现在区块链上。一旦确认完成，商户将收到通知，订单即可完成。';
	$cm_desc2   = '付款确认后您将会收到一封电子邮件通知。';
	$cm_btn     = '返回商家';
}

$h = function ($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); };

/**
 * 截断展示字符串（前 N 后 N），用于交易 ID / 订单号等较长字段
 */
$truncate = function (string $s, int $head = 5, int $tail = 5): string {
	$s = trim($s);
	$len = strlen($s);
	if ($len <= $head + $tail + 3) return $s;
	return substr($s, 0, $head) . ' … ' . substr($s, -$tail);
};
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=0">
<title><?php echo $h($cm_title . ' | ' . $cm_site_name); ?></title>
<link href="/assets/css/cashier-modern.css?v=3" rel="stylesheet" type="text/css">
</head>
<body class="cm-page cm-ps-page" data-state="<?php echo $h($state); ?>">

<div class="cm-ps-shell">

	<h1 class="cm-ps-heading"><?php echo $h($cm_title); ?></h1>

	<div class="cm-ps-card">
		<div class="cm-ps-icon-wrap">
			<?php if ($state === 'completed') { ?>
				<div class="cm-ps-icon cm-ps-icon-done" aria-label="Completed">
					<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
						<polyline points="5 13 10 18 20 7"></polyline>
					</svg>
				</div>
			<?php } else { ?>
				<div class="cm-ps-icon cm-ps-icon-detecting" aria-label="Detecting">
					<span class="cm-ps-spinner"></span>
					<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
						<polyline points="5 13 10 18 20 7"></polyline>
					</svg>
				</div>
			<?php } ?>
		</div>

		<div class="cm-ps-title"><?php echo $h($cm_title); ?></div>
		<div class="cm-ps-desc"><?php echo $h($cm_desc); ?></div>
		<?php if ($state === 'detected' && !empty($cm_desc2)) { ?>
		<div class="cm-ps-desc cm-ps-desc-dim"><?php echo $h($cm_desc2); ?></div>
		<?php } ?>

		<div class="cm-ps-rows">
			<div class="cm-ps-row">
				<span class="cm-ps-row-label">支付 ID</span>
				<span class="cm-ps-row-value">
					<button type="button" class="cm-ps-copy" data-copy="<?php echo $h($payment_id); ?>" title="复制">
						<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>
					</button>
					<code><?php echo $h($truncate($payment_id, 5, 5)); ?></code>
				</span>
			</div>
			<div class="cm-ps-row">
				<span class="cm-ps-row-label"><?php echo $h($show_time_label); ?></span>
				<span class="cm-ps-row-value"><?php echo $h($show_time_value); ?></span>
			</div>
			<?php if ($transaction_id !== '') { ?>
			<div class="cm-ps-row">
				<span class="cm-ps-row-label">交易 ID</span>
				<span class="cm-ps-row-value">
					<button type="button" class="cm-ps-copy" data-copy="<?php echo $h($transaction_id); ?>" title="复制">
						<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>
					</button>
					<code class="cm-ps-tx"><?php echo $h($truncate($transaction_id, 5, 5)); ?></code>
				</span>
			</div>
			<?php } ?>

			<?php if ($state === 'detected' && $pay_amount !== '') { ?>
			<div class="cm-ps-row">
				<span class="cm-ps-row-label">预期付款</span>
				<span class="cm-ps-row-value cm-ps-row-amount">
					<span class="cm-ps-amount-main"><?php echo $h($pay_amount); ?> <?php echo $h($currency_display); ?></span>
					<?php if ($fiat_amount !== '') { ?>
					<span class="cm-ps-amount-fiat">$ <?php echo $h($fiat_amount); ?></span>
					<?php } ?>
				</span>
			</div>
			<?php } ?>

			<div class="cm-ps-row">
				<span class="cm-ps-row-label"><?php echo $state === 'completed' ? '付款金额' : '到账金额'; ?></span>
				<span class="cm-ps-row-value cm-ps-row-amount">
					<?php if ($pay_amount !== '') { ?>
						<span class="cm-ps-amount-main"><?php echo $h($pay_amount); ?> <?php echo $h($currency_display); ?></span>
					<?php } else { ?>
						<span class="cm-ps-amount-main"><?php echo $h($fiat_amount); ?> <?php echo $h($fiat_unit); ?></span>
					<?php } ?>
					<?php if ($fiat_amount !== '' && $pay_amount !== '') { ?>
						<span class="cm-ps-amount-fiat"><?php echo $fiat === 'CNY' ? '￥ ' : '$ '; ?><?php echo $h($fiat_amount); ?></span>
					<?php } ?>
				</span>
			</div>
		</div>

		<div class="cm-ps-actions">
			<a id="cm-ps-back" class="cm-btn cm-btn-secondary" href="javascript:void(0);"><?php echo $h($cm_btn); ?></a>
		</div>
	</div>

</div>

<?php require SYSTEM_ROOT . 'pages/cashier_shell.php'; ?>

<script>
window.CM_CONFIG = {
	returnUrl: <?php echo json_encode($row['return_url'] ? $row['return_url'] : '/', JSON_UNESCAPED_SLASHES); ?>,
	refreshSeconds: 12,
	expireAt: 0
};
window.CM_PAYSUCCESS = {
	tradeNo: <?php echo json_encode($trade_no); ?>,
	state: <?php echo json_encode($state); ?>
};
</script>
<script src="/assets/js/cashier-modern.js?v=3"></script>
<script>
(function(){
	var cfg = window.CM_PAYSUCCESS || {};
	var tradeNo = cfg.tradeNo;
	var currentState = cfg.state;

	// 复制按钮（同 crypto.php 的 fallback 行为）
	document.addEventListener('click', function(e){
		var btn = e.target && e.target.closest && e.target.closest('.cm-ps-copy');
		if (!btn) return;
		var text = btn.getAttribute('data-copy') || '';
		if (!text) return;
		copyText(text, function(ok){
			if (ok) {
				showToast('已复制');
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
	function showToast(msg){
		var t = document.getElementById('cm-toast');
		if (!t) return;
		t.textContent = msg;
		t.classList.add('is-show');
		setTimeout(function(){ t.classList.remove('is-show'); }, 1400);
	}

	// 「返回商家」：调用 getshop.php 获取 backurl（兼容超时失效场景）
	var backBtn = document.getElementById('cm-ps-back');
	if (backBtn) {
		backBtn.addEventListener('click', function(){
			backBtn.classList.add('is-loading');
			fetchBackUrl(function(backurl){
				if (backurl) {
					window.location.href = backurl;
				} else {
					backBtn.classList.remove('is-loading');
					showToast('当前无法返回，请稍后重试');
				}
			});
		});
	}

	function fetchBackUrl(cb){
		var xhr = new XMLHttpRequest();
		xhr.open('GET', '/getshop.php?type=crypto&trade_no=' + encodeURIComponent(tradeNo), true);
		xhr.onreadystatechange = function(){
			if (xhr.readyState !== 4) return;
			try {
				var d = JSON.parse(xhr.responseText);
				if (d && d.code == 1 && d.backurl) {
					cb(d.backurl);
				} else {
					cb((window.CM_CONFIG && window.CM_CONFIG.returnUrl) || '/');
				}
			} catch(e) {
				cb((window.CM_CONFIG && window.CM_CONFIG.returnUrl) || '/');
			}
		};
		xhr.send();
	}

	// detected 状态下继续轮询，一旦确认成功则自动切换到 completed
	function pollForComplete(){
		if (currentState !== 'detected') return;
		var xhr = new XMLHttpRequest();
		xhr.open('GET', '/getshop.php?type=crypto&trade_no=' + encodeURIComponent(tradeNo), true);
		xhr.onreadystatechange = function(){
			if (xhr.readyState !== 4) return;
			try {
				var d = JSON.parse(xhr.responseText);
				if (d && d.code == 1) {
					// 付款已确认，刷新为 completed 视图
					window.location.href = '/paysuccess.php?trade_no=' + encodeURIComponent(tradeNo) + '&state=completed';
					return;
				}
			} catch(e) {}
			setTimeout(pollForComplete, 3000);
		};
		xhr.send();
	}
	if (currentState === 'detected') {
		setTimeout(pollForComplete, 3000);
	}
})();
</script>
</body>
</html>
