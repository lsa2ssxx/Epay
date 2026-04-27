-- Issue #10: 支付通道「收银台是否可下单」字段（已并入 install/update.php / update4.sql）
-- 推荐：浏览器访问 install/update.php 自动升级（无需密钥）
-- 手动：将下面 pre_ 换成实际表前缀后执行
ALTER TABLE `pre_channel`
  ADD COLUMN `cashier_ok` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1=收银台可下单 0=收银台仅展示不可用' AFTER `timestop`;
