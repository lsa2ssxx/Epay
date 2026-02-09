### Epay 安全修复补丁方案清单

> 适用目录：`/Users/nervix/网站项目/Epay`  
> 目标：在不大改业务逻辑的前提下，优先堵住“可导致资金/订单完整性被篡改或后台被接管”的高危面。

---

### 结论摘要（面向支付系统的威胁模型）

- **未发现典型 WebShell 明牌后门**（如 `eval($_POST...)` 直接执行类入口）。
- **存在多项支付系统不应接受的 P0/P1 风险**：
  - 大量 **TLS 证书校验被关闭**（`CURLOPT_SSL_VERIFYPEER/VERIFYHOST=false`），可被中间人劫持篡改第三方交互结果。
  - `install/update.php` **可被未授权直接执行**，具备改库/破坏可用性与完整性的风险。
  - PayPal webhook 验签中 **信任请求头 `PAYPAL-CERT-URL` 拉取证书**，存在 **SSRF/伪造验签** 风险。
  - 多处 **拼接 SQL / 动态列名**（例如 `admin/ajax_profitsharing.php`）存在注入面。
  - 密码哈希使用 **MD5 变种**（`getMd5Pwd`），不符合支付系统账号安全要求。

---

### 优先级说明

- **P0**：可直接影响资金/订单状态、可被远程利用、或可导致系统被接管/严重破坏。
- **P1**：高风险漏洞面或一旦与其他漏洞链路组合将造成严重后果。
- **P2**：工程加固与长期治理项，建议排期完成。

---

### P0（必须尽快修）

#### 1) 全局启用 TLS 证书校验（禁止默认不校验）

- **涉及文件（核心）**：
  - `includes/functions.php`：`curl_get()`、`get_curl()`、`check_proxy()`
- **涉及范围（常见）**：
  - 多个支付插件/SDK 内部 curl 调用（建议逐步收敛到统一的安全 HTTP 客户端）
- **问题表现**：
  - `CURLOPT_SSL_VERIFYPEER=false`、`CURLOPT_SSL_VERIFYHOST=false`
- **修复要点**：
  - 默认改为：
    - `CURLOPT_SSL_VERIFYPEER = true`
    - `CURLOPT_SSL_VERIFYHOST = 2`
  - 如存在“自签名/内网”需求：
    - 仅允许对**白名单域名**显式降级（默认绝不降级）
    - 且仍需尽可能采用内部 CA 或固定证书链路
- **回归验证**：
  - 选取你实际在用的 1-2 个支付通道进行下单/支付/回调全链路测试

#### 2) 修 PayPal webhook：禁止信任 `PAYPAL-CERT-URL` 请求头（SSRF/伪造验签）

- **涉及文件**：`plugins/paypal/paypal_plugin.php`
- **问题点**：
  - 通过 `get_curl($_SERVER['HTTP_PAYPAL_CERT_URL'])` 拉取公钥参与验签
  - 该 header 可被攻击者伪造，导致：
    - 拉取攻击者控制的证书/公钥 → **伪造验签通过**
    - 指向内网/本机地址 → **SSRF**
- **修复方案**（推荐顺序）：
  - **方案 B（推荐）**：按 PayPal 官方流程做 webhook 验证（避免依赖可控的证书 URL 头）
  - **方案 A（快速止血）**：严格校验 `PAYPAL-CERT-URL`
    - 必须 `https`
    - host 必须在 PayPal 官方域名白名单（不要用模糊匹配）
    - 禁止 IP/内网/localhost
    - 拉取证书时 **必须开启 TLS 校验**
  - 增加 **事件重放保护**（事件 ID 去重）

#### 3) 下线或限制 `install/` 目录的 Web 访问（含未授权升级脚本）

- **涉及文件**：
  - `install/index.php`
  - `install/update.php`
  - `install/*.sql`
- **问题点**：
  - `install/update.php` 直接连库执行 SQL，无管理员校验/口令校验
- **修复要点（推荐顺序）**：
  - **生产环境直接删除 `install/` 目录**
  - 或在 WebServer 层禁止访问（仅允许运维内网 / CLI）
  - 至少给 `update.php` 增加：管理员登录校验 + 强口令/一次性 token + IP 白名单 + 强制 HTTPS

---

### P1（高风险，建议紧跟 P0）

#### 4) 修 SQL 注入面：动态列名/拼 SQL → 白名单 + 参数绑定

- **已定位的高风险样例**：
  - `admin/ajax_profitsharing.php`：
    - `$_POST['column']` 被直接拼进 SQL 条件/统计语句
- **修复要点**：
  - 对 `column/sort` 一律做 **白名单映射**（允许字段列表）
  - `LIKE` 使用绑定参数，不拼接 `'%{$kw}%'`
  - 对 `IN (...)` 使用占位符展开或改为固定枚举
- **回归验证**：
  - 后台列表筛选、统计、导出 CSV 功能是否正常

#### 5) 密码哈希迁移：`getMd5Pwd()` → `password_hash()/password_verify()`

- **涉及文件**：
  - `includes/functions.php`：`getMd5Pwd()`
  - 登录/改密逻辑：`user/ajax.php`、`user/ajax2.php`、`admin/ajax_user.php` 等
- **修复要点（兼容迁移）**：
  - 登录校验时：
    - 若旧哈希校验成功 → **立刻升级写入新哈希**（渐进迁移）
  - 新密码仅写入现代哈希（bcrypt/argon2）
- **回归验证**：
  - 老用户可登录并完成一次自动升级；新用户/改密全链路正常

#### 6) 序列化入库改 JSON，降低反序列化链风险

- **涉及文件**：
  - `includes/lib/Cache.php`：从 DB 读取并 `unserialize`
  - `includes/lib/Payment.php`：`ext` 字段 `serialize/unserialize`（`lockPayData`）
- **修复要点**：
  - `serialize/unserialize` → `json_encode/json_decode(true)`
  - 若短期不能改：
    - 对内容加 **HMAC 签名**（防篡改）
    - 移除 `@unserialize` 静默失败

#### 7) Cookie 安全属性补齐（后台/商户会话）

- **涉及文件**：
  - `admin/login.php`（`admin_token`）
  - `includes/member.php`（`user_token`）
  - `includes/lib/Payment.php`（`mysid`）
- **修复要点**：
  - 在全站 HTTPS 下统一设置：
    - `Secure=true`
    - `HttpOnly=true`
    - `SameSite=Lax`（如需更严格可用 `Strict`，但注意兼容）
  - PHP 7.3+ 使用 options 数组设置 cookie，避免参数遗漏

---

### P2（工程加固/长期治理）

#### 8) 只在受控反代场景信任 `X-Forwarded-For/X-Real-IP/CF-Connecting-IP`

- **涉及文件**：`includes/functions.php`：`real_ip()`
- **修复要点**：
  - 仅当 `REMOTE_ADDR` 在 `trusted_proxies` 白名单时才解析转发头
  - 否则只使用 `REMOTE_ADDR`

#### 9) 上传接口增强与目录不可执行

- **涉及文件**：
  - `admin/ajax.php`（`article_upload`）
  - `admin/set.php`（logo 上传）
- **修复要点**：
  - 限制大小/频率
  - 校验真实 MIME/图片头（`getimagesize()`/finfo）
  - WebServer 配置：`assets/`（尤其上传目录）**禁止解析脚本**

---

### 最小回归验证清单（建议每次补丁都跑）

- **支付链路**：
  - 下单 → 支付 → 异步回调 → 订单入账
  - 退款/关闭订单（如果你的业务启用）
- **后台功能**：
  - 列表筛选、统计、导出 CSV
- **安全验证**：
  - TLS 开启后第三方仍可正常访问
  - PayPal webhook 伪造 `PAYPAL-CERT-URL`/伪造签名应被拒绝
  - `install/` 在生产环境无法被公网访问

---

### 建议实施顺序（最省事的落地路径）

- **第一阶段（P0）**：TLS 校验 → PayPal webhook 修复 → 禁用 `install/` Web 访问
- **第二阶段（P1）**：SQL 注入面收敛 → 密码哈希迁移 → 反序列化收口 → Cookie 加固
- **第三阶段（P2）**：IP 可信代理白名单 → 上传加固与权限隔离

