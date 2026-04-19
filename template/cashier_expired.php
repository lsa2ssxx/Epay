<?php
/**
 * 收银台 - 订单超时（Payment Expired）
 *
 * 由 cashier.php 在订单超过有效期时 include。
 * 期望的上下文变量：
 *   $row         pre_order 行
 *   $trade_no    订单号
 *   $cm_site_name 站点名（页脚展示）
 *   $cdnpublic   CDN 前缀
 */
$out_trade_no = isset($row['out_trade_no']) ? $row['out_trade_no'] : $trade_no;
$pid_full = (string) $out_trade_no;
$pid_short = strlen($pid_full) > 12
	? substr($pid_full, 0, 5) . ' … ' . substr($pid_full, -5)
	: $pid_full;
$return_url = isset($row['return_url']) && $row['return_url'] ? $row['return_url'] : '/';
?>
<!DOCTYPE html>
<html lang="zh-CN"><head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=0">
<title>Payment Expired | <?php echo htmlspecialchars((string) $cm_site_name, ENT_QUOTES, 'UTF-8'); ?></title>
<link href="/assets/css/cashier-modern.css?v=1" rel="stylesheet" type="text/css">
</head>
<body class="cm-page">

<div class="cm-expired">
	<h2 class="cm-expired-title">Payment Expired</h2>
	<div class="cm-expired-card">
		<div class="cm-expired-illu">
			<svg viewBox="0 0 200 140" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
				<rect x="20" y="30" width="120" height="80" rx="6" fill="#eef0f6" stroke="#c8cdda" stroke-width="2"/>
				<polygon points="60,75 80,40 100,75" fill="#fff" stroke="#5764ff" stroke-width="2"/>
				<line x1="80" y1="52" x2="80" y2="65" stroke="#5764ff" stroke-width="2" stroke-linecap="round"/>
				<circle cx="80" cy="70" r="1.6" fill="#5764ff"/>
				<polygon points="92,90 110,58 128,90" fill="#fff" stroke="#1a1f4e" stroke-width="2"/>
				<line x1="110" y1="68" x2="110" y2="80" stroke="#1a1f4e" stroke-width="2" stroke-linecap="round"/>
				<circle cx="110" cy="85" r="1.6" fill="#1a1f4e"/>
				<g transform="translate(140,30)">
					<circle cx="14" cy="14" r="8" fill="#1a1f4e"/>
					<rect x="6" y="22" width="16" height="40" rx="3" fill="#5764ff"/>
					<rect x="6" y="62" width="6" height="34" rx="2" fill="#1a1f4e"/>
					<rect x="16" y="62" width="6" height="34" rx="2" fill="#1a1f4e"/>
				</g>
			</svg>
		</div>
		<div class="cm-expired-text">
			订单已过期。任何在过期时间之后到账的款项将自动原路退回。<br>
			如有疑问，请联系商户客服并提供下方订单号。
		</div>
		<div class="cm-expired-id">
			<span class="cm-id-label">Payment ID</span>
			<span class="cm-id-value" data-copy="<?php echo htmlspecialchars($pid_full, ENT_QUOTES, 'UTF-8'); ?>" title="点击复制">
				<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>
				<?php echo htmlspecialchars($pid_short, ENT_QUOTES, 'UTF-8'); ?>
			</span>
		</div>
		<a class="cm-expired-readmore" href="javascript:;">
			<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>
			Read more 了解如何顺利完成下一次支付。
		</a>
	</div>

	<div class="cm-expired-actions">
		<a class="cm-btn cm-btn-primary" href="<?php echo htmlspecialchars($return_url, ENT_QUOTES, 'UTF-8'); ?>">Return To Merchant</a>
		<a class="cm-btn cm-btn-ghost" href="/user/index.php">Top Up My Wallet</a>
	</div>
</div>

<?php
require ROOT . 'includes/pages/cashier_shell.php';
?>

<script>
window.CM_CONFIG = {
	returnUrl: <?php echo json_encode($return_url, JSON_UNESCAPED_SLASHES); ?>
};
</script>
<script src="/assets/js/cashier-modern.js?v=1"></script>
</body></html>
