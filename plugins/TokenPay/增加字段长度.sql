-- TokenPay 插件：扩展支付插件 types 字段长度（支持多币种）
-- 若你的 Epay 表前缀不是 pre_，请把 pre_plugin 改成你的前缀+plugin
ALTER TABLE `pre_plugin`
MODIFY COLUMN `types` varchar(4096) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL AFTER `link`;
