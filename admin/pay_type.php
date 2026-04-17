<?php
/**
 * 支付方式
**/
include("../includes/common.php");
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
		$iconName = $res['name'];
		echo '<tr><td><b>'.$nameDisp.'</b></td><td><img src="/assets/icon/'.$iconName.'.ico" class="type-logo" onerror="this.style.display=\'none\'">'.$shownameDisp.$pluginNote.'</td><td>'.display_device($res['device']).'</td><td><a onclick="getAll(0,'.$res['id'].',this)" title="点此获取最新数据">[刷新]</a></td><td>'.($res['status']==1?'<a class="btn btn-xs btn-success" onclick="setStatus('.$res['id'].',0)">已开启</a>':'<a class="btn btn-xs btn-warning" onclick="setStatus('.$res['id'].',1)">已关闭</a>').'</td><td><a class="btn btn-xs btn-info" onclick="editframe('.$res['id'].')">编辑</a>&nbsp;<a class="btn btn-xs btn-danger" onclick="delItem('.$res['id'].')">删除</a>&nbsp;<a href="./order.php?type='.$res['id'].'" target="_blank" class="btn btn-xs btn-default">订单</a></td></tr>';
	}
}

$list = $DB->getAll("SELECT * FROM pre_type ORDER BY id ASC");
$plugins = \lib\Plugin::getAll();
$typeToPlugins = [];
foreach($plugins as $p){
	$typesStr = isset($p['types']) ? (string)$p['types'] : '';
	$tokens = array_unique(array_filter(array_map('trim', explode(',', $typesStr))));
	foreach($tokens as $t){
		if($t === '')continue;
		if(!isset($typeToPlugins[$t]))$typeToPlugins[$t] = [];
		$typeToPlugins[$t][$p['name']] = [
			'name' => $p['name'],
			'showname' => ($p['showname'] !== null && $p['showname'] !== '') ? $p['showname'] : $p['name'],
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
$tableHead = '<div class="table-responsive"><table class="table table-striped"><thead><tr><th>调用值</th><th>名称</th><th>支持设备</th><th>今日收款</th><th>状态</th><th>操作</th></tr></thead><tbody>';
$tableFoot = '</tbody></table></div>';
if(count($bucket_common) > 0){
	echo '<div class="panel panel-default type-plugin-panel"><div class="panel-heading"><strong>通用支付方式</strong>（被 2 个及以上支付插件声明）</div><div class="panel-body" style="padding:0">'.$tableHead;
	pay_type_render_rows($bucket_common);
	echo $tableFoot.'</div></div>';
}
foreach($bucket_by_plugin as $pack){
	$meta = $pack['meta'];
	$title = htmlspecialchars($meta['showname'], ENT_QUOTES, 'UTF-8').' <span class="text-muted">（'.htmlspecialchars($meta['name'], ENT_QUOTES, 'UTF-8').'）</span>';
	echo '<div class="panel panel-default type-plugin-panel"><div class="panel-heading"><strong>'.$title.'</strong></div><div class="panel-body" style="padding:0">'.$tableHead;
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