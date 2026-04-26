/* Coinify-style Cashier interactions
 * Scope: cashier.php and cashier_expired view
 * Globals: window.CM_CONFIG = { returnUrl, refreshSeconds, expireAt }
 */
(function () {
	'use strict';

	var doc = document;
	var STORAGE_THEME = 'cm_theme';

	function $(sel, root) { return (root || doc).querySelector(sel); }
	function $$(sel, root) { return Array.prototype.slice.call((root || doc).querySelectorAll(sel)); }

	/* ---------- Theme ---------- */
	function applyTheme(theme) {
		var html = doc.documentElement;
		if (theme === 'dark') {
			html.setAttribute('data-cm-theme', 'dark');
		} else {
			html.removeAttribute('data-cm-theme');
		}
		var sw = $('#cm-theme-switch');
		if (sw) sw.checked = (theme === 'dark');
	}
	function initTheme() {
		var saved = null;
		try { saved = localStorage.getItem(STORAGE_THEME); } catch (e) {}
		applyTheme(saved === 'dark' ? 'dark' : 'light');
		var sw = $('#cm-theme-switch');
		if (sw) {
			sw.addEventListener('change', function () {
				var next = sw.checked ? 'dark' : 'light';
				try { localStorage.setItem(STORAGE_THEME, next); } catch (e) {}
				applyTheme(next);
			});
		}
	}

	/* ---------- Drawer (Help) ---------- */
	function initDrawer() {
		var drawer = $('#cm-drawer');
		var mask = $('#cm-drawer-mask');
		var openBtn = $('#cm-help-btn');
		var closeBtn = $('#cm-drawer-close');
		if (!drawer || !openBtn) return;

		function open() {
			drawer.classList.add('is-open');
			if (mask) mask.classList.add('is-open');
		}
		function close() {
			drawer.classList.remove('is-open');
			if (mask) mask.classList.remove('is-open');
		}
		openBtn.addEventListener('click', open);
		if (closeBtn) closeBtn.addEventListener('click', close);
		if (mask) mask.addEventListener('click', close);

		$$('.cm-acc', drawer).forEach(function (acc) {
			var head = $('.cm-acc-head', acc);
			if (!head) return;
			head.addEventListener('click', function () {
				acc.classList.toggle('is-open');
			});
		});
	}

	/* ---------- Leave Payment Modal ---------- */
	function initLeaveModal() {
		var modal = $('#cm-leave-modal');
		var openBtn = $('#cm-close-btn');
		var closeBtn = $('#cm-leave-close');
		var keepBtn = $('#cm-leave-keep');
		var leaveBtn = $('#cm-leave-go');
		if (!modal || !openBtn) return;

		function open() { modal.classList.add('is-open'); }
		function close() { modal.classList.remove('is-open'); }

		openBtn.addEventListener('click', open);
		if (closeBtn) closeBtn.addEventListener('click', close);
		if (keepBtn) keepBtn.addEventListener('click', close);
		if (leaveBtn) {
			leaveBtn.addEventListener('click', function () {
				var url = (window.CM_CONFIG && window.CM_CONFIG.returnUrl) || '/';
				try { window.location.href = url; }
				catch (e) { window.location.href = '/'; }
			});
		}
		modal.addEventListener('click', function (e) {
			if (e.target === modal) close();
		});
	}

	/* ---------- List search ---------- */
	function initSearch() {
		var input = $('#cm-search-input');
		var list = $('#cm-list');
		var empty = $('#cm-empty');
		if (!input || !list) return;

		function norm(s) { return (s || '').toString().toLowerCase(); }

		input.addEventListener('input', function () {
			var q = norm(input.value).trim();
			var anyVisible = false;
			$$('.cm-item', list).forEach(function (it) {
				if (!q) {
					it.style.display = '';
					anyVisible = true;
					return;
				}
				var hay = norm(it.getAttribute('data-search'));
				var hit = hay.indexOf(q) !== -1;
				it.style.display = hit ? '' : 'none';
				if (hit) anyVisible = true;
			});
			$$('.cm-group-title', list).forEach(function (g) {
				var next = g.nextElementSibling;
				var hasVisible = false;
				while (next && !next.classList.contains('cm-group-title')) {
					if (next.classList.contains('cm-item') && next.style.display !== 'none') {
						hasVisible = true; break;
					}
					next = next.nextElementSibling;
				}
				g.style.display = hasVisible ? '' : 'none';
			});
			if (empty) empty.style.display = anyVisible ? 'none' : 'block';
		});
	}

	/* ---------- Item click highlight ----------
	 * 一级/二级条目都已渲染为 <a href>，默认跳转即可。
	 * 这里仅做"立即点亮"反馈，避免点击后整页空白前的视觉空挡。
	 * 兼容旧版（非 <a>）：保留 typeid+tradeno 的回退跳转逻辑。
	 */
	function initItemSelect() {
		$$('.cm-item').forEach(function (it) {
			if (it.classList.contains('is-disabled')) return;
			it.addEventListener('click', function (e) {
				$$('.cm-item').forEach(function (o) { o.classList.remove('is-active'); });
				it.classList.add('is-active');
				if (it.tagName.toLowerCase() === 'a' && it.getAttribute('href')) return;
				var typeid = it.getAttribute('data-typeid');
				var tradeNo = it.getAttribute('data-tradeno');
				if (!typeid || !tradeNo) return;
				e.preventDefault();
				window.location.href = './submit2.php?typeid=' + encodeURIComponent(typeid) +
					'&trade_no=' + encodeURIComponent(tradeNo);
			});
		});
	}

	/* ---------- Refresh countdown ---------- */
	function initRefresh() {
		var node = $('#cm-refresh-sec');
		if (!node) return;
		var total = parseInt((window.CM_CONFIG && window.CM_CONFIG.refreshSeconds) || 60, 10);
		var left = total;
		setInterval(function () {
			left -= 1;
			if (left <= 0) left = total;
			node.textContent = left;
		}, 1000);
	}

	/* ---------- Expire countdown / redirect ---------- */
	function initExpire() {
		var expireAt = (window.CM_CONFIG && window.CM_CONFIG.expireAt) || 0;
		if (!expireAt) return;
		function tick() {
			var remain = expireAt - Math.floor(Date.now() / 1000);
			if (remain <= 0) {
				var u = new URL(window.location.href);
				u.searchParams.set('expired', '1');
				window.location.replace(u.toString());
			}
		}
		setInterval(tick, 1000);
	}

	/* ---------- Copy ---------- */
	function copyToClipboard(text) {
		if (navigator.clipboard && navigator.clipboard.writeText) {
			return navigator.clipboard.writeText(text);
		}
		return new Promise(function (resolve, reject) {
			try {
				var ta = doc.createElement('textarea');
				ta.value = text;
				ta.style.position = 'fixed';
				ta.style.opacity = '0';
				doc.body.appendChild(ta);
				ta.select();
				doc.execCommand('copy');
				doc.body.removeChild(ta);
				resolve();
			} catch (e) { reject(e); }
		});
	}
	function showToast(msg) {
		var t = $('#cm-toast');
		if (!t) {
			t = doc.createElement('div');
			t.id = 'cm-toast';
			t.className = 'cm-toast';
			doc.body.appendChild(t);
		}
		t.textContent = msg;
		t.classList.add('is-show');
		clearTimeout(t._h);
		t._h = setTimeout(function () { t.classList.remove('is-show'); }, 1600);
	}
	function initCopy() {
		$$('[data-copy]').forEach(function (el) {
			el.addEventListener('click', function () {
				var v = el.getAttribute('data-copy');
				if (!v) return;
				copyToClipboard(v).then(function () { showToast('Copied'); });
			});
		});
	}

	/* ---------- QR Receive Page ---------- */
	function pad2(n) { n = String(n); return n.length < 2 ? '0' + n : n; }

	function fmtCountdown(remain) {
		if (remain < 0) remain = 0;
		var m = Math.floor(remain / 60);
		var s = remain % 60;
		if (m >= 60) {
			var h = Math.floor(m / 60);
			m = m % 60;
			return pad2(h) + ':' + pad2(m) + ':' + pad2(s);
		}
		return pad2(m) + ':' + pad2(s);
	}

	function truncateMid(text, head, tail) {
		text = String(text || '');
		head = head || 6;
		tail = tail || 4;
		if (text.length <= head + tail + 3) return text;
		return text.slice(0, head) + ' … ' + text.slice(-tail);
	}

	function initQrDetails() {
		var box = $('#cm-qr-details');
		if (!box) return;
		var head = $('.cm-qr-details-head', box);
		if (!head) return;
		head.addEventListener('click', function (e) {
			if (e.target.closest && e.target.closest('[data-copy]')) return;
			box.classList.toggle('is-open');
		});
	}

	function initQrCountdown() {
		var node = $('#cm-qr-countdown');
		if (!node) return;
		var expireAt = (window.CM_CONFIG && window.CM_CONFIG.expireAt) || 0;
		if (!expireAt) { node.textContent = '--:--'; return; }
		function tick() {
			var remain = expireAt - Math.floor(Date.now() / 1000);
			if (remain <= 0) {
				node.textContent = '00:00';
				node.classList.add('is-warning');
				return;
			}
			node.textContent = fmtCountdown(remain);
			if (remain < 120) node.classList.add('is-warning');
		}
		tick();
		setInterval(tick, 1000);
	}

	function initQrTruncate() {
		$$('[data-truncate]').forEach(function (el) {
			var src = el.getAttribute('data-truncate');
			if (!src) return;
			var head = parseInt(el.getAttribute('data-truncate-head') || '6', 10);
			var tail = parseInt(el.getAttribute('data-truncate-tail') || '4', 10);
			el.textContent = truncateMid(src, head, tail);
		});
	}

	/* ---------- boot ---------- */
	function ready(fn) {
		if (doc.readyState !== 'loading') fn();
		else doc.addEventListener('DOMContentLoaded', fn);
	}
	ready(function () {
		initTheme();
		initDrawer();
		initLeaveModal();
		initSearch();
		initItemSelect();
		initRefresh();
		initExpire();
		initCopy();
		initQrDetails();
		initQrCountdown();
		initQrTruncate();
	});
})();
