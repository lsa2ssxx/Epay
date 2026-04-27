/**
 * 支付轮询到成功：先 toast，再进 paysuccess 过渡流（staged=1 的「已检测」页）。
 * 需已加载 jQuery + layer。无 paysuccess_url 时回退为直接跳 backurl。
 */
(function (global) {
	'use strict';
	function epayOnPaid(data) {
		if (!data || parseInt(data.code, 10) !== 1) {
			return false;
		}
		if (data.paysuccess_url) {
			if (global.layer) {
				global.layer.msg('支付成功，正在进入确认…', {
					icon: 1,
					time: 1300,
					shade: 0.12,
					end: function () {
						global.location.href = data.paysuccess_url;
					}
				});
			} else {
				global.location.href = data.paysuccess_url;
			}
			return true;
		}
		if (data.backurl) {
			if (global.layer) {
				global.layer.msg('支付成功，正在跳转中…', { icon: 16, shade: 0.1, time: 15000 });
				setTimeout(function () {
					global.location.href = data.backurl;
				}, 900);
			} else {
				global.location.href = data.backurl;
			}
			return true;
		}
		return false;
	}
	global.epayOnPaid = epayOnPaid;
})(typeof window !== 'undefined' ? window : this);
