<?php
$is_defend = true;
$nosession = true;
require './includes/common.php';
require_once SYSTEM_ROOT . 'pay_type_icon.php';
require_once SYSTEM_ROOT . 'pay_type_category.php';

@header('Content-Type: text/html; charset=UTF-8');

$other = isset($_GET['other']) ? true : false;
$trade_no = daddslashes($_GET['trade_no']);
$sitename = base64_decode(daddslashes($_GET['sitename']));
$cm_currency_param = isset($_GET['currency']) ? trim((string) $_GET['currency']) : '';
$cm_currency_param = preg_replace('/[^A-Za-z0-9_\-]/', '', $cm_currency_param);

$row = $DB->getRow("SELECT * FROM pre_order WHERE trade_no='{$trade_no}' limit 1");
if (!$row) sysmsg('该订单号不存在，请返回来源地重新发起请求！');
if ($row['status'] == 1) sysmsg('该订单已完成支付，请勿重复支付');

$gid = $DB->getColumn("SELECT gid FROM pre_user WHERE uid='{$row['uid']}' limit 1");

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

/* ---------- 三级菜单数据（结合订单金额探测真实通道池；无数据库副作用） ---------- */
$cm_order_money = isset($row['money']) ? (float) $row['money'] : 0;
$cm_categories = \lib\Channel::getCategorizedTypes($row['uid'], $gid, $cm_order_money);
$cm_cashier_qs = '&cashier=1';
if ($sitename) {
	$cm_cashier_qs .= '&sitename=' . urlencode(base64_encode($sitename));
}

/**
 * 单网络快通道：直接生成 submit2.php 链接
 * 否则：生成 cashier.php?currency=XXX 进入二级页面
 */
$cm_self = (strpos($_SERVER['REQUEST_URI'] ?? '', '?') !== false ? strtok($_SERVER['REQUEST_URI'], '?') : ($_SERVER['REQUEST_URI'] ?? '/cashier.php'));
$cm_qs_base = 'trade_no=' . urlencode($trade_no);
if ($sitename) {
	$cm_qs_base .= '&sitename=' . urlencode(base64_encode($sitename));
}

/**
 * 通用 chevron SVG（带显式 width/height，避免 CSS 缺失时撑爆布局）
 */
function cm_chev_svg()
{
	return '<svg class="cm-item-chev" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="9 18 15 12 9 6"></polyline></svg>';
}

/**
 * 一级币种图标：
 *   - 法币 / 原生币 / 通用方式 → 直接用 .ico（assets/icon/<file>.ico）
 *   - 稳定币（USDT/USDC/BTC 等没有独立 .ico）→ 用文字 badge + 品牌色
 */
function cm_currency_icon_html($cat)
{
	$kind = $cat['kind'];
	$icon = (string) $cat['icon'];
	$key = strtoupper((string) $cat['key']);

	$has_native_ico = [
		'tron.trx', 'ethereum.eth', 'bsc.bnb', 'polygon.pol',
		'solana.sol', 'aptos.apt',
		'alipay', 'wxpay', 'wechat', 'qqpay', 'jdpay', 'bank', 'paypal',
	];
	if (in_array($icon, $has_native_ico, true)) {
		return pay_type_icon_html($icon, 'cm-icon-img');
	}

	$brand_bg = [
		'USDT' => '#26A17B',
		'USDC' => '#2775CA',
		'DAI'  => '#F4B731',
		'BTC'  => '#F7931A',
		'ETH'  => '#627EEA',
	];
	$bg = $brand_bg[$key] ?? '#5B6CFF';
	$label = htmlspecialchars(substr($key, 0, 4), ENT_QUOTES, 'UTF-8');

	return '<span class="cm-token-badge" style="background:' . $bg . ';">' . $label . '</span>';
}

/**
 * 渲染一级（币种）条目
 */
function cm_render_currency_item($cat, $trade_no, $self, $qs_base, $cashier_qs)
{
	$key = $cat['key'];
	$name = $cat['name'];
	$kind = $cat['kind'];
	$count = count($cat['networks']);

	if ($count === 1) {
		$only = $cat['networks'][0];
		$ready = !isset($only['cashier_ready']) || $only['cashier_ready'];
		if (!empty($only['is_roll']) && !empty($only['roll_id'])) {
			$href = './submit2.php?rollid=' . (int) $only['roll_id'] . '&trade_no=' . urlencode((string) $trade_no) . $cashier_qs;
		} else {
			$href = './submit2.php?typeid=' . (int) $only['typeid'] . '&trade_no=' . urlencode((string) $trade_no) . $cashier_qs;
		}
		if (!$ready) {
			$href = '';
		}
		$tag_h = htmlspecialchars($only['network_label'] !== '' ? $only['network_label'] : ($kind === 'fiat' ? 'Instant' : ''), ENT_QUOTES, 'UTF-8');
	} else {
		$href = $self . '?' . $qs_base . '&currency=' . urlencode($key);
		$tag_h = htmlspecialchars($count . ' networks', ENT_QUOTES, 'UTF-8');
		$ready = true;
	}

	$icon_html = cm_currency_icon_html($cat);
	$name_h = htmlspecialchars((string) $name, ENT_QUOTES, 'UTF-8');
	$short_h = htmlspecialchars(strtoupper(substr($key, 0, 6)), ENT_QUOTES, 'UTF-8');
	$href_h = htmlspecialchars($href, ENT_QUOTES, 'UTF-8');
	$search = strtolower($key . ' ' . $name . ' ' . $kind);
	foreach ($cat['networks'] as $n) {
		$search .= ' ' . strtolower($n['name'] . ' ' . $n['showname'] . ' ' . $n['network_label']);
	}
	$search_h = htmlspecialchars($search, ENT_QUOTES, 'UTF-8');

	$wrap = ($ready && $href !== '') ? 'a' : 'div';
	$cls = 'cm-item' . (($wrap === 'div' && $count === 1 && !$ready) ? ' cm-item-disabled' : '');
	$open = '<' . $wrap . ' class="' . $cls . '"';
	if ($wrap === 'a') {
		$open .= ' href="' . $href_h . '"';
	}
	$open .= ' data-search="' . $search_h . '">';
	$html = $open;
	$html .= '<span class="cm-item-icon">' . $icon_html . '</span>';
	$html .= '<div class="cm-item-body">';
	$html .= '<div class="cm-item-title"><span class="cm-item-code">' . $short_h . '</span><span class="cm-item-name">' . $name_h . '</span></div>';
	$html .= '</div>';
	if ($tag_h !== '') {
		$html .= '<span class="cm-item-tag">' . $tag_h . '</span>';
	}
	if ($wrap === 'a') {
		$html .= cm_chev_svg();
	} elseif ($count === 1 && !$ready) {
		$html .= '<span class="cm-item-muted">暂不可下单</span>';
	}
	$html .= '</' . $wrap . '>';

	return $html;
}

/**
 * 渲染二级（网络）条目
 */
function cm_render_network_item($net, $trade_no, $cashier_qs)
{
	$icon_html = pay_type_icon_html($net['name'], 'cm-icon-img');
	$showname = $net['showname'] ?: $net['name'];
	$short = '';
	$tag = $net['network_label'] !== '' ? $net['network_label'] : '';
	$ready = !isset($net['cashier_ready']) || $net['cashier_ready'];

	$showname_h = htmlspecialchars((string) $showname, ENT_QUOTES, 'UTF-8');
	$short_h = htmlspecialchars((string) $short, ENT_QUOTES, 'UTF-8');
	$tag_h = htmlspecialchars((string) $tag, ENT_QUOTES, 'UTF-8');
	$search = strtolower($net['name'] . ' ' . $showname . ' ' . $tag);
	$search_h = htmlspecialchars($search, ENT_QUOTES, 'UTF-8');
	if (!empty($net['is_roll']) && !empty($net['roll_id'])) {
		$href = './submit2.php?rollid=' . (int) $net['roll_id'] . '&trade_no=' . urlencode((string) $trade_no) . $cashier_qs;
	} else {
		$href = './submit2.php?typeid=' . (int) $net['typeid'] . '&trade_no=' . urlencode((string) $trade_no) . $cashier_qs;
	}
	$href_h = htmlspecialchars($href, ENT_QUOTES, 'UTF-8');

	$wrap = $ready ? 'a' : 'div';
	$cls = 'cm-item' . ($ready ? '' : ' cm-item-disabled');
	$open = '<' . $wrap . ' class="' . $cls . '"';
	if ($wrap === 'a') {
		$open .= ' href="' . $href_h . '"';
	}
	$open .= ' data-search="' . $search_h . '">';
	$html = $open;
	$html .= '<span class="cm-item-icon">' . $icon_html . '</span>';
	$html .= '<div class="cm-item-body">';
	$html .= '<div class="cm-item-title"><span class="cm-item-code">' . $short_h . '</span><span class="cm-item-name">' . $showname_h . '</span></div>';
	$html .= '</div>';
	if ($tag_h !== '') {
		$html .= '<span class="cm-item-tag">' . $tag_h . '</span>';
	}
	if ($ready) {
		$html .= cm_chev_svg();
	} else {
		$html .= '<span class="cm-item-muted">暂不可下单</span>';
	}
	$html .= '</' . $wrap . '>';

	return $html;
}

/* ---------- 选定的二级页面（带 ?currency=...） ---------- */
$cm_active_cat = null;
if ($cm_currency_param !== '') {
	foreach ($cm_categories as $c) {
		if (strcasecmp($c['key'], $cm_currency_param) === 0) {
			$cm_active_cat = $c;
			break;
		}
	}
	// 二级若只有 1 个网络，直接 302 进入支付通道
	if ($cm_active_cat && count($cm_active_cat['networks']) === 1) {
		$only = $cm_active_cat['networks'][0];
		$onlyReady = !isset($only['cashier_ready']) || $only['cashier_ready'];
		if ($onlyReady) {
			if (!empty($only['is_roll']) && !empty($only['roll_id'])) {
				header('Location: ./submit2.php?rollid=' . (int) $only['roll_id'] . '&trade_no=' . urlencode($trade_no) . $cm_cashier_qs, true, 302);
			} else {
				header('Location: ./submit2.php?typeid=' . (int) $only['typeid'] . '&trade_no=' . urlencode($trade_no) . $cm_cashier_qs, true, 302);
			}
			exit;
		}
	}
}

/* ---------- 一级分组：加密 / 法币 ---------- */
$cm_crypto = [];
$cm_fiat = [];
$cm_other = [];
if ($cm_active_cat === null) {
	foreach ($cm_categories as $c) {
		if ($c['kind'] === 'crypto') $cm_crypto[] = $c;
		elseif ($c['kind'] === 'fiat') $cm_fiat[] = $c;
		else $cm_other[] = $c;
	}
}

$cm_back_url = $cm_self . '?' . $cm_qs_base;
?>
<!DOCTYPE html>
<html lang="zh-CN"><head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=0">
<title><?php echo $cm_active_cat ? 'Select Network' : 'Select Currency'; ?> | <?php echo htmlspecialchars((string) $cm_site_name, ENT_QUOTES, 'UTF-8'); ?></title>
<link href="/assets/css/cashier-modern.css?v=4" rel="stylesheet" type="text/css">
<link href="/assets/css/pay-type-icon.css" rel="stylesheet" type="text/css">
</head>
<body class="cm-page">

<input type="hidden" name="trade_no" value="<?php echo htmlspecialchars((string) $trade_no, ENT_QUOTES, 'UTF-8'); ?>">

<?php if ($other) { ?>
<div class="cm-shell">
	<h1 class="cm-title">支付方式维护中</h1>
	<p class="cm-subtitle">当前支付方式暂时关闭维护，请更换其他方式支付。</p>
</div>
<?php } else { ?>

<div class="cm-shell">

<?php if ($cm_active_cat !== null) { /* ---------- 二级：网络列表 ---------- */ ?>

	<div class="cm-page-head">
		<a class="cm-back-btn" href="<?php echo htmlspecialchars($cm_back_url, ENT_QUOTES, 'UTF-8'); ?>" aria-label="Back">
			<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="15 18 9 12 15 6"></polyline></svg>
			<span>Currencies</span>
		</a>
	</div>
	<h1 class="cm-title">Select Network</h1>
	<p class="cm-subtitle">
		<?php echo htmlspecialchars((string) $cm_active_cat['name'], ENT_QUOTES, 'UTF-8'); ?>
		· 选择您要使用的网络
	</p>

	<div class="cm-card">
		<div class="cm-search">
			<input type="text" id="cm-search-input" placeholder="Search network" autocomplete="off">
		</div>

		<div class="cm-list" id="cm-list">
			<?php
			$cm_net_avail = [];
			$cm_net_unavail = [];
			foreach ($cm_active_cat['networks'] as $n) {
				$cm_nr = !array_key_exists('cashier_ready', $n) || $n['cashier_ready'];
				if ($cm_nr) {
					$cm_net_avail[] = $n;
				} else {
					$cm_net_unavail[] = $n;
				}
			}
			if (count($cm_net_avail) > 0) { ?>
				<div class="cm-section-label">可用</div>
				<?php foreach ($cm_net_avail as $n) {
					echo cm_render_network_item($n, $trade_no, $cm_cashier_qs);
				}
			}
			if (count($cm_net_unavail) > 0) { ?>
				<div class="cm-section-label cm-section-label-muted">不可用</div>
				<?php foreach ($cm_net_unavail as $n) {
					echo cm_render_network_item($n, $trade_no, $cm_cashier_qs);
				}
			} ?>
			<div class="cm-empty" id="cm-empty">没有匹配的网络</div>
		</div>
	</div>

<?php } else { /* ---------- 一级：币种列表 ---------- */ ?>

	<h1 class="cm-title">Select Currency</h1>
	<p class="cm-subtitle">Choose your preferred payment currency.</p>

	<div class="cm-card">

		<div class="cm-search">
			<input type="text" id="cm-search-input" placeholder="Search currency" autocomplete="off">
		</div>

		<div class="cm-refresh">
			<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="23 4 23 10 17 10"></polyline><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"></path></svg>
			<span>Next update in <span id="cm-refresh-sec">12</span>s</span>
		</div>

		<div class="cm-list" id="cm-list">

			<?php if (count($cm_crypto) > 0) { ?>
				<div class="cm-group-title">Crypto</div>
				<?php foreach ($cm_crypto as $c) { echo cm_render_currency_item($c, $trade_no, $cm_self, $cm_qs_base, $cm_cashier_qs); } ?>
			<?php } ?>

			<?php if (count($cm_fiat) > 0) { ?>
				<div class="cm-group-title">Fiat</div>
				<?php foreach ($cm_fiat as $c) { echo cm_render_currency_item($c, $trade_no, $cm_self, $cm_qs_base, $cm_cashier_qs); } ?>
			<?php } ?>

			<?php if (count($cm_other) > 0) { ?>
				<div class="cm-group-title">Others</div>
				<?php foreach ($cm_other as $c) { echo cm_render_currency_item($c, $trade_no, $cm_self, $cm_qs_base, $cm_cashier_qs); } ?>
			<?php } ?>

			<?php if (count($cm_categories) === 0) { ?>
				<div class="cm-empty" style="display:block;">暂无可用支付方式</div>
			<?php } else { ?>
				<div class="cm-empty" id="cm-empty">没有匹配的币种</div>
			<?php } ?>
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

<?php } ?>

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
<script src="/assets/js/cashier-modern.js?v=4"></script>
</body></html>
