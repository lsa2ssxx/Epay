-- Issue #10: 支付通道收银台可下单标记（随 install/update.php 执行，表前缀 pre_ 会被替换为 config 中的 dbqz）
ALTER TABLE `pre_channel`
ADD COLUMN `cashier_ok` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1=收银台可下单 0=收银台仅展示不可用' AFTER `timestop`;
