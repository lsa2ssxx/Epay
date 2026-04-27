-- Issue #10 评论：支付通道前台展示名（收银台左侧主文案优先用此字段）
ALTER TABLE `pre_channel`
ADD COLUMN `front_showname` varchar(64) DEFAULT NULL COMMENT '收银台/前台展示名称，空则沿用支付方式 showname' AFTER `name`;
