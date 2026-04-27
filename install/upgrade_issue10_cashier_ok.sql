-- Issue #10: 支付通道「收银台是否可下单」字段（已部署库请手动执行一次）
ALTER TABLE `pre_channel`
  ADD COLUMN `cashier_ok` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1=收银台可下单 0=收银台仅展示不可用' AFTER `timestop`;
