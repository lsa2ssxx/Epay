<?php
/**
 * Coinify 风格收银台全局壳片段
 * - 顶部右上角 X 关闭按钮
 * - 右下角 ? 帮助按钮
 * - 帮助抽屉（FAQ + 深色主题切换）
 * - 离开支付确认弹窗
 * - 底部 POWERED BY 页脚
 *
 * 使用变量：
 *   $cm_site_name : string  当前站点名（用于页脚展示）
 *
 * 调用方需自行 include 本文件，并提前在 <head> 引入：
 *   /assets/css/cashier-modern.css
 *   并在 </body> 前引入 /assets/js/cashier-modern.js
 */
if (!isset($cm_site_name)) {
	$cm_site_name = isset($conf['sitename']) ? $conf['sitename'] : '';
}
$cm_site_name_h = htmlspecialchars((string) $cm_site_name, ENT_QUOTES, 'UTF-8');
?>
<!-- Top-right close (X) -->
<button type="button" id="cm-close-btn" class="cm-close-btn" aria-label="Close">
	<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
</button>

<!-- Bottom-right help (?) -->
<button type="button" id="cm-help-btn" class="cm-help-btn" aria-label="Help">
	<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"></path><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
</button>

<!-- Footer -->
<div class="cm-footer">
	<span>POWERED BY</span>
	<strong><?php echo $cm_site_name_h ? $cm_site_name_h : 'Epay'; ?></strong>
</div>

<!-- Help drawer mask -->
<div id="cm-drawer-mask" class="cm-drawer-mask"></div>

<!-- Help drawer -->
<aside id="cm-drawer" class="cm-drawer" aria-hidden="true">
	<div class="cm-drawer-head">
		<h3>Learn more</h3>
		<button type="button" id="cm-drawer-close" class="cm-drawer-close" aria-label="Close">
			<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
		</button>
	</div>
	<div class="cm-drawer-body">

		<div class="cm-acc">
			<button type="button" class="cm-acc-head">
				<svg class="cm-acc-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg>
				<span>1. How to pay?</span>
			</button>
			<div class="cm-acc-body">
				<p>选择支付方式后，按页面提示完成下一步：扫码或将订单金额转账至显示的钱包地址即可。</p>
				<p>请务必使用与所选网络一致的链发送资产，否则可能造成资产丢失。</p>
			</div>
		</div>

		<div class="cm-acc">
			<button type="button" class="cm-acc-head">
				<svg class="cm-acc-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg>
				<span>2. Why are some coins unavailable?</span>
			</button>
			<div class="cm-acc-body">
				<p>下列原因可能导致部分币种暂不可用：</p>
				<ul>
					<li>支付金额过低或过高</li>
					<li>渠道临时维护、网络拥堵或风控</li>
					<li>商户尚未启用该币种</li>
				</ul>
				<p>如需使用暂不可用币种，可调整金额或稍后重试。</p>
			</div>
		</div>

		<div class="cm-acc">
			<button type="button" class="cm-acc-head">
				<svg class="cm-acc-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg>
				<span>3. Refunds</span>
			</button>
			<div class="cm-acc-body">
				<p>订单到期后到账的款项将原路退回。请保留订单号以便查询与申诉。</p>
			</div>
		</div>

		<div class="cm-acc">
			<button type="button" class="cm-acc-head">
				<svg class="cm-acc-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg>
				<span>4. How to contact us?</span>
			</button>
			<div class="cm-acc-body">
				<p>如遇支付异常，请联系商户客服并提供订单号，我们将协助核对到账与退款。</p>
			</div>
		</div>

	</div>
	<div class="cm-drawer-foot">
		<span>Dark theme</span>
		<label class="cm-switch">
			<input type="checkbox" id="cm-theme-switch">
			<span class="cm-slider"></span>
		</label>
	</div>
</aside>

<!-- Leave Payment modal -->
<div id="cm-leave-modal" class="cm-modal-mask" role="dialog" aria-modal="true" aria-labelledby="cm-leave-title">
	<div class="cm-modal">
		<div class="cm-modal-head">
			<h3 id="cm-leave-title">Leave payment?</h3>
			<button type="button" id="cm-leave-close" aria-label="Close">
				<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
			</button>
		</div>
		<div class="cm-modal-body">
			离开本次支付将失去当前价格保护，并跳回到您发起支付的来源页面。
		</div>
		<div class="cm-modal-foot">
			<button type="button" id="cm-leave-keep" class="cm-btn cm-btn-secondary">Keep Order</button>
			<button type="button" id="cm-leave-go" class="cm-btn cm-btn-primary">Leave Payment</button>
		</div>
	</div>
</div>

<!-- Toast -->
<div id="cm-toast" class="cm-toast"></div>
