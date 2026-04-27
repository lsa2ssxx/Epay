<?php
$nosession = true;
require './includes/common.php';
$act=isset($_GET['act'])?daddslashes($_GET['act']):null;

@header('Content-Type: application/json; charset=UTF-8');

if(!checkRefererHost())exit('{"code":403}');

switch($act){
case 'captcha_verify':
	$pid=$_POST['pid'];
	$trade_no=$_POST['trade_no'];
	if(!$pid || !$trade_no)exit(json_encode(['code'=>-1, 'msg'=>'参数不完整']));
	$captcha_result = verify_captcha4();
	if($captcha_result !== true){
		echo json_encode(['code'=>-1, 'msg'=>'验证失败，请重新验证']);
	}
	$key = time().getDefendKey($pid, $trade_no).rand(111111,999999);
	echo json_encode(['code'=>0, 'key'=>$key]);
break;
default:
	$trade_no=isset($_GET['trade_no'])?daddslashes($_GET['trade_no']):exit('{"code":-2,"msg":"No trade_no!"}');
	$type = isset($_GET['type']) ? daddslashes($_GET['type']) : '';

	$row=$DB->getRow("SELECT * FROM pre_order WHERE trade_no='{$trade_no}' limit 1");
	$paysuccess_url = '/paysuccess.php?trade_no=' . urlencode($trade_no);
	if($row['status']>=1){
		// 支付完成5分钟后禁止跳转回网站
		if(!empty($row['endtime']) && time() - strtotime($row['endtime']) > 300){
			$jumpurl = '/payok.html';
		}else{
			$url=creat_callback($row);
			$jumpurl = $url['return'];
		}
		if($row['status'] == 2){
			$jumpurl = '/payerr.html';
		}
		$resp = ['code'=>1, 'msg'=>'付款成功', 'backurl'=>$jumpurl];
		// 成功订单：先进入站内「已检测」过渡（staged=1 可配合非链上通道做纯展示），再到 paysuccess?completed
		if($row['status'] == 1){
			$resp['paysuccess_url'] = $paysuccess_url . '&state=detected&staged=1';
		}
		echo json_encode($resp);
	}else{
		// 中间态：链上已检测、尚未确认
		$detected = false;
		if($type === 'crypto' && !empty($row['ext'])){
			$ext = @unserialize($row['ext']);
			if(is_array($ext) && !empty($ext['detected_at'])){
				$detected = true;
			}
		}
		if($detected){
			echo json_encode([
				'code'           => 2,
				'msg'            => '付款已检测',
				'paysuccess_url' => $paysuccess_url . '&state=detected',
			]);
		}else{
			echo json_encode(['code'=>-1, 'msg'=>'未付款']);
		}
	}
break;
}

?>