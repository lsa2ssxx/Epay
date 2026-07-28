# BEpusdt 插件

本插件按 BEpusdt v1.24.1 的订单创建与回调协议实现，不直接覆盖或复制上游官方插件。

## 配置

- 接口地址必须使用 HTTPS，且不能包含用户名、密码、查询参数或片段。
- 认证 Token 必填。
- 订单超时留空时使用网关默认值；填写时必须是大于等于 120 的整数秒数。
- `unified_cashier=1` 时在 Epay 展示付款信息，否则跳转至 BEpusdt 返回的支付页。

后台新增、复制、编辑、保存配置、启用和批量导入通道时，都会校验插件与支付方式的匹配关系。新通道仍默认关闭，完成配置并验证后再手动启用。

## 回调语义

- `status=1`：等待支付，仅返回 `HTTP 200` 和 `ok`，不修改 Epay 订单状态。
- `status=2`：支付成功，使用原子状态门闩结算；`trade_id` 写入 `api_trade_no`，`block_transaction_id` 写入 `bill_trade_no`。
- `status=3`：网关订单超时，仅合并记录扩展信息，不结算。
- 非法 JSON、缺少字段、签名错误或订单号不匹配会返回非 2xx 响应。

## 测试

```bash
composer install
composer test
```

MariaDB 集成测试需要设置：

```bash
EPAY_TEST_DSN='mysql:host=127.0.0.1;port=3306;dbname=epay_test;charset=utf8mb4' \
EPAY_TEST_DB_USER=root \
EPAY_TEST_DB_PASSWORD=root \
composer test
```

GitHub Actions 会在 PHP 7.4 和 PHP 8.3 上启动 MariaDB 10.11 并运行完整测试。
