<?php
$is_defend = true;
$nosession = true;
require './includes/common.php';
require_once SYSTEM_ROOT . 'pay_type_icon.php';

@header('Content-Type: text/html; charset=UTF-8');

$other = isset($_GET['other']) ? true : false;
$trade_no = daddslashes($_GET['trade_no']);
$sitename = base64_decode(daddslashes($_GET['sitename']));

$row = $DB->getRow("SELECT * FROM pre_order WHERE trade_no='{$trade_no}' limit 1");
if (!$row) sysmsg('该订单号不存在，请返回来源地重新发起请求！');
if ($row['status'] == 1) sysmsg('该订单已完成支付，请勿重复支付');

$gid = $DB->getColumn("SELECT gid FROM pre_user WHERE uid='{$row['uid']}' limit 1");
$paytype = \lib\Channel::getTypes($row['uid'], $gid);

if (strpos($_SERVER['HTTP_USER_AGENT'], 'MicroMessenger') !== false) {
	$paytype = array_values($paytype);
	foreach ($paytype as $i => $s) {
		if ($s['name'] == 'wxpay') {
			$temp = $paytype[$i];
			$paytype[$i] = $paytype[0];
			$paytype[0] = $temp;
		}
	}
}

/* ---------- 订单生命周期 / 超时判定 ---------- */
$cm_lifetime = isset($conf['order_lifetime']) && (int) $conf['order_lifetime'] > 0
	? (int) $conf['order_lifetime']
	: 1800;
$cm_addtime_ts = isset($row['addtime']) ? strtotime($row['addtime']) : 0;
$cm_expire_ts = $cm_addtime_ts > 0 ? $cm_addtime_ts + $cm_lifetime : 0;
$cm_now = time();
$cm_is_expired = isset($_GET['expired'])
	|| ($cm_expire_ts > 0 && $cm_now >= $cm_expire_ts);

$cm_site_name = $sitename ? $sitename : (isset($conf['sitename']) ? $conf['sitename'] : 'Epay');

/* ---------- 超时分支：直接渲染超时模板 ---------- */
if ($cm_is_expired) {
	require TEMPLATE_ROOT . 'cashier_expired.php';
	exit;
}

/* ---------- 支付方式分组（Most popular / Available） ----------
 * 启发式：USDT/USDC/TRX/BTC/ETH 等热门币种或带 trc20/erc20/bep20/polygon 的归 Most popular，
 *         其余归 Available。
 */
$cm_paytype_list = array_values($paytype);
$cm_popular = [];
$cm_available = [];
$cm_popular_keywords = ['usdt', 'usdc', 'btc', 'eth', 'trx', 'bnb', 'pol', 'trc20', 'erc20', 'bep20', 'polygon'];
foreach ($cm_paytype_list as $pt) {
	$nameLow = strtolower((string) $pt['name']);
	$showLow = strtolower((string) $pt['showname']);
	$isPop = false;
	foreach ($cm_popular_keywords as $kw) {
		if (strpos($nameLow, $kw) !== false || strpos($showLow, $kw) !== false) {
			$isPop = true;
			break;
		}
	}
	if ($isPop) {
		$cm_popular[] = $pt;
	} else {
		$cm_available[] = $pt;
	}
}

/**
 * 根据支付方式名称推断"网络/链"标签
 * @param string $name
 * @return string
 */
function cm_chain_tag($name)
{
	$n = strtolower((string) $name);
	$map = [
		'trc20' => 'Tron',
		'tron' => 'Tron',
		'.trx' => 'Tron',
		'erc20' => 'Ethereum',
		'.eth' => 'Ethereum',
		'ethereum' => 'Ethereum',
		'bep20' => 'BSC',
		'bsc' => 'BSC',
		'.bnb' => 'BSC',
		'polygon' => 'Polygon',
		'.pol' => 'Polygon',
		'arbitrum' => 'Arbitrum',
		'solana' => 'Solana',
		'aptos' => 'Aptos',
		'xlayer' => 'X Layer',
		'base' => 'Base',
		'plasma' => 'Plasma',
		'alipay' => 'Alipay',
		'wxpay' => 'WeChat',
		'wechat' => 'WeChat',
		'qqpay' => 'QQ',
		'jdpay' => 'JD',
		'bank' => 'Bank',
		'paypal' => 'PayPal',
	];
	foreach ($map as $kw => $tag) {
		if (strpos($n, $kw) !== false) return $tag;
	}
	return '';
}

/**
 * 渲染单个支付方式条目
 * @param array $pt
 * @param string $trade_no
 * @return string
 */
function cm_render_item($pt, $trade_no)
{
	$icon = pay_type_icon_html($pt['name'], 'cm-icon-img');
	$showname = $pt['showname'] ? $pt['showname'] : $pt['name'];
	$short = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $pt['name']));
	if (strlen($short) > 6) $short = substr($short, 0, 6);
	$tag = cm_chain_tag($pt['name']);
	$search = strtolower($pt['name'] . ' ' . $showname . ' ' . $tag);
	$showname_h = htmlspecialchars((string) $showname, ENT_QUOTES, 'UTF-8');
	$short_h = htmlspecialchars((string) $short, ENT_QUOTES, 'UTF-8');
	$tag_h = htmlspecialchars((string) $tag, ENT_QUOTES, 'UTF-8');
	$search_h = htmlspecialchars($search, ENT_QUOTES, 'UTF-8');
	$tid = (int) $pt['id'];
	$tradeno_h = htmlspecialchars((string) $trade_no, ENT_QUOTES, 'UTF-8');

	$html = '<div class="cm-item" data-typeid="' . $tid . '" data-tradeno="' . $tradeno_h . '" data-search="' . $search_h . '">';
	$html .= '<span class="cm-item-icon">' . $icon . '</span>';
	$html .= '<div class="cm-item-body">';
	$html .= '<div class="cm-item-title"><span class="cm-item-code">' . $short_h . '</span><span class="cm-item-name">' . $showname_h . '</span></div>';
	$html .= '</div>';
	if ($tag_h !== '') {
		$html .= '<span class="cm-item-tag">' . $tag_h . '</span>';
	}
	$html .= '</div>';
	return $html;
}
?>
<!DOCTYPE html>
<html lang="zh-CN"><head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=0">
<title>收银台 | <?php echo htmlspecialchars((string) $cm_site_name, ENT_QUOTES, 'UTF-8'); ?></title>
<link href="/assets/css/cashier-modern.css?v=1" rel="stylesheet" type="text/css">
<link href="/assets/css/pay-type-icon.css" rel="stylesheet" type="text/css">
</head>
<body class="cm-page">

<input type="hidden" name="trade_no" value="<?php echo htmlspecialchars((string) $trade_no, ENT_QUOTES, 'UTF-8'); ?>">

<?php if ($other) { ?>
<div class="cm-shell">
	<h1 class="cm-title">支付方式维护中</h1>
	<p class="cm-subtitle">当前支付方式暂时关闭维护，请更换其他方式支付。</p>
	<?php if (in_array('qqpay', array_column($cm_paytype_list, 'name'))) { ?>
	<div class="cm-card" style="text-align:center;">
		<p style="color:var(--cm-text);">如果您需要微信支付，请将微信余额转到 QQ 后再选择 QQ 钱包支付。</p>
		<p><a href="./wx.html">点击查看微信余额转到 QQ 钱包教程</a></p>
	</div>
	<?php } ?>
</div>
<?php } else { ?>

<div class="cm-shell">
	<h1 class="cm-title">Select Crypto</h1>
	<p class="cm-subtitle">Choose your preferred cryptocurrency.</p>

	<div class="cm-card">

		<div class="cm-search">
			<input type="text" id="cm-search-input" placeholder="Search" autocomplete="off">
		</div>

		<div class="cm-refresh">
			<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 4 23 10 17 10"></polyline><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"></path></svg>
			<span>Next update in <span id="cm-refresh-sec">12</span>s</span>
		</div>

		<div class="cm-list" id="cm-list">

			<?php if (count($cm_popular) > 0) { ?>
				<div class="cm-group-title">Most popular</div>
				<?php foreach ($cm_popular as $pt) {
					echo cm_render_item($pt, $trade_no);
				} ?>
			<?php } ?>

			<?php if (count($cm_available) > 0) { ?>
				<div class="cm-group-title">Available</div>
				<?php foreach ($cm_available as $pt) {
					echo cm_render_item($pt, $trade_no);
				} ?>
			<?php } ?>

			<?php if (count($cm_popular) + count($cm_available) === 0) { ?>
				<div class="cm-empty" style="display:block;">暂无可用支付方式</div>
			<?php } ?>

			<div class="cm-empty" id="cm-empty">没有匹配的支付方式</div>
		</div>

	</div>

	<!-- 订单基础信息（折叠展示，便于核对） -->
	<div class="cm-card" style="margin-top:14px;">
		<div style="display:flex;justify-content:space-between;font-size:13px;color:var(--cm-text-muted);">
			<span>订单号</span>
			<span style="color:var(--cm-text);"><?php echo htmlspecialchars((string) $trade_no, ENT_QUOTES, 'UTF-8'); ?></span>
		</div>
		<div style="display:flex;justify-content:space-between;font-size:13px;color:var(--cm-text-muted);margin-top:8px;">
			<span>商品名称</span>
			<span style="color:var(--cm-text);"><?php echo htmlspecialchars((string) $row['name'], ENT_QUOTES, 'UTF-8'); ?></span>
		</div>
		<div style="display:flex;justify-content:space-between;font-size:13px;color:var(--cm-text-muted);margin-top:8px;">
			<span>需支付</span>
			<span style="color:var(--cm-text);font-weight:600;">
				<?php echo htmlspecialchars((string) ($row['realmoney'] ? $row['realmoney'] : $row['money']), ENT_QUOTES, 'UTF-8'); ?> 元
			</span>
		</div>
	</div>

</div>

<?php } ?>

<?php require SYSTEM_ROOT . 'pages/cashier_shell.php'; ?>

<script>
window.CM_CONFIG = {
	returnUrl: <?php echo json_encode($row['return_url'] ? $row['return_url'] : '/', JSON_UNESCAPED_SLASHES); ?>,
	refreshSeconds: 12,
	expireAt: <?php echo (int) $cm_expire_ts; ?>
};
</script>
<script src="/assets/js/cashier-modern.js?v=1"></script>
</body></html>
