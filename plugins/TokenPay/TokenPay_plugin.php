<?php

class TokenPay_plugin{
	static public $info = [
		'name'        => 'TokenPay', //支付插件英文名称，需和目录名称一致，不能有重复
		'showname'    => 'TokenPay', //支付插件显示名称
		'author'      => 'TokenPay', //支付插件作者
		'link'        => 'https://github.com/LightCountry/TokenPay', //支付插件作者链接
		'types'       => ['TRX', 'USDT_TRC20', 'EVM_ETH_ETH', 'EVM_ETH_USDT_ERC20', 'EVM_ETH_USDC_ERC20', 'EVM_BSC_BNB', 'EVM_BSC_USDT_BEP20', 'EVM_BSC_USDC_BEP20', 'EVM_Polygon_POL', 'EVM_Polygon_USDT_ERC20', 'EVM_Polygon_USDC_ERC20'], //支付插件支持的支付方式，可选的有alipay,qqpay,wxpay,bank
		'inputs' => [ //支付插件要求传入的参数以及参数显示名称，可选的有appid,appkey,appsecret,appurl,appmchid
			'appurl' => [
				'name' => 'API接口地址',
				'type' => 'input',
				'note' => '以http://或https://开头，末尾不要有斜线/',
			],
			'appid' => [
				'name' => 'APP ID',
				'type' => 'input',
				'note' => '输入任意字符即可',
			],
			'appkey' => [
				'name' => 'API秘钥',
				'type' => 'input',
				'note' => 'TokenPay API 密钥',
			],
			'unified_cashier' => [
				'name' => '统一收银台',
				'type' => 'select',
				'options' => [
					'0' => '关闭（跳转 TokenPay 官方收银页）',
					'1' => '开启（在本站内渲染地址+金额+二维码）',
				],
				'note' => '开启后：下单后直接在本站收银台展示收款地址与金额，保持站点品牌与 UI 一致；回调链路不变。',
			],
		],
		'select' => null,
		'note' => '', //支付密钥填写说明
		'bindwxmp' => false, //是否支持绑定微信公众号
		'bindwxa' => false, //是否支持绑定微信小程序
	];

	static public function submit(){
		global $siteurl, $channel, $order, $sitename;

		if(in_array($order['typename'], self::$info['types'])){
			return ['type'=>'jump','url'=>'/pay/TokenPay/'.TRADE_NO.'/?sitename='.$sitename];
		}
	}

	static public function mapi(){
		global $siteurl, $channel, $order, $device, $mdevice;

		if(in_array($order['typename'], self::$info['types'])){
			$result = self::TokenPay($order['typename']);
			// 统一收银台场景下，把 type=page 转换为 API 友好的 crypto 响应
			if(!empty($channel['unified_cashier']) && isset($result['type']) && $result['type'] === 'page'){
				return ['type'=>'crypto','data'=>$result['data']];
			}
			return $result;
		}
	}

	static private function getApiUrl(){
		global $channel;
		$apiurl = $channel['appurl'];
		if(substr($apiurl, -1, 1) == '/')$apiurl = substr($apiurl, 0, -1);
		return $apiurl;
	}

	static private function sendRequest($url, $param, $key){
		$url = self::getApiUrl().$url;
		$post = json_encode($param);
		$response = get_curl($url,$post,0,0,0,0,0,['Content-Type: application/json']);

		return json_decode($response, true);
	}

    static public function Sign($params,$appKey){
        if(!empty($params)){
           $p =  ksort($params);
           if($p){
               $str = '';
               foreach ($params as $k=>$val){
                   $str .= $k .'=' . $val . '&';
               }
               $strs = rtrim($str, '&').$appKey;

               return md5($strs);
           }
        }
        
        return null;
    }
    
    
    
	//通用创建订单（保留原签名：返回 pay URL 字符串，向后兼容调用方）
	static private function CreateOrder($type, $extra = null){
		$result = self::CreateOrderRaw($extra);
		return $result['code_url'];
	}

	/**
	 * 调用 TokenPay /CreateOrder 并返回完整上下文
	 *
	 * @param array|null $extra 额外参数
	 * @return array{code_url:string, info:array<string,mixed>, raw:array<string,mixed>}
	 * @throws Exception
	 */
	static private function CreateOrderRaw($extra = null){
		global $siteurl, $channel, $order, $conf;

		$param = [
		    'OutOrderId'   => TRADE_NO,
		    'OrderUserKey' => (string)$order['uid'],
		    'ActualAmount' => $order['realmoney'],
		    'Currency'     => $order['typename'],
		    'NotifyUrl'    => $conf['localurl'].'pay/notify/'.TRADE_NO.'/',
		    'RedirectUrl'  => $siteurl.'pay/return/'.TRADE_NO.'/'
		];

		if($extra){
			$param = array_merge($param, $extra);
		}
		$param['Signature'] = self::Sign($param,$channel['appkey']);

		$result = self::sendRequest('/CreateOrder', $param, $channel['appkey']);

		if(isset($result["success"]) && $result["success"]){
			$code_url = $result['data'];
			$info = isset($result['info']) && is_array($result['info']) ? $result['info'] : [];
			// 优先把 TokenPay 内部订单号写入 api_trade_no，便于对账；info 缺失时兜底存 pay URL
			$api_trade_no = !empty($info['Id']) ? (string)$info['Id'] : (string)$code_url;
			\lib\Payment::updateOrder(TRADE_NO, $api_trade_no);
			return ['code_url'=>$code_url, 'info'=>$info, 'raw'=>$result];
		}
		throw new Exception($result["message"]?$result["message"]:'返回数据解析失败');
	}

    static public function TokenPay(){
		global $channel;

		try{
			$created = self::CreateOrderRaw(null);
		}catch(Exception $ex){
			return ['type'=>'error','msg'=>'TokenPay创建订单失败！'.$ex->getMessage()];
		}

		// 统一收银台：站内渲染地址+金额+二维码
		if(!empty($channel['unified_cashier']) && !empty($created['info'])){
			$payinfo = self::_buildUnifiedPayInfo($created['info'], $created['code_url']);
			\lib\Payment::updateOrderExt(TRADE_NO, $payinfo);
			return [
				'type' => 'page',
				'page' => 'crypto',
				'data' => self::_unifiedPageData($payinfo),
			];
		}

        return ['type'=>'jump','url'=>$created['code_url']];
	}

	/**
	 * 将 TokenPay /CreateOrder 响应的 info 对象整理为统一收银台上下文
	 *
	 * @param array<string,mixed> $info     响应的 info 段（ToAddress/Amount/... 等）
	 * @param string              $code_url TokenPay 官方 Checkout URL（兜底跳转用）
	 * @return array<string,mixed>
	 */
	static private function _buildUnifiedPayInfo(array $info, string $code_url): array
	{
		$expire_at = 0;
		if(!empty($info['ExpireTime'])){
			$ts = strtotime((string)$info['ExpireTime']);
			if($ts > 0) $expire_at = $ts;
		}
		$qrcode = '';
		if(!empty($info['QrCodeBase64'])){
			$qrcode = (string)$info['QrCodeBase64'];
		}elseif(!empty($info['QrCodeLink'])){
			$qrcode = (string)$info['QrCodeLink'];
		}
		return [
			'plugin'       => 'TokenPay',
			'address'      => (string)($info['ToAddress'] ?? ''),
			'amount'       => (string)($info['Amount'] ?? ''),
			'currency'     => (string)($info['CurrencyName'] ?? ($info['Currency'] ?? '')),
			'chain'        => (string)($info['BlockChainName'] ?? ''),
			'fiat'         => (string)($info['BaseCurrency'] ?? 'CNY'),
			'fiat_amount'  => (string)($info['ActualAmount'] ?? ''),
			'expire_at'    => $expire_at,
			'qrcode'       => $qrcode,
			'fallback_url' => $code_url,
			'api_trade_no' => (string)($info['Id'] ?? ''),
		];
	}

	/**
	 * 扩展数据 → crypto.php 局部变量
	 *
	 * @param array<string,mixed> $ext
	 * @return array<string,mixed>
	 */
	static private function _unifiedPageData(array $ext): array
	{
		return [
			'pay_plugin'       => 'TokenPay',
			'pay_address'      => $ext['address'] ?? '',
			'pay_amount'       => $ext['amount'] ?? '',
			'pay_currency'     => $ext['currency'] ?? '',
			'pay_chain'        => $ext['chain'] ?? '',
			'pay_fiat'         => $ext['fiat'] ?? 'CNY',
			'pay_fiat_amount'  => $ext['fiat_amount'] ?? '',
			'pay_expire_at'    => (int)($ext['expire_at'] ?? 0),
			'pay_qrcode'       => $ext['qrcode'] ?? '',
			'pay_fallback_url' => $ext['fallback_url'] ?? '',
		];
	}

	//异步回调
	static public function notify(){
		global $channel, $order;

		$resultJson = file_get_contents("php://input");
		$resultArr = json_decode($resultJson,true);
		$Signature = $resultArr["Signature"];
		
		//生成签名时取出 Signature 字段
		unset($resultArr['Signature']);
		
		$sign = self::Sign($resultArr,$channel['appkey']);
    
		if($sign===$Signature){
			$out_trade_no = $resultArr['OutOrderId'];

			if ($out_trade_no == TRADE_NO) {
				processNotify($order, $out_trade_no);
			}else{
			    return ['type'=>'html','data'=>'fail'];
			}
			return ['type'=>'html','data'=>'ok'];
		}else{
			return ['type'=>'html','data'=>'fail'];
		}
	}

	//支付返回页面
	static public function return(){
		return ['type'=>'page','page'=>'return'];
	}
}
