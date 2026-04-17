-- TokenPay 插件：一次性添加所有币种支付方式（可选）
-- 若你的 Epay 表前缀不是 pre_，请把 pre_type 改成你的前缀+type
INSERT INTO `pre_type` ( `name`, `device`, `showname`, `status`) VALUES ('TRX', 0, 'TRX', 1);
INSERT INTO `pre_type` ( `name`, `device`, `showname`, `status`) VALUES ('USDT_TRC20', 0, 'USDT-TRC20', 1);

INSERT INTO `pre_type` ( `name`, `device`, `showname`, `status`) VALUES ('EVM_ETH_ETH', 0, 'ETH', 1);
INSERT INTO `pre_type` ( `name`, `device`, `showname`, `status`) VALUES ('EVM_ETH_USDT_ERC20', 0, 'USDT-ERC20', 1);
INSERT INTO `pre_type` ( `name`, `device`, `showname`, `status`) VALUES ('EVM_ETH_USDC_ERC20', 0, 'USDC-ERC20', 1);

INSERT INTO `pre_type` ( `name`, `device`, `showname`, `status`) VALUES ('EVM_BSC_BNB', 0, 'BNB', 1);
INSERT INTO `pre_type` ( `name`, `device`, `showname`, `status`) VALUES ('EVM_BSC_USDT_BEP20', 0, 'USDT-BEP20', 1);
INSERT INTO `pre_type` ( `name`, `device`, `showname`, `status`) VALUES ('EVM_BSC_USDC_BEP20', 0, 'USDC-BEP20', 1);

INSERT INTO `pre_type` ( `name`, `device`, `showname`, `status`) VALUES ('EVM_Polygon_POL', 0, 'POL', 1);
INSERT INTO `pre_type` ( `name`, `device`, `showname`, `status`) VALUES ('EVM_Polygon_USDT_ERC20', 0, 'USDT-Polygon', 1);
INSERT INTO `pre_type` ( `name`, `device`, `showname`, `status`) VALUES ('EVM_Polygon_USDC_ERC20', 0, 'USDC-Polygon', 1);
