-- TokenPay 插件：扩展支付插件 types 字段长度（支持多币种）
-- 适配表前缀为 pay_ 的数据库
ALTER TABLE `pay_plugin`
MODIFY COLUMN `types` varchar(4096) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL AFTER `link`;
