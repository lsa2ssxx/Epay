# TokenPay 与 BEPusdt 加密货币支持深度分析

## 📋 目录

1. [概述](#概述)
2. [TokenPay 支持的加密货币](#tokenpay-支持的加密货币)
3. [BEPusdt 支持的加密货币](#bepusdt-支持的加密货币)
4. [配置方法详解](#配置方法详解)
5. [对比分析](#对比分析)
6. [使用建议](#使用建议)

---

## 概述

本文档深入分析 TokenPay 和 BEPusdt 两个支付插件支持的加密货币类型、配置方法以及它们的技术实现差异。

### 基本信息

| 项目 | TokenPay | BEPusdt |
|------|----------|---------|
| **插件名称** | TokenPay | BEpusdt USDT/USDC 个人收款 |
| **作者** | TokenPay | V03413 |
| **官方链接** | https://github.com/LightCountry/TokenPay | https://github.com/v03413/BEpusdt |
| **支付方式命名格式** | 大写+下划线（如 `USDT_TRC20`） | 小写+点号（如 `usdt.trc20`） |
| **API接口** | `/CreateOrder` | `/api/v1/order/create-transaction` |

---

## TokenPay 支持的加密货币

### 完整列表（共11种）

TokenPay 支持以下加密货币类型：

#### 1. TRON 网络（2种）
- **TRX** - Tron 原生代币
- **USDT_TRC20** - Tether USD (TRC20标准)

#### 2. Ethereum 网络（3种）
- **EVM_ETH_ETH** - Ethereum 原生代币
- **EVM_ETH_USDT_ERC20** - Tether USD (ERC20标准)
- **EVM_ETH_USDC_ERC20** - USD Coin (ERC20标准)

#### 3. Binance Smart Chain 网络（3种）
- **EVM_BSC_BNB** - Binance Coin (BEP20标准)
- **EVM_BSC_USDT_BEP20** - Tether USD (BEP20标准)
- **EVM_BSC_USDC_BEP20** - USD Coin (BEP20标准)

#### 4. Polygon 网络（3种）
- **EVM_Polygon_POL** - Polygon 原生代币（原MATIC）
- **EVM_Polygon_USDT_ERC20** - Tether USD (Polygon网络)
- **EVM_Polygon_USDC_ERC20** - USD Coin (Polygon网络)

### TokenPay 支付方式命名规则

TokenPay 使用以下命名格式：
- **格式**：`网络_币种_标准` 或 `币种_标准`
- **示例**：
  - `TRX` - 原生代币，无标准后缀
  - `USDT_TRC20` - 币种_标准
  - `EVM_ETH_ETH` - EVM链_网络_原生币
  - `EVM_ETH_USDT_ERC20` - EVM链_网络_币种_标准
  - `EVM_BSC_USDT_BEP20` - EVM链_网络_币种_标准

### TokenPay 技术特点

1. **订单创建方式**：
   - 使用 `Currency` 字段直接传递支付方式名称（原样字符串）
   - 支持自定义 `OutOrderId`、`OrderUserKey`、`ActualAmount` 等参数

2. **签名算法**：
   - 使用 MD5 签名
   - 参数按 key 排序后拼接，最后追加 API密钥
   - 格式：`key1=value1&key2=value2&...&appkey`

3. **回调处理**：
   - 接收 JSON 格式的回调数据
   - 验证 `Signature` 字段
   - 订单号字段：`OutOrderId`

---

## BEPusdt 支持的加密货币

### 完整列表（共19种）

BEPusdt 支持以下加密货币类型：

#### 1. TRON 网络（3种）
- **tron.trx** - Tron 原生代币
- **usdt.trc20** - Tether USD (TRC20标准)
- **usdc.trc20** - USD Coin (TRC20标准)

#### 2. Ethereum 网络（2种）
- **usdt.erc20** - Tether USD (ERC20标准)
- **usdc.erc20** - USD Coin (ERC20标准)

#### 3. Binance Smart Chain 网络（2种）
- **usdt.bep20** - Tether USD (BEP20标准)
- **usdc.bep20** - USD Coin (BEP20标准)

#### 4. Polygon 网络（2种）
- **usdt.polygon** - Tether USD (Polygon网络)
- **usdc.polygon** - USD Coin (Polygon网络)

#### 5. Arbitrum 网络（2种）
- **usdt.arbitrum** - Tether USD (Arbitrum网络)
- **usdc.arbitrum** - USD Coin (Arbitrum网络)

#### 6. X Layer 网络（2种）
- **usdt.xlayer** - Tether USD (X Layer网络)
- **usdc.xlayer** - USD Coin (X Layer网络)

#### 7. Base 网络（1种）
- **usdc.base** - USD Coin (Base网络)

#### 8. Solana 网络（2种）
- **usdt.solana** - Tether USD (Solana网络)
- **usdc.solana** - USD Coin (Solana网络)

#### 9. Aptos 网络（2种）
- **usdt.aptos** - Tether USD (Aptos网络)
- **usdc.aptos** - USD Coin (Aptos网络)

### BEPusdt 支付方式命名规则

BEPusdt 使用以下命名格式：
- **格式**：`币种.网络` 或 `网络.币种`
- **规则**：
  - 全部小写
  - 使用点号（`.`）分隔
  - 网络名称使用小写（如 `trc20`、`erc20`、`bep20`、`polygon`、`arbitrum` 等）
- **示例**：
  - `tron.trx` - 网络.币种
  - `usdt.trc20` - 币种.网络
  - `usdc.base` - 币种.网络

### BEPusdt 技术特点

1. **订单创建方式**：
   - 使用 `trade_type` 字段传递支付方式
   - 自动将 Epay 的支付方式名称转换为 BEPusdt 格式（`_normalizeTradeType` 方法）
   - 转换规则：转小写，将 `-` 和 `_` 替换为 `.`

2. **签名算法**：
   - 使用 MD5 签名
   - **重要**：空值不参与签名（包括 null、空字符串、0）
   - 参数按 key 排序后拼接，最后追加认证Token
   - 格式：`key1=value1&key2=value2&...&token`

3. **回调处理**：
   - 接收 JSON 格式的回调数据
   - 验证 `signature` 字段（小写）
   - 订单号字段：`order_id`
   - 交易ID字段：`trade_id`
   - 支付状态：`status === 2` 表示支付成功

4. **额外功能**：
   - 支持自定义收款地址（`address` 参数）
   - 支持订单超时设置（`timeout` 参数，单位：秒）
   - 支持订单汇率设置（`rate` 参数）

---

## 配置方法详解

### TokenPay 配置方法

#### 1. 数据库准备

**必须执行**：扩展插件 `types` 字段长度
```sql
-- 根据表前缀选择对应的SQL
ALTER TABLE `pre_plugin` MODIFY COLUMN `types` TEXT;
-- 或
ALTER TABLE `pay_plugin` MODIFY COLUMN `types` TEXT;
```

**可选执行**：批量添加支付方式
```sql
-- 添加所有TokenPay支持的币种
INSERT INTO `pre_type` (`name`, `device`, `showname`, `status`) VALUES 
('TRX', 0, 'TRX', 1),
('USDT_TRC20', 0, 'USDT-TRC20', 1),
('EVM_ETH_ETH', 0, 'ETH', 1),
('EVM_ETH_USDT_ERC20', 0, 'USDT-ERC20', 1),
('EVM_ETH_USDC_ERC20', 0, 'USDC-ERC20', 1),
('EVM_BSC_BNB', 0, 'BNB', 1),
('EVM_BSC_USDT_BEP20', 0, 'USDT-BEP20', 1),
('EVM_BSC_USDC_BEP20', 0, 'USDC-BEP20', 1),
('EVM_Polygon_POL', 0, 'POL', 1),
('EVM_Polygon_USDT_ERC20', 0, 'USDT-Polygon', 1),
('EVM_Polygon_USDC_ERC20', 0, 'USDC-Polygon', 1);
```

#### 2. 后台配置

在 Epay 后台 **支付接口** → **支付通道** 中添加通道：

| 配置项 | 说明 | 示例 |
|--------|------|------|
| **API接口地址** | TokenPay 服务地址 | `https://token-pay.xxx.com` |
| **方式** | 选择对应的币种 | `TRX`、`USDT_TRC20` 等 |
| **APP ID** | 任意字符 | `myapp` |
| **API秘钥** | TokenPay API 密钥 | `your-api-key-here` |

**注意事项**：
- API接口地址末尾**不要**带斜线（`/`）
- 每个币种需要单独创建一个支付通道
- APP ID 可以是任意字符，主要用于标识

#### 3. 代码实现

TokenPay 创建订单的关键代码：
```php
$param = [
    'OutOrderId' => TRADE_NO,
    'OrderUserKey' => (string)$order['uid'],
    'ActualAmount' => $order['realmoney'],
    'Currency' => $order['typename'],  // 直接使用支付方式名称
    'NotifyUrl' => $conf['localurl'].'pay/notify/'.TRADE_NO.'/',
    'RedirectUrl' => $siteurl.'pay/return/'.TRADE_NO.'/'
];
$param['Signature'] = self::Sign($param, $channel['appkey']);
```

---

### BEPusdt 配置方法

#### 1. 数据库准备

**必须执行**：扩展插件 `types` 字段长度（如果 TokenPay 已执行过，可跳过）
```sql
ALTER TABLE `pre_plugin` MODIFY COLUMN `types` TEXT;
-- 或
ALTER TABLE `pay_plugin` MODIFY COLUMN `types` TEXT;
```

**可选执行**：批量添加支付方式
```sql
-- 添加所有BEPusdt支持的币种
INSERT INTO `pre_type` (`name`, `device`, `showname`, `status`) VALUES 
('tron.trx', 0, 'TRX (BEPusdt)', 1),
('usdt.trc20', 0, 'USDT-TRC20 (BEPusdt)', 1),
('usdc.trc20', 0, 'USDC-TRC20 (BEPusdt)', 1),
('usdt.polygon', 0, 'USDT-Polygon (BEPusdt)', 1),
('usdc.polygon', 0, 'USDC-Polygon (BEPusdt)', 1),
('usdt.arbitrum', 0, 'USDT-Arbitrum (BEPusdt)', 1),
('usdc.arbitrum', 0, 'USDC-Arbitrum (BEPusdt)', 1),
('usdt.erc20', 0, 'USDT-ERC20 (BEPusdt)', 1),
('usdc.erc20', 0, 'USDC-ERC20 (BEPusdt)', 1),
('usdt.bep20', 0, 'USDT-BEP20 (BEPusdt)', 1),
('usdc.bep20', 0, 'USDC-BEP20 (BEPusdt)', 1),
('usdt.xlayer', 0, 'USDT-X Layer (BEPusdt)', 1),
('usdc.xlayer', 0, 'USDC-X Layer (BEPusdt)', 1),
('usdc.base', 0, 'USDC-Base (BEPusdt)', 1),
('usdt.solana', 0, 'USDT-Solana (BEPusdt)', 1),
('usdc.solana', 0, 'USDC-Solana (BEPusdt)', 1),
('usdt.aptos', 0, 'USDT-Aptos (BEPusdt)', 1),
('usdc.aptos', 0, 'USDC-Aptos (BEPusdt)', 1);
```

#### 2. 后台配置

在 Epay 后台 **支付接口** → **支付通道** 中添加通道：

| 配置项 | 说明 | 示例 | 是否必填 |
|--------|------|------|---------|
| **接口地址** | BEpusdt 网关地址 | `https://bepusdt.xxx.com/` | ✅ 必填 |
| **方式** | 选择对应的币种 | `usdt.trc20`、`tron.trx` 等 | ✅ 必填 |
| **认证Token** | BEpusdt API Token | `your-token-here` | ✅ 必填 |
| **收款地址** | 指定收款地址 | `Txxxxxxxxxxxxx` | ⚠️ 可选 |
| **订单超时** | 订单超时时间（秒） | `1200` | ⚠️ 可选 |
| **订单汇率** | 汇率设置 | `7.4` 或 `~1.02` | ⚠️ 可选 |

**注意事项**：
- 接口地址**必须**以 `/` 结尾
- 认证Token 在 BEpusdt 后台获取：**系统管理 → 基本设置 → API设置 → 对接令牌**
- 收款地址留空则由 BEpusdt 自动分配
- 订单超时推荐设置为 1200 秒（20分钟）
- 订单汇率格式：固定值（如 `7.4`）或相对值（如 `~1.02` 表示上浮2%）

#### 3. 代码实现

BEPusdt 创建订单的关键代码：
```php
$parameter = [
    'order_id' => TRADE_NO,
    'amount' => floatval($order['realmoney']),
    'notify_url' => $conf['localurl'] . 'pay/notify/' . TRADE_NO . '/',
    'redirect_url' => $siteurl . 'pay/return/' . TRADE_NO . '/',
];

// 自动转换支付方式名称
$trade_type = self::_normalizeTradeType($order['typename']);
if ($trade_type) {
    $parameter['trade_type'] = $trade_type;
}

// 可选参数
if ($address !== '') $parameter['address'] = $address;
if ($timeout > 0) $parameter['timeout'] = $timeout;
if ($rate !== '') $parameter['rate'] = $rate;

$parameter['signature'] = self::_toSign($parameter, $channel['appkey']);
```

**名称转换逻辑**：
```php
private static function _normalizeTradeType(string $typename): string
{
    $t = strtolower(trim($typename));
    $t = str_replace(['-', '_'], '.', $t);
    return $t;
}
```

---

## 对比分析

### 支持的加密货币对比

| 网络/币种 | TokenPay | BEPusdt | 说明 |
|-----------|----------|---------|------|
| **TRON 网络** |
| TRX | ✅ `TRX` | ✅ `tron.trx` | 原生代币 |
| USDT-TRC20 | ✅ `USDT_TRC20` | ✅ `usdt.trc20` | 稳定币 |
| USDC-TRC20 | ❌ | ✅ `usdc.trc20` | TokenPay不支持 |
| **Ethereum 网络** |
| ETH | ✅ `EVM_ETH_ETH` | ❌ | BEPusdt不支持原生ETH |
| USDT-ERC20 | ✅ `EVM_ETH_USDT_ERC20` | ✅ `usdt.erc20` | 稳定币 |
| USDC-ERC20 | ✅ `EVM_ETH_USDC_ERC20` | ✅ `usdc.erc20` | 稳定币 |
| **BSC 网络** |
| BNB | ✅ `EVM_BSC_BNB` | ❌ | BEPusdt不支持原生BNB |
| USDT-BEP20 | ✅ `EVM_BSC_USDT_BEP20` | ✅ `usdt.bep20` | 稳定币 |
| USDC-BEP20 | ✅ `EVM_BSC_USDC_BEP20` | ✅ `usdc.bep20` | 稳定币 |
| **Polygon 网络** |
| POL/MATIC | ✅ `EVM_Polygon_POL` | ❌ | BEPusdt不支持原生POL |
| USDT-Polygon | ✅ `EVM_Polygon_USDT_ERC20` | ✅ `usdt.polygon` | 稳定币 |
| USDC-Polygon | ✅ `EVM_Polygon_USDC_ERC20` | ✅ `usdc.polygon` | 稳定币 |
| **Arbitrum 网络** |
| USDT-Arbitrum | ❌ | ✅ `usdt.arbitrum` | TokenPay不支持 |
| USDC-Arbitrum | ❌ | ✅ `usdc.arbitrum` | TokenPay不支持 |
| **X Layer 网络** |
| USDT-X Layer | ❌ | ✅ `usdt.xlayer` | TokenPay不支持 |
| USDC-X Layer | ❌ | ✅ `usdc.xlayer` | TokenPay不支持 |
| **Base 网络** |
| USDC-Base | ❌ | ✅ `usdc.base` | TokenPay不支持 |
| **Solana 网络** |
| USDT-Solana | ❌ | ✅ `usdt.solana` | TokenPay不支持 |
| USDC-Solana | ❌ | ✅ `usdc.solana` | TokenPay不支持 |
| **Aptos 网络** |
| USDT-Aptos | ❌ | ✅ `usdt.aptos` | TokenPay不支持 |
| USDC-Aptos | ❌ | ✅ `usdc.aptos` | TokenPay不支持 |

### 功能对比

| 功能特性 | TokenPay | BEPusdt |
|---------|----------|---------|
| **支持的币种总数** | 11种 | 19种 |
| **支持原生币种** | ✅ ETH、BNB、POL、TRX | ✅ TRX |
| **支持稳定币** | ✅ USDT、USDC | ✅ USDT、USDC |
| **支持的网络** | TRON、Ethereum、BSC、Polygon | TRON、Ethereum、BSC、Polygon、Arbitrum、X Layer、Base、Solana、Aptos |
| **自定义收款地址** | ❌ | ✅ |
| **订单超时设置** | ❌ | ✅ |
| **订单汇率设置** | ❌ | ✅ |
| **名称自动转换** | ❌ | ✅ |
| **签名空值处理** | 所有参数参与签名 | 空值不参与签名 |

### 技术实现对比

| 技术点 | TokenPay | BEPusdt |
|--------|----------|---------|
| **API端点** | `/CreateOrder` | `/api/v1/order/create-transaction` |
| **请求方法** | POST | POST |
| **数据格式** | JSON | JSON |
| **签名算法** | MD5 | MD5 |
| **签名规则** | 所有参数参与签名 | 空值不参与签名 |
| **订单号字段** | `OutOrderId` | `order_id` |
| **金额字段** | `ActualAmount` | `amount` |
| **币种字段** | `Currency` | `trade_type` |
| **回调签名字段** | `Signature` | `signature` |
| **支付状态字段** | - | `status` (2=成功) |

---

## 使用建议

### 选择建议

#### 选择 TokenPay 的场景：
1. ✅ 需要支持原生币种（ETH、BNB、POL）
2. ✅ 主要使用主流网络（TRON、Ethereum、BSC、Polygon）
3. ✅ 需要统一的命名格式（大写+下划线）
4. ✅ 支付方式名称需要精确匹配

#### 选择 BEPusdt 的场景：
1. ✅ 需要支持更多网络（Arbitrum、X Layer、Base、Solana、Aptos）
2. ✅ 需要支持 USDC-TRC20
3. ✅ 需要自定义收款地址
4. ✅ 需要订单超时和汇率控制
5. ✅ 需要支付方式名称自动转换（更灵活）

### 同时使用两个插件

**优势**：
- 覆盖更多币种和网络
- 提供更多支付选择
- 互为备份，提高可用性

**注意事项**：
1. **支付方式名称不同**：需要在后台分别创建对应的支付方式
2. **配置独立**：每个插件需要独立的支付通道配置
3. **命名规范**：建议在显示名称中标注插件来源，如：
   - `USDT-TRC20 (TokenPay)`
   - `USDT-TRC20 (BEPusdt)`

### 配置最佳实践

1. **数据库准备**：
   - 执行字段扩展SQL（只需执行一次）
   - 根据需求选择批量添加支付方式或手动添加

2. **支付通道配置**：
   - TokenPay：每个币种单独配置，API地址不要带斜线
   - BEPusdt：每个币种单独配置，API地址必须带斜线

3. **测试验证**：
   - 创建测试订单
   - 验证回调是否正常
   - 确认订单状态更新正确

4. **监控和维护**：
   - 定期检查订单状态
   - 监控回调成功率
   - 关注插件更新和新增币种支持

---

## 总结

### TokenPay 特点
- ✅ 支持原生币种（ETH、BNB、POL、TRX）
- ✅ 命名格式统一（大写+下划线）
- ✅ 配置简单，只需3个参数
- ❌ 支持的币种和网络较少（11种）
- ❌ 不支持高级功能（自定义地址、超时、汇率）

### BEPusdt 特点
- ✅ 支持更多币种和网络（19种）
- ✅ 支持更多Layer2网络（Arbitrum、Base、X Layer等）
- ✅ 支持非EVM链（Solana、Aptos）
- ✅ 支持高级功能（自定义地址、超时、汇率）
- ✅ 支付方式名称自动转换
- ❌ 不支持原生币种（ETH、BNB、POL）
- ❌ 配置参数较多

### 推荐方案

**方案一：单一插件**
- 如果只需要主流币种和网络 → 选择 **TokenPay**
- 如果需要更多网络和高级功能 → 选择 **BEPusdt**

**方案二：双插件共存**
- TokenPay 用于原生币种支付（ETH、BNB、POL）
- BEPusdt 用于稳定币支付和更多网络
- 两者互补，提供最全面的支付支持

---

**文档版本**：v1.0  
**最后更新**：2026-02-09  
**维护者**：根据代码分析生成
