<?php
/**
 * Coinify 风格收银台「全局壳」注入器
 *
 * 用法（在 submit2.php 等渲染入口）：
 *
 *   require_once SYSTEM_ROOT.'pages/cashier_shell_inject.php';
 *   cm_shell_start([
 *       'site_name'  => $cm_site_name,
 *       'return_url' => $order['return_url'],
 *   ]);
 *   // ... 调用 Payment::echoDefault($result); ...
 *   cm_shell_finish();
 *
 * 行为：
 *   - 用 ob_start() 捕获插件渲染的 HTML
 *   - 仅当响应是 HTML（包含 </body>）时注入 cashier-modern 资源 + 全局壳 HTML + 配置脚本
 *   - 命中 ?cm_shell=0 或常量 CM_SHELL_DISABLE 时直接透传，便于回退对比
 *   - 对纯 jump/json/外跳页面无影响
 */
if (!function_exists('cm_shell_start')) {

	/**
	 * 启动输出缓冲
	 * @param array $opts 可选键: site_name, return_url, expire_at
	 */
	function cm_shell_start(array $opts = []): void
	{
		// 旁路开关
		if (defined('CM_SHELL_DISABLE') || (isset($_GET['cm_shell']) && $_GET['cm_shell'] === '0')) {
			$GLOBALS['__cm_shell_active'] = false;
			return;
		}
		$GLOBALS['__cm_shell_active'] = true;
		$GLOBALS['__cm_shell_opts'] = $opts;
		ob_start();
		// 兜底：插件层意外 exit/sysmsg 也保证完成注入
		register_shutdown_function('cm_shell_finish');
	}

	/**
	 * 结束缓冲并执行注入（多次调用安全）
	 */
	function cm_shell_finish(): void
	{
		if (empty($GLOBALS['__cm_shell_active'])) {
			return;
		}
		$GLOBALS['__cm_shell_active'] = false;
		$html = ob_get_clean();
		if ($html === false) {
			return;
		}
		$opts = isset($GLOBALS['__cm_shell_opts']) && is_array($GLOBALS['__cm_shell_opts'])
			? $GLOBALS['__cm_shell_opts']
			: [];
		echo cm_shell_inject($html, $opts);
	}

	/**
	 * 实际注入逻辑
	 *
	 * @param string $html 原始 HTML
	 * @param array  $opts site_name / return_url / expire_at
	 */
	function cm_shell_inject(string $html, array $opts = []): string
	{
		$lower = strtolower($html);
		// 非 HTML（JSON/JS 跳转脚本/纯 form 自动提交）直接透传
		if (strpos($lower, '</body>') === false) {
			return $html;
		}

		$site_name = isset($opts['site_name']) && $opts['site_name'] !== ''
			? (string) $opts['site_name']
			: (isset($GLOBALS['conf']['sitename']) ? (string) $GLOBALS['conf']['sitename'] : 'Epay');
		$return_url = isset($opts['return_url']) && $opts['return_url'] !== ''
			? (string) $opts['return_url']
			: '/';
		$expire_at = isset($opts['expire_at']) ? (int) $opts['expire_at'] : 0;

		$assetVer = '1';
		$cssLink = '<link rel="stylesheet" type="text/css" href="/assets/css/cashier-modern.css?v=' . $assetVer . '">';

		$shell = cm_shell_render_html($site_name);

		$config = json_encode(
			[
				'returnUrl' => $return_url,
				'expireAt' => $expire_at,
				'refreshSeconds' => 12,
			],
			JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
		);
		$jsBoot = '<script>window.CM_CONFIG = window.CM_CONFIG || ' . $config . ';</script>'
			. '<script src="/assets/js/cashier-modern.js?v=' . $assetVer . '"></script>';

		// 注入 CSS：优先放到 </head> 之前；否则放到 <body> 之后
		if (stripos($html, '</head>') !== false) {
			$html = preg_replace('#</head>#i', $cssLink . '</head>', $html, 1);
		} else {
			$html = preg_replace('#<body[^>]*>#i', '$0' . $cssLink, $html, 1);
		}

		// 注入壳 HTML 与启动脚本到 </body> 之前
		$html = preg_replace('#</body>#i', $shell . $jsBoot . '</body>', $html, 1);

		// 给 body 添加标识类，便于覆盖样式
		$html = preg_replace_callback(
			'#<body([^>]*)>#i',
			function ($m) {
				$attrs = $m[1];
				if (preg_match('/\bclass\s*=\s*"([^"]*)"/i', $attrs)) {
					$attrs = preg_replace('/\bclass\s*=\s*"([^"]*)"/i', 'class="$1 cm-shell-inject"', $attrs);
				} elseif (preg_match("/\bclass\s*=\s*'([^']*)'/i", $attrs)) {
					$attrs = preg_replace("/\bclass\s*=\s*'([^']*)'/i", "class='$1 cm-shell-inject'", $attrs);
				} else {
					$attrs .= ' class="cm-shell-inject"';
				}
				return '<body' . $attrs . '>';
			},
			$html,
			1
		);

		return $html;
	}

	/**
	 * 渲染壳 HTML（与 cashier_shell.php 同源；此处直接捕获该文件输出，复用维护）
	 */
	function cm_shell_render_html(string $site_name): string
	{
		$cm_site_name = $site_name;
		ob_start();
		require SYSTEM_ROOT . 'pages/cashier_shell.php';
		return ob_get_clean();
	}
}
