<?php
include("../includes/common.php");
if($islogin==1){}else exit("<script language='javascript'>window.location.href='./login.php';</script>");
$act=isset($_GET['act'])?daddslashes($_GET['act']):null;

if(!checkRefererHost())exit('{"code":403}');

@header('Content-Type: application/json; charset=UTF-8');

switch($act){
case 'channelList':
	$sql=" 1=1";
	if(isset($_POST['id']) && !empty($_POST['id'])) {
		$id = intval($_POST['id']);
		$sql.=" AND A.`id`='$id'";
	}
	if(isset($_POST['type']) && !empty($_POST['type'])) {
		$type = intval($_POST['type']);
		$sql.=" AND A.`type`='$type'";
	}
	if(isset($_POST['plugin']) && !empty($_POST['plugin'])) {
		$plugin = trim($_POST['plugin']);
		$sql.=" AND A.`plugin`='$plugin'";
	}
	if(isset($_POST['dstatus']) && $_POST['dstatus']>-1) {
		$dstatus = intval($_POST['dstatus']);
		$sql.=" AND A.`status`={$dstatus}";
	}
	if(isset($_POST['cash_filter']) && $_POST['cash_filter'] !== '' && $_POST['cash_filter'] > -1) {
		$cf = intval($_POST['cash_filter']);
		$cf = $cf === 0 ? 0 : 1;
		$sql.=" AND A.`cashier_ok`={$cf}";
	}
	if(isset($_POST['kw']) && !empty($_POST['kw'])) {
		$kw = trim(daddslashes($_POST['kw']));
		$sql.=" AND (A.`id`='{$kw}' OR A.`name` like '%{$kw}%')";
	}
	$list = $DB->getAll("SELECT A.*,B.name typename,B.showname typeshowname FROM pre_channel A LEFT JOIN pre_type B ON A.type=B.id WHERE{$sql} ORDER BY id DESC");
	exit(json_encode(is_array($list) ? $list : []));
break;

case 'getPayType':
	$id=intval($_GET['id']);
	$row=$DB->getRow("select * from pre_type where id='$id' limit 1");
	if(!$row)
		exit('{"code":-1,"msg":"当前支付方式不存在！"}');
	$result = ['code'=>0,'msg'=>'succ','data'=>$row];
	exit(json_encode($result));
break;
case 'setPayType':
	$id=intval($_GET['id']);
	$status=intval($_GET['status']);
	$row=$DB->getRow("select * from pre_type where id='$id' limit 1");
	if(!$row)
		exit('{"code":-1,"msg":"当前支付方式不存在！"}');
	$sql = "UPDATE pre_type SET status='$status' WHERE id='$id'";
	if($DB->exec($sql))exit('{"code":0,"msg":"修改支付方式成功！"}');
	else exit('{"code":-1,"msg":"修改支付方式失败['.$DB->error().']"}');
break;
case 'delPayType':
	$id=intval($_GET['id']);
	$row=$DB->getRow("select * from pre_type where id='$id' limit 1");
	if(!$row)
		exit('{"code":-1,"msg":"当前支付方式不存在！"}');
	$row=$DB->getRow("select * from pre_channel where type='$id' limit 1");
	if($row)
		exit('{"code":-1,"msg":"删除失败，存在使用该支付方式的支付通道"}');
	$sql = "DELETE FROM pre_type WHERE id='$id'";
	if($DB->exec($sql))exit('{"code":0,"msg":"删除支付方式成功！"}');
	else exit('{"code":-1,"msg":"删除支付方式失败['.$DB->error().']"}');
break;
case 'savePayType':
	$currency = isset($_POST['currency']) ? trim((string)$_POST['currency']) : '';
	$network = isset($_POST['network']) ? trim((string)$_POST['network']) : '';
	$currency_sort = isset($_POST['currency_sort']) ? max(0, intval($_POST['currency_sort'])) : 0;
	$network_sort = isset($_POST['network_sort']) ? max(0, intval($_POST['network_sort'])) : 0;
	if(strlen($currency) > 30 || strlen($network) > 30){
		exit('{"code":-1,"msg":"币种/网络字段长度不能超过 30"}');
	}
	if($currency !== '' && !preg_match('/^[A-Za-z0-9_\-]+$/', $currency)){
		exit('{"code":-1,"msg":"币种字段仅允许字母数字下划线和连字符"}');
	}
	if($network !== '' && !preg_match('/^[A-Za-z0-9_\-]+$/', $network)){
		exit('{"code":-1,"msg":"网络字段仅允许字母数字下划线和连字符"}');
	}
	if($_POST['action'] == 'add'){
		$name=trim($_POST['name']);
		$showname=trim($_POST['showname']);
		$device=intval($_POST['device']);
		if(!preg_match('/^[a-zA-Z0-9_.]+$/',$name)){
			exit('{"code":-1,"msg":"调用值不符合规则"}');
		}
		$row=$DB->getRow("select * from pre_type where name='$name' and device='$device' limit 1");
		if($row)
			exit('{"code":-1,"msg":"同一个调用值+支持设备不能重复"}');
		$data = [
			'name'=>$name, 'showname'=>$showname, 'device'=>$device, 'status'=>1,
			'currency'=>$currency, 'network'=>$network,
			'currency_sort'=>$currency_sort, 'network_sort'=>$network_sort,
		];
		if($DB->insert('type', $data))exit('{"code":0,"msg":"新增支付方式成功！"}');
		else exit('{"code":-1,"msg":"新增支付方式失败['.$DB->error().']"}');
	}else{
		$id=intval($_POST['id']);
		$name=trim($_POST['name']);
		$showname=trim($_POST['showname']);
		$device=intval($_POST['device']);
		if(!preg_match('/^[a-zA-Z0-9_.]+$/',$name)){
			exit('{"code":-1,"msg":"调用值不符合规则"}');
		}
		$row=$DB->getRow("select * from pre_type where name='$name' and device='$device' and id<>$id limit 1");
		if($row)
			exit('{"code":-1,"msg":"同一个调用值+支持设备不能重复"}');
		$data = [
			'name'=>$name, 'showname'=>$showname, 'device'=>$device,
			'currency'=>$currency, 'network'=>$network,
			'currency_sort'=>$currency_sort, 'network_sort'=>$network_sort,
		];
		if($DB->update('type', $data, ['id'=>$id])!==false)exit('{"code":0,"msg":"修改支付方式成功！"}');
		else exit('{"code":-1,"msg":"修改支付方式失败['.$DB->error().']"}');
	}
break;
case 'importBepusdtPayTypes':
	$pluginFile = PLUGIN_ROOT.'bepusdt/bepusdt_plugin.php';
	if(!file_exists($pluginFile))
		exit('{"code":-1,"msg":"BEpusdt 插件不存在"}');
	require_once $pluginFile;
	if(!class_exists('bepusdt_plugin', false) || !method_exists('bepusdt_plugin', 'tradeTypeCatalog'))
		exit('{"code":-1,"msg":"BEpusdt 插件不完整"}');
	$rows = bepusdt_plugin::tradeTypeCatalog();
	$device = 0;
	$imported = 0;
	$skipped = 0;
	foreach($rows as $r){
		$name = isset($r['name']) ? trim($r['name']) : '';
		$showname = isset($r['showname']) ? trim($r['showname']) : '';
		if($name === '' || $showname === '')
			continue;
		if(!preg_match('/^[a-zA-Z0-9_.]+$/', $name))
			continue;
		$exist = $DB->getRow('SELECT id FROM pre_type WHERE name=:name AND device=:device LIMIT 1', [':name'=>$name, ':device'=>$device]);
		if($exist){
			$skipped++;
			continue;
		}
		if($DB->insert('type', ['name'=>$name, 'device'=>$device, 'showname'=>$showname, 'status'=>1]))
			$imported++;
	}
	\lib\Plugin::updateAll();
	$total = count($rows);
	exit(json_encode(['code'=>0,'msg'=>'导入完成','imported'=>$imported,'skipped'=>$skipped,'total'=>$total], JSON_UNESCAPED_UNICODE));
break;
case 'importBepusdtChannels':
	// 批量导入 BEpusdt 支付通道（pre_channel）
	// 输入：POST list=JSON字符串（数组），每项包含 name/type/rate/.../config
	$raw = isset($_POST['list']) ? trim($_POST['list']) : '';
	if($raw === '') exit('{"code":-1,"msg":"参数不能为空"}');

	$list = json_decode($raw, true);
	if(!is_array($list)) exit('{"code":-1,"msg":"JSON解析失败或格式不正确（必须是数组）"}');

	$imported = 0;
	$skipped = 0;
	$failed = 0;
	$errors = [];

	$pluginName = 'bepusdt';
	$pluginCfg = \lib\Plugin::getConfig($pluginName);
	if(!$pluginCfg || empty($pluginCfg['inputs'])){
		exit('{"code":-1,"msg":"BEpusdt 插件不存在或未声明 inputs"}');
	}
	$inputKeys = array_keys($pluginCfg['inputs']);

	foreach($list as $idx => $item){
		if(!is_array($item)){
			$failed++;
			if(count($errors) < 10) $errors[] = '第'.($idx+1).'条：不是对象';
			continue;
		}

		$name = isset($item['name']) ? trim((string)$item['name']) : '';
		$typeName = isset($item['type']) ? trim((string)$item['type']) : '';
		if($name === '' || $typeName === ''){
			$failed++;
			if(count($errors) < 10) $errors[] = '第'.($idx+1).'条：name/type 不能为空';
			continue;
		}
		if(mb_strlen($name) > 30){
			$failed++;
			if(count($errors) < 10) $errors[] = '第'.($idx+1).'条：name 长度不能超过30';
			continue;
		}
		if(!preg_match('/^[a-zA-Z0-9_.]+$/', $typeName)){
			$failed++;
			if(count($errors) < 10) $errors[] = '第'.($idx+1).'条：type 格式不合法（仅允许字母数字下划线点）';
			continue;
		}

		// 支付通道名称全局唯一（与 saveChannel 的规则一致）
		$exist = $DB->getRow('SELECT id FROM pre_channel WHERE name=:name LIMIT 1', [':name'=>$name]);
		if($exist){
			$skipped++;
			continue;
		}

		// type -> pre_type.id （默认使用 device=0 的调用值）
		$typeRow = $DB->getRow('SELECT id FROM pre_type WHERE name=:name AND device=0 LIMIT 1', [':name'=>$typeName]);
		if(!$typeRow){
			$failed++;
			if(count($errors) < 10) $errors[] = '第'.($idx+1).'条：支付方式不存在（请先导入/创建 '.$typeName.'）';
			continue;
		}

		$mode = isset($item['mode']) ? intval($item['mode']) : 0;
		$rate = isset($item['rate']) ? trim((string)$item['rate']) : '';
		$costrate = isset($item['costrate']) ? trim((string)$item['costrate']) : '';
		$daytop = isset($item['daytop']) ? intval($item['daytop']) : 0;
		$daymaxorder = isset($item['daymaxorder']) ? intval($item['daymaxorder']) : 0;
		$paymin = isset($item['paymin']) ? trim((string)$item['paymin']) : '';
		$paymax = isset($item['paymax']) ? trim((string)$item['paymax']) : '';
		$timestart = isset($item['timestart']) ? trim((string)$item['timestart']) : '';
		$timestop = isset($item['timestop']) ? trim((string)$item['timestop']) : '';

		if($rate === '') $rate = '100';
		if(!preg_match('/^[0-9.]+$/', $rate)){
			$failed++;
			if(count($errors) < 10) $errors[] = '第'.($idx+1).'条：rate 不合法';
			continue;
		}
		if($costrate !== '' && !preg_match('/^[0-9.]+$/', $costrate)){
			$failed++;
			if(count($errors) < 10) $errors[] = '第'.($idx+1).'条：costrate 不合法';
			continue;
		}
		if($paymin !== '' && !preg_match('/^[0-9.]+$/', $paymin)){
			$failed++;
			if(count($errors) < 10) $errors[] = '第'.($idx+1).'条：paymin 不合法';
			continue;
		}
		if($paymax !== '' && !preg_match('/^[0-9.]+$/', $paymax)){
			$failed++;
			if(count($errors) < 10) $errors[] = '第'.($idx+1).'条：paymax 不合法';
			continue;
		}

		$config = isset($item['config']) && is_array($item['config']) ? $item['config'] : [];
		$cfg = [];
		foreach($inputKeys as $k){
			if(array_key_exists($k, $config)){
				$cfg[$k] = is_array($config[$k]) ? $config[$k] : trim((string)$config[$k]);
			}else{
				$cfg[$k] = '';
			}
		}
		$appurl = trim((string)($cfg['appurl'] ?? ''));
		$appkey = trim((string)($cfg['appkey'] ?? ''));
		if($appurl === '' || $appkey === ''){
			$failed++;
			if(count($errors) < 10) $errors[] = '第'.($idx+1).'条：config.appurl/config.appkey 不能为空';
			continue;
		}
		if(!preg_match('#^https?://#i', $appurl)){
			$failed++;
			if(count($errors) < 10) $errors[] = '第'.($idx+1).'条：config.appurl 必须以 http(s):// 开头';
			continue;
		}
		if(substr($appurl, -1) !== '/'){
			$appurl .= '/';
			$cfg['appurl'] = $appurl;
		}

		$data = [
			'name' => $name,
			'rate' => $rate,
			'costrate' => $costrate,
			'mode' => $mode,
			'type' => intval($typeRow['id']),
			'plugin' => $pluginName,
			'daytop' => $daytop,
			'paymin' => $paymin,
			'paymax' => $paymax,
			'daymaxorder' => $daymaxorder,
			'timestart' => $timestart === '' ? null : intval($timestart),
			'timestop' => $timestop === '' ? null : intval($timestop),
			'config' => json_encode($cfg, JSON_UNESCAPED_UNICODE),
			'status' => 0,
			'cashier_ok' => 1,
		];
		if($DB->insert('channel', $data)){
			$imported++;
		}else{
			$failed++;
			if(count($errors) < 10) $errors[] = '第'.($idx+1).'条：写入数据库失败['.$DB->error().']';
		}
	}
	exit(json_encode(['code'=>0,'msg'=>'导入完成','imported'=>$imported,'skipped'=>$skipped,'failed'=>$failed,'errors'=>$errors], JSON_UNESCAPED_UNICODE));
break;
case 'getPlugin':
	$name = trim($_GET['name']);
	$row=$DB->getRow("SELECT * FROM pre_plugin WHERE name='$name'");
	if($row){
		$result = ['code'=>0,'msg'=>'succ','data'=>$row];
		exit(json_encode($result));
	}
	else exit('{"code":-1,"msg":"当前支付插件不存在！"}');
break;
case 'getPlugins':
	$typeid = intval($_GET['typeid']);
	$type=$DB->getColumn("SELECT name FROM pre_type WHERE id='$typeid'");
	if(!$type)
		exit('{"code":-1,"msg":"当前支付方式不存在！"}');
	$list = [];
	foreach(\lib\Plugin::getList() as $pn){
		if(!$pn) continue;
		$cfg = \lib\Plugin::getConfig($pn);
		if(!$cfg || empty($cfg['name'])) continue;
		$types = $cfg['types'] ?? [];
		if(!is_array($types)){
			$types = explode(',', (string)$types);
		}
		$types = array_unique(array_filter(array_map('trim', $types)));
		if(in_array($type, $types, true)){
			$list[] = [
				'name' => $cfg['name'],
				'showname' => (!empty($cfg['showname'])) ? $cfg['showname'] : $cfg['name'],
			];
		}
	}
	if($list){
		usort($list, function($a, $b){ return strcasecmp($a['name'], $b['name']); });
		$result = ['code'=>0,'msg'=>'succ','data'=>$list];
		exit(json_encode($result, JSON_UNESCAPED_UNICODE));
	}
	else exit('{"code":-1,"msg":"没有找到支持该支付方式的插件（请确认插件目录存在且插件声明了 types）"}');
break;
case 'getChannel':
	$id=intval($_GET['id']);
	$row=$DB->getRow("SELECT * FROM pre_channel WHERE id='$id'");
	if(!$row)
		exit('{"code":-1,"msg":"当前支付通道不存在！"}');
	$result = ['code'=>0,'msg'=>'succ','data'=>$row];
	exit(json_encode($result));
break;
case 'getChannels':
	$typeid = intval($_GET['typeid']);
	$type=$DB->getColumn("SELECT name FROM pre_type WHERE id='$typeid'");
	if(!$type)
		exit('{"code":-1,"msg":"当前支付方式不存在！"}');
	$list=$DB->getAll("SELECT id,name FROM pre_channel WHERE type='$typeid' and status=1 ORDER BY id ASC");
	if($list){
		$result = ['code'=>0,'msg'=>'succ','data'=>$list];
		exit(json_encode($result));
	}
	else exit('{"code":-1,"msg":"没有找到支持该支付方式的通道"}');
break;
case 'getChannelsByPlugin':
	$plugin = $_GET['plugin'];
	if($plugin){
		$list=$DB->getAll("SELECT id,name FROM pre_channel WHERE plugin='$plugin' ORDER BY id ASC");
	}else{
		$list=$DB->getAll("SELECT id,name FROM pre_channel ORDER BY id ASC");
	}
	if($list){
		$result = ['code'=>0,'msg'=>'succ','data'=>$list];
		exit(json_encode($result));
	}
	else exit('{"code":-1,"msg":"没有找到支持该支付插件的通道"}');
break;
case 'getSubChannels':
	$channel = intval($_GET['channel']);
	$uid = intval($_GET['uid']);
	$sql = " channel='$channel'";
	if($uid > 0) $sql .= " AND uid='$uid'";
	$list=$DB->getAll("SELECT id,name,channel,apply_id FROM pre_subchannel WHERE{$sql} ORDER BY id ASC");
	$result = ['code'=>0,'msg'=>'succ','data'=>$list];
	exit(json_encode($result));
break;
case 'setChannel':
	$id=intval($_GET['id']);
	$status=intval($_GET['status']);
	$row=$DB->getRow("SELECT * FROM pre_channel WHERE id='$id'");
	if(!$row)
		exit('{"code":-1,"msg":"当前支付通道不存在！"}');
	if($status==1 && empty($row['config'])){
		exit('{"code":-1,"msg":"请先配置好密钥后再开启"}');
	}
	if($status==1 && $conf['admin_pwd']=='123456'){
		exit('{"code":-1,"msg":"请先修改默认管理员密码后再开启支付通道"}');
	}
	$sql = "UPDATE pre_channel SET status='$status' WHERE id='$id'";
	if($DB->exec($sql))exit('{"code":0,"msg":"修改支付通道成功！"}');
	else exit('{"code":-1,"msg":"修改支付通道失败['.$DB->error().']"}');
break;
case 'delChannel':
	$id=intval($_GET['id']);
	$row=$DB->getRow("SELECT * FROM pre_channel WHERE id='$id'");
	if(!$row)
		exit('{"code":-1,"msg":"当前支付通道不存在！"}');
	if($DB->find('psreceiver', '*', ['channel'=>$id])){
		exit('{"code":-1,"msg":"当前支付通道下有分账规则，需要先删除"}');
	}
	if($DB->find('applychannel', '*', ['channel'=>$id])){
		exit('{"code":-1,"msg":"当前支付通道关联了进件渠道，无法删除"}');
	}
	$sql = "DELETE FROM pre_channel WHERE id='$id'";
	if($DB->exec($sql)){
		$DB->exec("DELETE FROM pre_subchannel WHERE channel='$id'");
		exit('{"code":0,"msg":"删除支付通道成功！"}');
	}
	else exit('{"code":-1,"msg":"删除支付通道失败['.$DB->error().']"}');
break;
case 'saveChannel':
	if($_POST['action'] == 'add'){
		$name=trim($_POST['name']);
		$rate=trim($_POST['rate']);
		$costrate=trim($_POST['costrate']);
		$type=intval($_POST['type']);
		$plugin=trim($_POST['plugin']);
		$daytop=intval($_POST['daytop']);
		$mode=intval($_POST['mode']);
		$paymin=trim($_POST['paymin']);
		$paymax=trim($_POST['paymax']);
		$daymaxorder=intval($_POST['daymaxorder']);
		$timestart=trim($_POST['timestart']);
		$timestop=trim($_POST['timestop']);
		if(empty($rate)) $rate = 100;
		if(!preg_match('/^[0-9.]+$/',$rate)){
			exit('{"code":-1,"msg":"分成比例不符合规则"}');
		}
		if(!empty($costrate) && !preg_match('/^[0-9.]+$/',$costrate)){
			exit('{"code":-1,"msg":"通道成本不符合规则"}');
		}
		if($paymin && !preg_match('/^[0-9.]+$/',$paymin)){
			exit('{"code":-1,"msg":"最小支付金额不符合规则"}');
		}
		if($paymax && !preg_match('/^[0-9.]+$/',$paymax)){
			exit('{"code":-1,"msg":"最大支付金额不符合规则"}');
		}
		$cashier_ok = isset($_POST['cashier_ok']) ? intval($_POST['cashier_ok']) : 1;
		if($cashier_ok !== 0) $cashier_ok = 1;
		$row=$DB->getRow("SELECT * FROM pre_channel WHERE name='$name' LIMIT 1");
		if($row)
			exit('{"code":-1,"msg":"支付通道名称重复"}');
		$data = ['name'=>$name, 'rate'=>$rate, 'costrate'=>$costrate, 'mode'=>$mode, 'type'=>$type, 'plugin'=>$plugin, 'daytop'=>$daytop, 'paymin'=>$paymin, 'paymax'=>$paymax, 'daymaxorder'=>$daymaxorder, 'timestart'=>$timestart, 'timestop'=>$timestop, 'cashier_ok'=>$cashier_ok];
		if($DB->insert('channel', $data))exit('{"code":0,"msg":"新增支付通道成功！"}');
		else exit('{"code":-1,"msg":"新增支付通道失败['.$DB->error().']"}');
	}elseif($_POST['action'] == 'copy'){
		$id=intval($_POST['id']);
		$row=$DB->getRow("SELECT * FROM pre_channel WHERE id='$id'");
		if(!$row) exit('{"code":-1,"msg":"当前支付通道不存在！"}');
		$name=trim($_POST['name']);
		$rate=trim($_POST['rate']);
		$costrate=trim($_POST['costrate']);
		$type=intval($_POST['type']);
		$plugin=trim($_POST['plugin']);
		$daytop=intval($_POST['daytop']);
		$mode=intval($_POST['mode']);
		$paymin=trim($_POST['paymin']);
		$paymax=trim($_POST['paymax']);
		$daymaxorder=intval($_POST['daymaxorder']);
		$timestart=trim($_POST['timestart']);
		$timestop=trim($_POST['timestop']);
		if(!preg_match('/^[0-9.]+$/',$rate)){
			exit('{"code":-1,"msg":"分成比例不符合规则"}');
		}
		if(!empty($costrate) && !preg_match('/^[0-9.]+$/',$costrate)){
			exit('{"code":-1,"msg":"通道成本不符合规则"}');
		}
		if($paymin && !preg_match('/^[0-9.]+$/',$paymin)){
			exit('{"code":-1,"msg":"最小支付金额不符合规则"}');
		}
		if($paymax && !preg_match('/^[0-9.]+$/',$paymax)){
			exit('{"code":-1,"msg":"最大支付金额不符合规则"}');
		}
		$cashier_ok = isset($_POST['cashier_ok']) ? intval($_POST['cashier_ok']) : 1;
		if($cashier_ok !== 0) $cashier_ok = 1;
		$nrow=$DB->getRow("SELECT * FROM pre_channel WHERE name='$name' LIMIT 1");
		if($nrow)
			exit('{"code":-1,"msg":"支付通道名称重复"}');
		$data = ['name'=>$name, 'rate'=>$rate, 'costrate'=>$costrate, 'mode'=>$mode, 'type'=>$type, 'plugin'=>$plugin, 'daytop'=>$daytop, 'paymin'=>$paymin, 'paymax'=>$paymax, 'daymaxorder'=>$daymaxorder, 'config'=>$row['config'], 'apptype'=>$row['apptype'], 'appwxmp'=>$row['appwxmp'], 'appwxa'=>$row['appwxa'], 'timestart'=>$timestart, 'timestop'=>$timestop, 'cashier_ok'=>$cashier_ok];
		if($DB->insert('channel', $data))exit('{"code":0,"msg":"复制支付通道成功！"}');
		else exit('{"code":-1,"msg":"复制支付通道失败['.$DB->error().']"}');
	}elseif($_POST['action'] == 'edit'){
		$id=intval($_POST['id']);
		$row=$DB->getRow("SELECT * FROM pre_channel WHERE id='$id'");
		if(!$row) exit('{"code":-1,"msg":"当前支付通道不存在！"}');
		$name=trim($_POST['name']);
		$rate=trim($_POST['rate']);
		$costrate=trim($_POST['costrate']);
		$type=intval($_POST['type']);
		$plugin=trim($_POST['plugin']);
		$daytop=intval($_POST['daytop']);
		$mode=intval($_POST['mode']);
		$paymin=trim($_POST['paymin']);
		$paymax=trim($_POST['paymax']);
		$daymaxorder=intval($_POST['daymaxorder']);
		$timestart=trim($_POST['timestart']);
		$timestop=trim($_POST['timestop']);
		if(!preg_match('/^[0-9.]+$/',$rate)){
			exit('{"code":-1,"msg":"分成比例不符合规则"}');
		}
		if(!empty($costrate) && !preg_match('/^[0-9.]+$/',$costrate)){
			exit('{"code":-1,"msg":"通道成本不符合规则"}');
		}
		if($paymin && !preg_match('/^[0-9.]+$/',$paymin)){
			exit('{"code":-1,"msg":"最小支付金额不符合规则"}');
		}
		if($paymax && !preg_match('/^[0-9.]+$/',$paymax)){
			exit('{"code":-1,"msg":"最大支付金额不符合规则"}');
		}
		$cashier_ok = isset($_POST['cashier_ok']) ? intval($_POST['cashier_ok']) : 1;
		if($cashier_ok !== 0) $cashier_ok = 1;
		$nrow=$DB->getRow("SELECT * FROM pre_channel WHERE name='$name' AND id<>$id LIMIT 1");
		if($nrow)
			exit('{"code":-1,"msg":"支付通道名称重复"}');
		$data = ['name'=>$name, 'rate'=>$rate, 'costrate'=>$costrate, 'mode'=>$mode, 'type'=>$type, 'plugin'=>$plugin, 'daytop'=>$daytop, 'paymin'=>$paymin, 'paymax'=>$paymax, 'daymaxorder'=>$daymaxorder, 'timestart'=>$timestart, 'timestop'=>$timestop, 'cashier_ok'=>$cashier_ok];
		if($DB->update('channel', $data, ['id'=>$id])!==false){
			if($row['daystatus']==1 && ($daytop==0 || $daytop>$row['daytop'] || $daymaxorder==0)){
				$DB->exec("UPDATE pre_channel SET daystatus=0 WHERE id='$id'");
			}
			exit('{"code":0,"msg":"修改支付通道成功！"}');
		}else exit('{"code":-1,"msg":"修改支付通道失败['.$DB->error().']"}');
	}
break;
case 'channelInfo':
	$id=intval($_GET['id']);
	$row=$DB->getRow("SELECT * FROM pre_channel WHERE id='$id'");
	if(!$row)
		exit('{"code":-1,"msg":"当前支付通道不存在！"}');
	$typename = $DB->getColumn("SELECT name FROM pre_type WHERE id='{$row['type']}'");
	//if($row['mode']>0){
	//	exit('{"code":-1,"msg":"当前通道为商户直清模式，请进入用户列表-编辑-接口密钥进行配置"}');
	//}
	$apptype = explode(',',$row['apptype']);
	$plugin = \lib\Plugin::getConfig($row['plugin']);
	if(!$plugin)
		exit('{"code":-1,"msg":"当前支付插件不存在！"}');

	$data = '<div class="modal-body"><form class="form" id="form-info">';
	$select_list = [];
	if(!empty($plugin['select_'.$typename])){
		$select_list = $plugin['select_'.$typename];
	}
	elseif(!empty($plugin['select'])){
		$select_list = $plugin['select'];
	}
	if(count($select_list) > 0){
		$select = '';
		foreach($select_list as $key=>$input){
			$select .= '<label><input type="checkbox" '.(in_array($key,$apptype)?'checked':null).' name="apptype[]" value="'.$key.'">'.$input.'</label>&nbsp;';
		}
		$data .= '<div class="form-group"><input type="hidden" id="isapptype" name="isapptype" value="1"/><label>请选择可用的接口：</label><div class="checkbox">'.$select.'</div></div>';
	}
	$config = json_decode($row['config'],true);
	foreach($plugin['inputs'] as $key=>$input){
		if($input['type'] == 'textarea'){
			$data .= '<div class="form-group"><label>'.$input['name'].'：</label><br/><textarea name="config['.$key.']" rows="2" class="form-control" placeholder="'.$input['note'].'">'.$config[$key].'</textarea></div>';
		}elseif($input['type'] == 'select'){
			$addOptions = '';
			foreach($input['options'] as $k=>$v){
				$addOptions.='<option value="'.$k.'" '.($config[$key]==$k?'selected':'').'>'.$v.'</option>';
			}
			$data .= '<div class="form-group"><label>'.$input['name'].'：</label><br/><select class="form-control" name="config['.$key.']" default="'.$config[$key].'">'.$addOptions.'</select></div>';
		}elseif($input['type'] == 'checkbox'){
			$checked = $config[$key] ?? [];
			$addOptions = '';
			foreach($input['options'] as $k=>$v){
				$addOptions.='<label><input type="checkbox" '.(in_array($k,$checked)?'checked':null).' name="config['.$key.'][]" value="'.$k.'">'.$v.'</label>&nbsp;';
			}
			$data .= '<div class="form-group"><label>'.$input['name'].'：</label><div class="checkbox">'.$addOptions.'</div></div>';
		}else{
			$data .= '<div class="form-group"><label>'.$input['name'].'：</label><br/><input type="text" name="config['.$key.']" value="'.$config[$key].'" class="form-control" placeholder="'.$input['note'].'"/></div>';
		}
	}
	if($plugin['bindwxmp'] && $row['type']==2){
		$wxmplist = $DB->getAll("SELECT * FROM pre_weixin WHERE type=0 ORDER BY id ASC");
		$addOptions = '<option value="0">不绑定</option>';
		foreach($wxmplist as $wxmp){
			$addOptions.='<option value="'.$wxmp['id'].'" '.($row['appwxmp']==$wxmp['id']?'selected':'').'>'.$wxmp['name'].'（'.$wxmp['appid'].'）'.'</option>';
		}
		$data .= '<div class="form-group"><label>绑定微信公众号：</label><br/><select class="form-control" name="appwxmp" default="'.$row[$key].'">'.$addOptions.'</select></div>';
	}
	if($plugin['bindwxa'] && $row['type']==2){
		$wxalist = $DB->getAll("SELECT * FROM pre_weixin WHERE type=1 ORDER BY id ASC");
		$addOptions = '<option value="0">不绑定</option>';
		foreach($wxalist as $wxa){
			$addOptions.='<option value="'.$wxa['id'].'" '.($row['appwxa']==$wxa['id']?'selected':'').'>'.$wxa['name'].'（'.$wxa['appid'].'）'.'</option>';
		}
		$data .= '<div class="form-group"><label>绑定微信小程序：</label><br/><select class="form-control" name="appwxa" default="'.$row[$key].'">'.$addOptions.'</select></div>';
	}

	$note = str_replace(['[siteurl]','[channel]','[basedir]'],[$siteurl,$id,ROOT],$plugin['note']);

	$data .= '<button type="button" id="save" onclick="saveInfo('.$id.')" class="btn btn-primary btn-block">保存</button></form><br/><font color="green">'.$note.'</font></div>';
	$result=array("code"=>0,"msg"=>"succ","data"=>$data);
	exit(json_encode($result));
break;
case 'saveChannelInfo':
	$id=intval($_GET['id']);
	$config=isset($_POST['config'])?$_POST['config']:null;
	$appwxmp=isset($_POST['appwxmp'])?intval($_POST['appwxmp']):null;
	$appwxa=isset($_POST['appwxa'])?intval($_POST['appwxa']):null;
	if(isset($_POST['isapptype'])){
		if(!isset($_POST['apptype']) || count($_POST['apptype'])<=0)exit('{"code":-1,"msg":"请至少选择一个可用的支付接口"}');
		$apptype=implode(',',$_POST['apptype']);
	}else{
		$apptype=null;
	}
	if(empty($config)) exit('{"code":-1,"msg":"填写的内容不能为空"}');
	$config = json_encode($config);
	$data = ['config'=>$config, 'apptype'=>$apptype, 'appwxmp'=>$appwxmp, 'appwxa'=>$appwxa];
	if($DB->update('channel', $data, ['id'=>$id])!==false)exit('{"code":0,"msg":"修改支付密钥成功！"}');
	else exit('{"code":-1,"msg":"修改支付密钥失败['.$DB->error().']"}');
break;
case 'getRoll':
	$id=intval($_GET['id']);
	$row=$DB->getRow("select * from pre_roll where id='$id' limit 1");
	if(!$row)
		exit('{"code":-1,"msg":"当前轮询组不存在！"}');
	$result = ['code'=>0,'msg'=>'succ','data'=>$row];
	exit(json_encode($result));
break;
case 'setRoll':
	$id=intval($_GET['id']);
	$status=intval($_GET['status']);
	$row=$DB->getRow("select * from pre_roll where id='$id' limit 1");
	if(!$row)
		exit('{"code":-1,"msg":"当前轮询组不存在！"}');
	if($status==1 && empty($row['info'])){
		exit('{"code":-1,"msg":"请先配置好支付通道后再开启"}');
	}
	$sql = "UPDATE pre_roll SET status='$status' WHERE id='$id'";
	if($DB->exec($sql))exit('{"code":0,"msg":"修改轮询组成功！"}');
	else exit('{"code":-1,"msg":"修改轮询组失败['.$DB->error().']"}');
break;
case 'delRoll':
	$id=intval($_GET['id']);
	$row=$DB->getRow("select * from pre_roll where id='$id' limit 1");
	if(!$row)
		exit('{"code":-1,"msg":"当前轮询组不存在！"}');
	$sql = "DELETE FROM pre_roll WHERE id='$id'";
	if($DB->exec($sql))exit('{"code":0,"msg":"删除轮询组成功！"}');
	else exit('{"code":-1,"msg":"删除轮询组失败['.$DB->error().']"}');
break;
case 'saveRoll':
	$category = isset($_POST['category']) ? intval($_POST['category']) : 0;
	if($category !== 0 && $category !== 1){
		exit('{"code":-1,"msg":"轮询模式不合法"}');
	}
	$currency = isset($_POST['currency']) ? trim((string)$_POST['currency']) : '';
	$network  = isset($_POST['network'])  ? trim((string)$_POST['network'])  : '';
	if($category === 1){
		if($currency === ''){
			exit('{"code":-1,"msg":"按加密货币模式必须填写币种"}');
		}
		if(!preg_match('/^[A-Za-z0-9_\-]+$/', $currency)){
			exit('{"code":-1,"msg":"币种仅允许字母数字下划线和连字符"}');
		}
		if($network !== '' && !preg_match('/^[A-Za-z0-9_\-]+$/', $network)){
			exit('{"code":-1,"msg":"网络仅允许字母数字下划线和连字符"}');
		}
		if(strlen($currency) > 30 || strlen($network) > 30){
			exit('{"code":-1,"msg":"币种/网络长度不能超过 30"}');
		}
	}else{
		// 按支付方式模式：忽略 currency/network，统一保存为空
		$currency = '';
		$network = '';
	}
	if($_POST['action'] == 'add'){
		$name=trim($_POST['name']);
		$type=$category===1 ? 0 : intval($_POST['type']);
		$kind=intval($_POST['kind']);
		$row=$DB->getRow("select * from pre_roll where name='$name' limit 1");
		if($row)
			exit('{"code":-1,"msg":"轮询组名称重复"}');
		if($category === 1){
			$dup = $DB->getRow("SELECT id FROM pre_roll WHERE category=1 AND currency=:c AND network=:n LIMIT 1", [':c'=>$currency, ':n'=>$network]);
			if($dup) exit('{"code":-1,"msg":"该币种+网络已存在加密货币轮询组"}');
		}
		$ok = $DB->insert('roll', [
			'name'     => $name,
			'type'     => $type,
			'kind'     => $kind,
			'category' => $category,
			'currency' => $currency,
			'network'  => $network,
		]);
		if($ok)exit('{"code":0,"msg":"新增轮询组成功！"}');
		else exit('{"code":-1,"msg":"新增轮询组失败['.$DB->error().']"}');
	}else{
		$id=intval($_POST['id']);
		$name=trim($_POST['name']);
		$type=$category===1 ? 0 : intval($_POST['type']);
		$kind=intval($_POST['kind']);
		$row=$DB->getRow("select * from pre_roll where name='$name' and id<>$id limit 1");
		if($row)
			exit('{"code":-1,"msg":"轮询组名称重复"}');
		if($category === 1){
			$dup = $DB->getRow("SELECT id FROM pre_roll WHERE category=1 AND currency=:c AND network=:n AND id<>:id LIMIT 1", [':c'=>$currency, ':n'=>$network, ':id'=>$id]);
			if($dup) exit('{"code":-1,"msg":"该币种+网络已存在加密货币轮询组"}');
		}
		$old = $DB->getRow("SELECT category,type,currency,network FROM pre_roll WHERE id='$id' LIMIT 1");
		$shouldClearInfo = false;
		if($old){
			$oldCategory = (int)($old['category'] ?? 0);
			if($oldCategory !== $category) $shouldClearInfo = true;
			elseif($category === 1 && (strcasecmp((string)$old['currency'], $currency) !== 0 || strcasecmp((string)$old['network'], $network) !== 0)) $shouldClearInfo = true;
			elseif($category === 0 && (int)$old['type'] !== $type) $shouldClearInfo = true;
		}
		$update = [
			'name'     => $name,
			'type'     => $type,
			'kind'     => $kind,
			'category' => $category,
			'currency' => $currency,
			'network'  => $network,
		];
		if($shouldClearInfo){
			$update['info'] = '';
			$update['index'] = 0;
		}
		if($DB->update('roll', $update, ['id'=>$id]) !== false)exit('{"code":0,"msg":"修改轮询组成功！"}');
		else exit('{"code":-1,"msg":"修改轮询组失败['.$DB->error().']"}');
	}
break;
case 'rollInfo':
	$id=intval($_GET['id']);
	$row=$DB->getRow("select * from pre_roll where id='$id' limit 1");
	if(!$row)
		exit('{"code":-1,"msg":"当前轮询组不存在！"}');
	$statusSql = "";
	if($row['kind'] < 2) $statusSql = " AND A.status=1 ";
	$category = isset($row['category']) ? (int)$row['category'] : 0;
	if($category === 1){
		require_once SYSTEM_ROOT.'pay_type_category.php';
		$cur = strtoupper(trim((string)($row['currency'] ?? '')));
		$net = strtoupper(trim((string)($row['network'] ?? '')));
		if($cur === '') exit('{"code":-1,"msg":"加密货币轮询组缺少币种"}');
		// 复用前台 pay_type_category_resolve()：DB 列空时按调用值启发式推导
		$types = $DB->getAll("SELECT id,name,showname,currency,network FROM pre_type");
		$matchedIds = [];
		$matchedShowname = [];
		foreach($types as $t){
			$resolved = pay_type_category_resolve($t);
			$tCur = strtoupper((string)$resolved['currency']);
			$tNet = strtoupper((string)($resolved['network'] ?? ''));
			if($tCur !== $cur) continue;
			if($tNet !== $net) continue;
			$matchedIds[] = (int)$t['id'];
			$matchedShowname[(int)$t['id']] = (string)$t['showname'];
		}
		if(empty($matchedIds)){
			$hint = $net === '' ? '原生币（网络为空）' : ('网络='.$net);
			exit(json_encode(['code'=>-1,'msg'=>'没有找到币种='.$cur.' '.$hint.' 的支付方式，请先在「支付方式」中添加或正确填写 currency/network'], JSON_UNESCAPED_UNICODE));
		}
		$idIn = implode(',', $matchedIds);
		$list = $DB->getAll("SELECT A.id, A.name, A.type FROM pre_channel A WHERE A.type IN ({$idIn}) {$statusSql} ORDER BY A.id ASC");
		if(!$list){
			$msg = $net === ''
				? ('没有找到币种='.$cur.' 原生币的支付通道，请先在「支付通道」中添加并启用')
				: ('没有找到币种='.$cur.' 网络='.$net.' 的支付通道，请先在「支付通道」中添加并启用');
			exit(json_encode(['code'=>-1,'msg'=>$msg], JSON_UNESCAPED_UNICODE));
		}
		// 拼上对应支付方式 showname 便于区分（同币种网络可能跨多个 pre_type）
		foreach($list as &$lr){
			$tn = isset($matchedShowname[(int)$lr['type']]) ? $matchedShowname[(int)$lr['type']] : '';
			if($tn !== '') $lr['name'] = $lr['name'].' ['.$tn.']';
			unset($lr['type']);
		}
		unset($lr);
	}else{
		$type = (int)$row['type'];
		$list=$DB->getAll("SELECT A.id, A.name FROM pre_channel A WHERE A.type='{$type}' {$statusSql} ORDER BY A.id ASC");
		if(!$list)exit('{"code":-1,"msg":"没有找到支持该支付方式的通道"}');
	}
	if(!empty($row['info'])){
		$arr = explode(',',$row['info']);
		$info = [];
		foreach($arr as $item){
			$a = explode(':',$item);
			$info[] = ['channel'=>$a[0], 'weight'=>$a[1]?$a[1]:1];
		}
	}else{
		$info = null;
	}
	$result=array("code"=>0,"msg"=>"succ","channels"=>$list,"info"=>$info,"kind"=>$row['kind']);
	exit(json_encode($result));
break;
case 'saveRollInfo':
	$id=intval($_GET['id']);
	$row=$DB->getRow("select * from pre_roll where id='$id' limit 1");
	if(!$row)
		exit('{"code":-1,"msg":"当前轮询组不存在！"}');
	$list=$_POST['list'];
	if(empty($list))
		exit('{"code":-1,"msg":"通道配置不能为空！"}');
	$info = '';
	foreach($list as $a){
		$info .= $row['kind']==1 ? $a['channel'].':'.$a['weight'].',' : $a['channel'].',';
	}
	$info = trim($info,',');
	if(empty($info))
		exit('{"code":-1,"msg":"通道配置不能为空！"}');
	$sql = "UPDATE pre_roll SET info='{$info}' WHERE id='$id'";
	if($DB->exec($sql)!==false)exit('{"code":0,"msg":"修改轮询组成功！"}');
	else exit('{"code":-1,"msg":"修改轮询组失败['.$DB->error().']"}');
break;

case 'getChannelMoney': //统计支付通道金额
	$type=intval($_GET['type']);
	$channel=intval($_GET['channel']);
	if($type == 2 || $type == 3){
		$today=$type==3 ? date("Y-m-d", strtotime("-1 day")) : date("Y-m-d");
		$orders=$DB->getColumn("SELECT COUNT(*) FROM pre_order WHERE date='$today' AND channel='$channel' AND status>0");
		exit('{"code":0,"msg":"succ","money":"'.$orders.'"}');
	}else{
		$today=$type==1 ? date("Y-m-d", strtotime("-1 day")) : date("Y-m-d");
		$money=$DB->getColumn("SELECT SUM(realmoney) FROM pre_order WHERE date='$today' AND channel='$channel' AND status>0");
		exit('{"code":0,"msg":"succ","money":"'.round($money,2).'"}');
	}
break;
case 'getSubChannelMoney': //统计子通道金额
	$type=intval($_GET['type']);
	$channel=trim($_GET['channel']);
	$today=$type==1 ? date("Y-m-d", strtotime("-1 day")) : date("Y-m-d");
	$channel = explode('|', $channel);
	$channel = array_map('intval', $channel);
	$money=$DB->getColumn("SELECT SUM(realmoney) FROM pre_order WHERE date='$today' AND subchannel IN (".implode(",", $channel).") AND status>0");
	exit('{"code":0,"msg":"succ","money":"'.round($money,2).'"}');
break;
case 'getTypeMoney': //统计支付方式金额
	$type=intval($_GET['type']);
	$typeid=intval($_GET['typeid']);
	$today=$type==1 ? date("Y-m-d", strtotime("-1 day")) : date("Y-m-d");
	$money=$DB->getColumn("SELECT SUM(realmoney) FROM pre_order WHERE date='$today' AND type='$typeid' AND status>0");
	exit('{"code":0,"msg":"succ","money":"'.round($money,2).'"}');
break;
case 'getChannelRate':
	$channel=intval($_GET['channel']);
	$thtime = date("Y-m-d").' 00:00:00';
	$all = 0;
	$success = 0;
	$orders=$DB->getAll("SELECT * FROM pre_order WHERE addtime>='$thtime' AND channel='$channel'");
	foreach($orders as $order){
		$all++;
		if($order['status']>0)$success++;
	}
	$rate = $all > 0 ? round($success*100/$all, 2) : 0;
	exit('{"code":0,"msg":"succ","rate":"'.$rate.'"}');
break;
case 'getSuccessRate':
	$channel = intval($_GET['channel']);
	$thtime = date("Y-m-d");
	$orderrow=$DB->getRow("SELECT COUNT(*) allnum,COUNT(IF(status>0, 1, NULL)) sucnum FROM pre_order WHERE addtime>='$thtime' AND channel='$channel'");
	$success_rate = $orderrow && $orderrow['allnum'] > 0 ? round($orderrow['sucnum']/$orderrow['allnum']*100,2) : 100;
	exit('{"code":0,"msg":"succ","data":"' . $success_rate . '"}');
break;

case 'testpay':
	$channel=intval($_POST['channel']);
	$subchannel=intval($_POST['subchannel']);
	$param=!empty($_POST['param'])?trim($_POST['param']):null;
	$row=$DB->getRow("select * from pre_channel where id='$channel' limit 1");
	if(!$row)
		exit('{"code":-1,"msg":"当前支付通道不存在！"}');
	if($subchannel > 0){
		if(!$DB->getRow("select * from pre_subchannel where id='$subchannel' limit 1")) exit('{"code":-1,"msg":"当前子通道不存在！"}');
	}
	if(empty($row['config']))exit('{"code":-1,"msg":"请先配置好密钥"}');
	if(!$conf['test_pay_uid'])exit('{"code":-1,"msg":"请先配置测试支付收款商户ID"}');
	$money=trim(daddslashes($_POST['money']));
	$name=trim(daddslashes($_POST['name']));
	if($money<=0 || !is_numeric($money) || !preg_match('/^[0-9.]+$/', $money))exit('{"code":-1,"msg":"金额不合法"}');
	if($conf['pay_maxmoney']>0 && $money>$conf['pay_maxmoney'])exit('{"code":-1,"msg":"最大支付金额是'.$conf['pay_maxmoney'].'元"}');
	if($conf['pay_minmoney']>0 && $money<$conf['pay_minmoney'])exit('{"code":-1,"msg":"最小支付金额是'.$conf['pay_minmoney'].'元"}');
	$trade_no=date("YmdHis").rand(11111,99999);
	$return_url=$siteurl.'user/test.php?ok=1&trade_no='.$trade_no;
	$domain=getdomain($return_url);
	if(!$DB->exec("INSERT INTO `pre_order` (`trade_no`,`out_trade_no`,`uid`,`tid`,`addtime`,`name`,`money`,`type`,`channel`,`subchannel`,`realmoney`,`getmoney`,`notify_url`,`return_url`,`domain`,`ip`,`param`,`status`) VALUES (:trade_no, :out_trade_no, :uid, 3, NOW(), :name, :money, :type, :channel, :subchannel, :realmoney, :getmoney, :notify_url, :return_url, :domain, :clientip, :param, 0)", [':trade_no'=>$trade_no, ':out_trade_no'=>$trade_no, ':uid'=>$conf['test_pay_uid'], ':name'=>$name, ':money'=>$money, ':type'=>$row['type'], ':channel'=>$channel, ':subchannel'=>$subchannel, ':realmoney'=>$money, ':getmoney'=>$money, ':notify_url'=>$return_url, ':return_url'=>$return_url, ':domain'=>$domain, ':clientip'=>$clientip, ':param'=>$param]))exit('{"code":-1,"msg":"创建订单失败，请返回重试！"}');
	$result = ['code'=>0, 'msg'=>'succ', 'url'=>'./testsubmit.php?trade_no='.$trade_no];
	exit(json_encode($result));
break;

case 'getWeixin':
	$id=intval($_GET['id']);
	$row=$DB->getRow("select * from pre_weixin where id='$id' limit 1");
	if(!$row)
		exit('{"code":-1,"msg":"当前公众号/小程序不存在！"}');
	$result = ['code'=>0,'msg'=>'succ','data'=>$row];
	exit(json_encode($result));
break;
case 'delWeixin':
	$id=intval($_GET['id']);
	$row=$DB->getRow("select * from pre_weixin where id='$id' limit 1");
	if(!$row)
		exit('{"code":-1,"msg":"当前公众号/小程序不存在！"}');
	$row=$DB->getRow("select * from pre_channel where appwxmp='$id' limit 1");
	if($row)
		exit('{"code":-1,"msg":"删除失败，存在使用该微信公众号的支付通道"}');
	$row=$DB->getRow("select * from pre_channel where appwxa='$id' limit 1");
	if($row)
		exit('{"code":-1,"msg":"删除失败，存在使用该微信小程序的支付通道"}');
	$sql = "DELETE FROM pre_weixin WHERE id='$id'";
	if($DB->exec($sql)){
		exit('{"code":0,"msg":"删除公众号/小程序成功！"}');
	}else exit('{"code":-1,"msg":"删除公众号/小程序失败['.$DB->error().']"}');
break;
case 'saveWeixin':
	if($_POST['action'] == 'add'){
		$type=intval($_POST['type']);
		$name=trim($_POST['name']);
		$appid=trim($_POST['appid']);
		$appsecret=trim($_POST['appsecret']);
		$row=$DB->getRow("select * from pre_weixin where name='$name' limit 1");
		if($row)
			exit('{"code":-1,"msg":"名称重复"}');
		$row=$DB->getRow("select * from pre_weixin where appid='$appid' limit 1");
		if($row)
			exit('{"code":-1,"msg":"APPID重复"}');
		if($DB->insert('weixin', ['type'=>$type, 'name'=>$name, 'appid'=>$appid, 'appsecret'=>$appsecret, 'status'=>1, 'addtime'=>'NOW()']))exit('{"code":0,"msg":"新增公众号/小程序成功！"}');
		else exit('{"code":-1,"msg":"新增公众号/小程序失败['.$DB->error().']"}');
	}else{
		$id=intval($_POST['id']);
		$type=intval($_POST['type']);
		$name=trim($_POST['name']);
		$appid=trim($_POST['appid']);
		$appsecret=trim($_POST['appsecret']);
		$row=$DB->getRow("select * from pre_weixin where name='$name' and id<>$id limit 1");
		if($row)
			exit('{"code":-1,"msg":"名称重复"}');
		$row=$DB->getRow("select * from pre_weixin where appid='$appid' and id<>$id limit 1");
		if($row)
			exit('{"code":-1,"msg":"APPID重复"}');
		if($DB->update('weixin', ['type'=>$type, 'name'=>$name, 'appid'=>$appid, 'appsecret'=>$appsecret], ['id'=>$id])!==false)exit('{"code":0,"msg":"修改公众号/小程序成功！"}');
		else exit('{"code":-1,"msg":"修改公众号/小程序失败['.$DB->error().']"}');
	}
break;
case 'testweixin':
	$id=intval($_POST['id']);
	$row=$DB->getRow("select * from pre_weixin where id='$id' limit 1");
	if(!$row)
		exit('{"code":-1,"msg":"当前公众号/小程序不存在！"}');
	try{
		$wechat = new \lib\wechat\WechatAPI($id);
		$access_token = $wechat->getAccessToken(true);
	}catch(Exception $e){
		exit('{"code":-1,"msg":"'.$e->getMessage().'"}');
	}
	exit('{"code":0,"msg":"接口连接测试成功！"}');
break;

case 'getWework':
	$id=intval($_GET['id']);
	$row=$DB->getRow("select * from pre_wework where id='$id' limit 1");
	if(!$row)
		exit('{"code":-1,"msg":"当前企业微信不存在！"}');
	$result = ['code'=>0,'msg'=>'succ','data'=>$row];
	exit(json_encode($result));
break;
case 'setWework':
	$id=intval($_GET['id']);
	$status=intval($_GET['status']);
	$row=$DB->getRow("select * from pre_wework where id='$id' limit 1");
	if(!$row)
		exit('{"code":-1,"msg":"当前企业微信不存在！"}');
	$sql = "UPDATE pre_wework SET status='$status' WHERE id='$id'";
	if($DB->exec($sql))exit('{"code":0,"msg":"修改企业微信成功！"}');
	else exit('{"code":-1,"msg":"修改企业微信失败['.$DB->error().']"}');
break;
case 'delWework':
	$id=intval($_GET['id']);
	$row=$DB->getRow("select * from pre_wework where id='$id' limit 1");
	if(!$row)
		exit('{"code":-1,"msg":"当前企业微信不存在！"}');
	if($DB->delete('wework', ['id'=>$id])){
		$DB->delete('wxkfaccount', ['wid'=>$id]);
		exit('{"code":0,"msg":"删除企业微信成功！"}');
	}else exit('{"code":-1,"msg":"删除企业微信失败['.$DB->error().']"}');
break;
case 'saveWework':
	if($_POST['action'] == 'add'){
		$name=trim($_POST['name']);
		$appid=trim($_POST['appid']);
		$appsecret=trim($_POST['appsecret']);
		$row=$DB->getRow("select * from pre_wework where name='$name' limit 1");
		if($row)
			exit('{"code":-1,"msg":"名称重复"}');
		$row=$DB->getRow("select * from pre_wework where appid='$appid' limit 1");
		if($row)
			exit('{"code":-1,"msg":"企业ID重复"}');
		if($DB->insert('wework', ['name'=>$name, 'appid'=>$appid, 'appsecret'=>$appsecret, 'status'=>1, 'addtime'=>'NOW()']))exit('{"code":0,"msg":"新增企业微信成功！请点击刷新客服账号数量"}');
		else exit('{"code":-1,"msg":"新增企业微信失败['.$DB->error().']"}');
	}else{
		$id=intval($_POST['id']);
		$name=trim($_POST['name']);
		$appid=trim($_POST['appid']);
		$appsecret=trim($_POST['appsecret']);
		$row=$DB->getRow("select * from pre_wework where name='$name' and id<>$id limit 1");
		if($row)
			exit('{"code":-1,"msg":"名称重复"}');
		$row=$DB->getRow("select * from pre_wework where appid='$appid' and id<>$id limit 1");
		if($row)
			exit('{"code":-1,"msg":"企业ID重复"}');
		if($DB->update('wework', ['name'=>$name, 'appid'=>$appid, 'appsecret'=>$appsecret], ['id'=>$id])!==false)exit('{"code":0,"msg":"修改企业微信成功！"}');
		else exit('{"code":-1,"msg":"修改企业微信失败['.$DB->error().']"}');
	}
break;
case 'refreshWework':
	$id=intval($_POST['id']);
	$row=$DB->getRow("select * from pre_wework where id='$id' limit 1");
	if(!$row)
		exit('{"code":-1,"msg":"当前企业微信不存在！"}');
	$wework = new \lib\wechat\WeWorkAPI($id);
	try{
		$account_list = $wework->getKFList();
		if(count($account_list) == 0){
			exit('{"code":-1,"msg":"没有添加客服账号"}');
		}
		$account_data = $DB->findAll('wxkfaccount', 'id,openkfid', ['wid'=>$id]);
		foreach($account_list as $account){
			$isExsist = false;
			foreach($account_data as $find){
				if($find['openkfid'] == $account['open_kfid']){
					$isExsist = true;break;
				}
			}
			if(!$isExsist){
				$DB->insert('wxkfaccount', ['wid'=>$id, 'openkfid'=>$account['open_kfid'], 'name'=>$account['name'], 'addtime'=>'NOW()']);
			}
		}
		foreach($account_data as $account){
			$isExsist = false;
			foreach($account_list as $find){
				if($find['open_kfid'] == $account['openkfid']){
					$isExsist = true;break;
				}
			}
			if(!$isExsist){
				$DB->delete('wxkfaccount', ['id'=>$account['id']]);
			}
		}
		exit(json_encode(['code'=>0, 'msg'=>'成功获取到'.count($account_list).'个客服账号']));
	}catch(Exception $e){
		exit('{"code":-1,"msg":"'.$e->getMessage().'"}');
	}
break;
case 'testWework':
	$id=intval($_POST['id']);
	$row=$DB->getRow("select * from pre_wework where id='$id' limit 1");
	if(!$row)
		exit('{"code":-1,"msg":"当前企业微信不存在！"}');
	$wework = new \lib\wechat\WeWorkAPI($id);
	try{
		$access_token = $wework->getAccessToken(true);
	}catch(Exception $e){
		exit('{"code":-1,"msg":"'.$e->getMessage().'"}');
	}
	exit('{"code":0,"msg":"接口连接测试成功！"}');
break;

default:
	exit('{"code":-4,"msg":"No Act"}');
break;
}