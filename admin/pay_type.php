<?php
/**
 * 支付方式
**/
include("../includes/common.php");
include_once SYSTEM_ROOT.'pay_type_crypto_sort.php';
include_once SYSTEM_ROOT.'pay_type_category.php';
$title='支付方式';
include './head.php';
if($islogin==1){}else exit("<script language='javascript'>window.location.href='./login.php';</script>");
?>
<style>
.type-logo{width: 18px;margin-top: -2px;padding-right: 4px;}
.type-plugin-panel{margin-bottom:16px;}
.type-plugin-panel .panel-heading{font-size:14px;}
.type-plugin-related summary{cursor:pointer;outline:none;}
.type-plugin-related summary::-webkit-details-marker{display:none;}
.type-plugin-related ul{list-style:none;}
</style>
  <div class="container" style="padding-top:70px;">
    <div class="col-md-8 center-block" style="float: none;">
<?php
function display_device($device){
	if($device==1)
		return 'PC';
	elseif($device==2)
		return 'Mobile';
	else
		return 'PC+Mobile';
}

function pay_type_render_rows($rows){
	foreach($rows as $res){
		$pluginNote = '';
		if(!empty($res['_plugins'])){
			$pl = $res['_plugins'];
			usort($pl, function($a, $b){
				$sa = $a['showname'] ?? $a['name'];
				$sb = $b['showname'] ?? $b['name'];
				return strcasecmp($sa, $sb);
			});
			$n = count($pl);
			if($n <= 4){
				$bits = [];
				foreach($pl as $z){
					$sn = htmlspecialchars($z['showname'] ?? '', ENT_QUOTES, 'UTF-8');
					$nm = htmlspecialchars($z['name'], ENT_QUOTES, 'UTF-8');
					$bits[] = $sn.'（'.$nm.'）';
				}
				$pluginNote = '<div class="text-muted" style="font-size:12px;margin-top:4px">关联插件：'.implode('、', $bits).'</div>';
			}else{
				$lis = '';
				foreach($pl as $z){
					$sn = htmlspecialchars($z['showname'] ?? '', ENT_QUOTES, 'UTF-8');
					$nm = htmlspecialchars($z['name'], ENT_QUOTES, 'UTF-8');
					$lis .= '<li style="margin:3px 0">'.$sn.' <span class="text-muted">（'.$nm.'）</span></li>';
				}
				$pluginNote = '<details class="type-plugin-related text-muted" style="margin-top:4px;font-size:12px"><summary>关联插件（共 <strong>'.(int)$n.'</strong> 个）<span class="text-info">点击展开</span></summary><ul style="margin:8px 0 0;padding:6px 8px 6px 10px;max-height:11em;overflow-y:auto;border-left:3px solid #bce8f1;background:#f9f9f9">'.$lis.'</ul></details>';
			}
		}
		$nameDisp = htmlspecialchars($res['name'], ENT_QUOTES, 'UTF-8');
		$shownameDisp = htmlspecialchars($res['showname'], ENT_QUOTES, 'UTF-8');
		$resolved = pay_type_category_resolve($res);
		$cur_set = trim((string)($res['currency'] ?? '')) !== '';
		$net_set = trim((string)($res['network'] ?? '')) !== '';
		$cur_h = htmlspecialchars($resolved['currency'], ENT_QUOTES, 'UTF-8');
		$net_h = $resolved['network'] !== null ? htmlspecialchars($resolved['network'], ENT_QUOTES, 'UTF-8') : '<span class="text-muted">—</span>';
		$cur_label = $cur_set
			? '<span class="label label-info" title="管理员设置">'.$cur_h.'</span>'
			: '<span class="label label-default" title="按调用值自动推导">'.$cur_h.'</span>';
		$net_label = !$net_set && $resolved['network'] === null
			? $net_h
			: ($net_set
				? '<span class="label label-primary" title="管理员设置">'.$net_h.'</span>'
				: '<span class="label label-default" title="按调用值自动推导">'.$net_h.'</span>');
		$catCell = '<div style="white-space:nowrap;font-size:12px;line-height:1.8;">'.$cur_label.' '.$net_label.'</div>';
		echo '<tr><td><b>'.$nameDisp.'</b></td><td>'.pay_type_icon_html($res['name'], 'type-logo').$shownameDisp.$pluginNote.'</td><td>'.$catCell.'</td><td>'.display_device($res['device']).'</td><td><a onclick="getAll(0,'.$res['id'].',this)" title="点此获取最新数据">[刷新]</a></td><td>'.($res['status']==1?'<a class="btn btn-xs btn-success" onclick="setStatus('.$res['id'].',0)">已开启</a>':'<a class="btn btn-xs btn-warning" onclick="setStatus('.$res['id'].',1)">已关闭</a>').'</td><td><a class="btn btn-xs btn-info" onclick="editframe('.$res['id'].')">编辑</a>&nbsp;<a class="btn btn-xs btn-danger" onclick="delItem('.$res['id'].')">删除</a>&nbsp;<a href="./order.php?type='.$res['id'].'" target="_blank" class="btn btn-xs btn-default">订单</a></td></tr>';
	}
}

/**
 * 单插件面板内行顺序：BEpusdt/TokenPay 按资产类型深度排序（USDT 整块→USDC 整块→原生币，见 pay_type_crypto_sort.php）；其余按调用值自然序。
 */
function pay_type_sort_rows_for_plugin(array $rows, $pluginName)
{
	if ($pluginName === 'bepusdt') {
		return pay_type_sort_rows_bepusdt_deep($rows);
	}
	if ($pluginName === 'TokenPay') {
		return pay_type_sort_rows_tokenpay_deep($rows);
	}
	usort($rows, function ($a, $b) {
		return strnatcasecmp($a['name'] ?? '', $b['name'] ?? '');
	});

	return $rows;
}

/** 通用 / 其他分组：按调用值自然序 */
function pay_type_sort_rows_default(array $rows)
{
	usort($rows, function ($a, $b) {
		return strnatcasecmp($a['name'] ?? '', $b['name'] ?? '');
	});

	return $rows;
}

$list = $DB->getAll("SELECT * FROM pre_type ORDER BY id ASC");
$plugins = [];
foreach(\lib\Plugin::getList() as $pn){
	if(!$pn) continue;
	$cfg = \lib\Plugin::getConfig($pn);
	if(!$cfg || empty($cfg['name'])) continue;
	$plugins[] = [
		'name' => $cfg['name'],
		'showname' => $cfg['showname'] ?? $cfg['name'],
		'types' => $cfg['types'] ?? null,
	];
}
$typeToPlugins = [];
foreach($plugins as $p){
	$tokens = [];
	if(isset($p['types'])){
		if(is_array($p['types'])){
			$tokens = $p['types'];
		}else{
			$typesStr = (string)$p['types'];
			$tokens = explode(',', $typesStr);
		}
	}
	$tokens = array_unique(array_filter(array_map('trim', $tokens)));
	foreach($tokens as $t){
		if($t === '')continue;
		if(!isset($typeToPlugins[$t]))$typeToPlugins[$t] = [];
		$typeToPlugins[$t][$p['name']] = [
			'name' => $p['name'],
			'showname' => (!empty($p['showname'])) ? $p['showname'] : $p['name'],
		];
	}
}
$bucket_common = [];
$bucket_by_plugin = [];
$bucket_other = [];
foreach($list as $res){
	$n = $res['name'];
	$pmap = isset($typeToPlugins[$n]) ? $typeToPlugins[$n] : [];
	$plist = array_values($pmap);
	$cnt = count($plist);
	if($cnt >= 2){
		$res['_plugins'] = $plist;
		$bucket_common[] = $res;
	}elseif($cnt === 1){
		$pn = $plist[0]['name'];
		if(!isset($bucket_by_plugin[$pn])){
			$bucket_by_plugin[$pn] = ['meta' => $plist[0], 'rows' => []];
		}
		$bucket_by_plugin[$pn]['rows'][] = $res;
	}else{
		$bucket_other[] = $res;
	}
}
ksort($bucket_by_plugin);
foreach ($bucket_by_plugin as $k => $pack) {
	$bucket_by_plugin[$k]['rows'] = pay_type_sort_rows_for_plugin($pack['rows'], $pack['meta']['name']);
}
if (count($bucket_common) > 0) {
	$bucket_common = pay_type_sort_rows_default($bucket_common);
}
if (count($bucket_other) > 0) {
	$bucket_other = pay_type_sort_rows_default($bucket_other);
}
?>
<div class="modal" id="modal-store" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true" data-backdrop="static">
	<div class="modal-dialog">
		<div class="modal-content animated flipInX">
			<div class="modal-header">
				<button type="button" class="close" data-dismiss="modal"><span
							aria-hidden="true">&times;</span><span
							class="sr-only">Close</span></button>
				<h4 class="modal-title" id="modal-title">支付方式修改/添加</h4>
			</div>
			<div class="modal-body">
				<form class="form-horizontal" id="form-store">
					<input type="hidden" name="action" id="action"/>
					<input type="hidden" name="id" id="id"/>
					<div class="form-group">
						<label class="col-sm-2 control-label no-padding-right"></label>
						<div class="col-sm-10">
							<div class="alert alert-warning">
								注意：同一个调用值+支持设备不能重复
							</div>
						</div>
					</div>
					<div class="form-group">
						<label class="col-sm-2 control-label no-padding-right">调用值</label>
						<div class="col-sm-10">
							<input type="text" class="form-control" name="name" id="name" placeholder="仅限英文，要与支付文档一致">
						</div>
					</div>
					<div class="form-group">
						<label class="col-sm-2 control-label no-padding-right">显示名称</label>
						<div class="col-sm-10">
							<input type="text" class="form-control" name="showname" id="showname" placeholder="仅显示使用">
						</div>
					</div>
					<div class="form-group">
						<label class="col-sm-2 control-label">支持设备</label>
						<div class="col-sm-10">
							<select name="device" id="device" class="form-control">
								<option value="0">PC+Mobile</option>
								<option value="1">PC</option>
								<option value="2">Mobile</option>
							</select>
						</div>
					</div>
					<div class="form-group">
						<div class="col-sm-offset-2 col-sm-10">
							<p class="help-block" style="margin:0 0 4px;">收银台三级菜单（币种 → 网络 → 通道）依据下面 4 个字段分组与排序，留空时按「调用值」自动推导。</p>
						</div>
					</div>
					<div class="form-group">
						<label class="col-sm-2 control-label no-padding-right">币种分类</label>
						<div class="col-sm-10">
							<input type="text" class="form-control" name="currency" id="currency" placeholder="如 USDT/USDC/Alipay/WeChat，留空将自动从调用值推导" maxlength="30">
						</div>
					</div>
					<div class="form-group">
						<label class="col-sm-2 control-label no-padding-right">网络</label>
						<div class="col-sm-10">
							<input type="text" class="form-control" name="network" id="network" placeholder="加密货币填写链名（如 TRC20/ERC20/Polygon），法币留空" maxlength="30">
						</div>
					</div>
					<div class="row">
					<div class="col-sm-6">
					<div class="form-group">
						<label class="col-sm-4 control-label no-padding-right">币种排序</label>
						<div class="col-sm-8">
							<input type="number" class="form-control" name="currency_sort" id="currency_sort" placeholder="0=默认" min="0">
						</div>
					</div>
					</div><div class="col-sm-6">
					<div class="form-group">
						<label class="col-sm-4 control-label no-padding-right">网络排序</label>
						<div class="col-sm-8">
							<input type="number" class="form-control" name="network_sort" id="network_sort" placeholder="0=默认" min="0">
						</div>
					</div>
					</div>
					</div>
				</form>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-white" data-dismiss="modal">关闭</button>
				<button type="button" class="btn btn-primary" id="store" onclick="save()">保存</button>
			</div>
		</div>
	</div>
</div>

<div class="panel panel-info">
   <div class="panel-heading"><h3 class="panel-title">系统共有 <b><?php echo count($list);?></b> 个支付方式（按支付插件归类）&nbsp;<span class="pull-right"><a href="javascript:addframe()" class="btn btn-default btn-xs"><i class="fa fa-plus"></i> 新增</a></span></h3></div>
</div>
<?php
$tableHead = '<div class="table-responsive"><table class="table table-striped"><thead><tr><th>调用值</th><th>名称</th><th>币种 / 网络</th><th>支持设备</th><th>今日收款</th><th>状态</th><th>操作</th></tr></thead><tbody>';
$tableFoot = '</tbody></table></div>';
if(count($bucket_common) > 0){
	echo '<div class="panel panel-default type-plugin-panel"><div class="panel-heading"><strong>通用支付方式</strong>（被 2 个及以上支付插件声明）</div><div class="panel-body" style="padding:0">'.$tableHead;
	pay_type_render_rows($bucket_common);
	echo $tableFoot.'</div></div>';
}
foreach($bucket_by_plugin as $pack){
	$meta = $pack['meta'];
	$title = htmlspecialchars($meta['showname'], ENT_QUOTES, 'UTF-8').' <span class="text-muted">（'.htmlspecialchars($meta['name'], ENT_QUOTES, 'UTF-8').'）</span>';
	$bepusdtImport = '';
	if(($meta['name'] ?? '') === 'bepusdt'){
		$bepusdtImport = '<span class="pull-right" style="margin-top:-2px"><button type="button" class="btn btn-xs btn-primary" onclick="importBepusdtPayTypes()"><i class="fa fa-plus-circle"></i> 一键导入全部交易类型</button></span>';
	}
	echo '<div class="panel panel-default type-plugin-panel"><div class="panel-heading clearfix"><strong>'.$title.'</strong>'.$bepusdtImport.'</div><div class="panel-body" style="padding:0">'.$tableHead;
	pay_type_render_rows($pack['rows']);
	echo $tableFoot.'</div></div>';
}
if(count($bucket_other) > 0){
	echo '<div class="panel panel-default type-plugin-panel"><div class="panel-heading"><strong>其他</strong>（未在任何支付插件的声明类型中出现）</div><div class="panel-body" style="padding:0">'.$tableHead;
	pay_type_render_rows($bucket_other);
	echo $tableFoot.'</div></div>';
}
?>
    </div>
  </div>
<script src="<?php echo $cdnpublic?>layer/3.1.1/layer.js"></script>
<script>
function addframe(){
	$("#modal-store").modal('show');
	$("#modal-title").html("新增支付方式");
	$("#action").val("add");
	$("#id").val('');
	$("#name").val('');
	$("#showname").val('');
	$("#device").val(0);
	$("#currency").val('');
	$("#network").val('');
	$("#currency_sort").val(0);
	$("#network_sort").val(0);
}
function editframe(id){
	var ii = layer.load(2, {shade:[0.1,'#fff']});
	$.ajax({
		type : 'GET',
		url : 'ajax_pay.php?act=getPayType&id='+id,
		dataType : 'json',
		success : function(data) {
			layer.close(ii);
			if(data.code == 0){
				$("#modal-store").modal('show');
				$("#modal-title").html("修改支付方式");
				$("#action").val("edit");
				$("#id").val(data.data.id);
				$("#name").val(data.data.name);
				$("#showname").val(data.data.showname);
				$("#device").val(data.data.device);
				$("#currency").val(data.data.currency || '');
				$("#network").val(data.data.network || '');
				$("#currency_sort").val(data.data.currency_sort || 0);
				$("#network_sort").val(data.data.network_sort || 0);
			}else{
				layer.alert(data.msg, {icon: 2})
			}
		},
		error:function(data){
			layer.msg('服务器错误');
			return false;
		}
	});
}
function save(){
	if($("#name").val()==''||$("#showname").val()==''){
		layer.alert('请确保各项不能为空！');return false;
	}
	var ii = layer.load(2, {shade:[0.1,'#fff']});
	$.ajax({
		type : 'POST',
		url : 'ajax_pay.php?act=savePayType',
		data : $("#form-store").serialize(),
		dataType : 'json',
		success : function(data) {
			layer.close(ii);
			if(data.code == 0){
				layer.alert(data.msg,{
					icon: 1,
					closeBtn: false
				}, function(){
				  window.location.reload()
				});
			}else{
				layer.alert(data.msg, {icon: 2})
			}
		},
		error:function(data){
			layer.msg('服务器错误');
			return false;
		}
	});
}
function delItem(id) {
	if(id<4){
		layer.msg('系统自带支付方式暂不支持删除');
		return false;
	}
	var confirmobj = layer.confirm('你确实要删除此支付方式吗？', {
	  btn: ['确定','取消'], icon:0
	}, function(){
	  $.ajax({
		type : 'GET',
		url : 'ajax_pay.php?act=delPayType&id='+id,
		dataType : 'json',
		success : function(data) {
			if(data.code == 0){
				window.location.reload()
			}else{
				layer.alert(data.msg, {icon: 2});
			}
		},
		error:function(data){
			layer.msg('服务器错误');
			return false;
		}
	  });
	}, function(){
	  layer.close(confirmobj);
	});
}
function setStatus(id,status) {
	$.ajax({
		type : 'GET',
		url : 'ajax_pay.php?act=setPayType&id='+id+'&status='+status,
		dataType : 'json',
		success : function(data) {
			if(data.code == 0){
				window.location.reload()
			}else{
				layer.msg(data.msg, {icon:2, time:1500});
			}
		},
		error:function(data){
			layer.msg('服务器错误');
			return false;
		}
	});
}
function importBepusdtPayTypes(){
	layer.confirm('将按 BEpusdt 官方文档（trade_type）在数据库中批量注册支付方式；调用值 + 设备（PC+Mobile）已存在的条目将自动跳过。是否继续？', {
		btn: ['确定','取消'], title: '导入 BEpusdt 交易类型', icon: 3
	}, function(){
		var ii = layer.load(2, {shade:[0.1,'#fff']});
		$.ajax({
			type : 'GET',
			url : 'ajax_pay.php?act=importBepusdtPayTypes',
			dataType : 'json',
			success : function(data) {
				layer.close(ii);
				if(data.code == 0){
					layer.alert('导入完成：新增 <b>'+data.imported+'</b> 条，跳过（已存在）<b>'+data.skipped+'</b> 条；目录共 <b>'+data.total+'</b> 项。已同步刷新插件注册表。', {
						icon: 1,
						closeBtn: false
					}, function(){
						window.location.reload();
					});
				}else{
					layer.alert(data.msg || '导入失败', {icon: 2});
				}
			},
			error:function(){
				layer.close(ii);
				layer.msg('服务器错误');
			}
		});
	});
}
function getAll(type, typeid, obj){
	var ii = layer.load();
	$.ajax({
		type : 'GET',
		url : 'ajax_pay.php?act=getTypeMoney&type='+type+'&typeid='+typeid,
		dataType : 'json',
		success : function(data) {
			layer.close(ii);
			if(data.code == 0){
				$(obj).html(data.money);
			}else{
				layer.alert(data.msg, {icon: 2})
			}
		},
		error:function(data){
			layer.msg('服务器错误');
			return false;
		}
	});
}
</script>