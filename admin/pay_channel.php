<?php
/**
 * 支付通道
**/
include("../includes/common.php");
$title='支付通道';
include './head.php';
if($islogin==1){}else exit("<script language='javascript'>window.location.href='./login.php';</script>");

$type_select = '<option value="0">所有支付方式</option>';
$type_select2 = '';
$rs = $DB->getAll("SELECT * FROM pre_type ORDER BY id ASC");
foreach($rs as $row){
	$type_select .= '<option value="'.$row['id'].'">'.$row['showname'].'</option>';
	$type_select2 .= '<option value="'.$row['id'].'">'.$row['showname'].'</option>';
}
unset($rs);
?>
<style>
.form-inline .form-control {
    display: inline-block;
    width: auto;
    vertical-align: middle;
}
.form-inline .form-group {
    display: inline-block;
    margin-bottom: 0;
    vertical-align: middle;
}
.type-logo{width: 18px;margin-top: -2px;padding-right: 4px;}
.channel-plugin-panel { margin-bottom: 20px; }
.channel-plugin-panel .panel-heading { font-size: 14px; }
.channel-plugin-panel .panel-body .fixed-table-toolbar { padding: 8px 10px 0; }
</style>

<div class="modal" id="modal-store" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true" data-backdrop="static">
	<div class="modal-dialog">
		<div class="modal-content animated flipInX">
			<div class="modal-header">
				<button type="button" class="close" data-dismiss="modal"><span
							aria-hidden="true">&times;</span><span
							class="sr-only">Close</span></button>
				<h4 class="modal-title" id="modal-title">支付通道修改/添加</h4>
			</div>
			<div class="modal-body">
				<form class="form-horizontal" id="form-store">
					<input type="hidden" name="action" id="action"/>
					<input type="hidden" name="id" id="id"/>
					<div class="form-group">
						<label class="col-sm-2 control-label no-padding-right">显示名称</label>
						<div class="col-sm-10">
							<input type="text" class="form-control" name="name" id="name" placeholder="仅显示使用，不要与其他通道名称重复">
						</div>
					</div>
					<div class="form-group">
						<label class="col-sm-2 control-label no-padding-right">前台显示名称</label>
						<div class="col-sm-10">
							<input type="text" class="form-control" name="front_showname" id="front_showname" placeholder="收银台列表主文案；留空则沿用支付方式名称" maxlength="64">
						</div>
					</div>
					<div class="form-group">
						<label class="col-sm-2 control-label no-padding-right">分成比例</label>
						<div class="col-sm-10">
							<div class="input-group"><input type="text" class="form-control" name="rate" id="rate" placeholder="在没配置用户组的情况下以此费率为准" title="是指给商户的分成比例"><span class="input-group-addon">%</span></div>
						</div>
					</div>
					<div class="form-group">
						<label class="col-sm-2 control-label no-padding-right">通道成本</label>
						<div class="col-sm-10">
							<div class="input-group"><input type="text" class="form-control" name="costrate" id="costrate" placeholder="仅用于统计利润，可留空，默认为0"><span class="input-group-addon">%</span></div>
						</div>
					</div>
					<div class="form-group">
						<label class="col-sm-2 control-label">通道模式</label>
						<div class="col-sm-10">
							<div class="input-group"><select name="mode" id="mode" class="form-control" onchange="changeMode()">
								<option value="0">支付金额扣除手续费后加入商户余额（默认）</option><option value="1">支付完成后不给商户加余额，同时需扣手续费</option>
							</select><a tabindex="0" class="input-group-addon" role="button" data-toggle="popover" data-trigger="focus" title="通道模式说明" data-placement="bottom" data-content="【第一种模式】资金由平台代收，然后结算给商户，手续费从每笔订单直接扣除 【第二种模式】资金由上游直接结算给商户，手续费从商户余额扣除，商户需先充值余额否则无法支付"><span class="glyphicon glyphicon-info-sign"></span></a></div>
						</div>
					</div>
					<div class="form-group">
						<label class="col-sm-2 control-label">支付方式</label>
						<div class="col-sm-10">
							<select name="type" id="type" class="form-control" onchange="changeType()">
								<option value="0">请选择支付方式</option><?php echo $type_select2; ?>
							</select>
						</div>
					</div>
					<div class="form-group">
						<label class="col-sm-2 control-label">支付插件</label>
						<div class="col-sm-10">
							<select name="plugin" id="plugin" class="form-control">
							</select>
						</div>
					</div>
					<div class="form-group">
						<label class="col-sm-2 control-label no-padding-right">单日限额</label>
						<div class="col-sm-10">
							<input type="text" class="form-control" name="daytop" id="daytop" placeholder="0或留空为没有单日限额，超出限额会暂停使用该通道" title="修改后第二天生效">
						</div>
					</div>
					<div class="form-group">
						<label class="col-sm-2 control-label no-padding-right">单日限笔</label>
						<div class="col-sm-10">
							<input type="text" class="form-control" name="daymaxorder" id="daymaxorder" placeholder="0或留空为没有单日支付订单数量限制">
						</div>
					</div>
					<div class="row">
					<div class="col-sm-6">
					<div class="form-group">
						<label class="col-sm-4 control-label no-padding-right">单笔最小</label>
						<div class="col-sm-8">
							<input type="text" class="form-control" name="paymin" id="paymin" placeholder="留空无单笔最小限额">
						</div>
					</div>
					</div><div class="col-sm-6">
					<div class="form-group">
						<label class="col-sm-4 control-label no-padding-right">单笔最大</label>
						<div class="col-sm-8">
							<input type="text" class="form-control" name="paymax" id="paymax" placeholder="留空无单笔最大限额">
						</div>
					</div>
					</div>
					</div>
					<div class="form-group">
						<label class="col-sm-2 control-label no-padding-right">开放时间</label>
						<div class="col-sm-5">
							<input type="text" class="form-control" name="timestart" id="timestart" placeholder="开始时间0~23小时" title="">
						</div>
						<div class="col-sm-5">
							<input type="text" class="form-control" name="timestop" id="timestop" placeholder="结束时间0~23小时" title="">
						</div>
					</div>
					<div class="form-group">
						<label class="col-sm-2 control-label">收银台</label>
						<div class="col-sm-10">
							<select name="cashier_ok" id="cashier_ok" class="form-control">
								<option value="1">可用（顾客可从收银台入口下单）</option>
								<option value="0">不可用（收银台仅展示为不可用；API/轮询等未带收银台参数时仍可选中）</option>
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

  <div class="container" style="padding-top:70px;">
  <div class="row">
    <div class="col-md-12 center-block" style="float: none;">
<form onsubmit="return searchSubmit()" method="GET" class="form-inline" id="searchToolbar">
<input type="hidden" class="form-control" name="id">
<input type="hidden" class="form-control" name="batch">
  <div class="form-group">
	<label>搜索</label>
    <input type="text" class="form-control" name="kw" placeholder="通道ID/名称">
  </div>
  <div class="form-group">
    <input type="text" class="form-control" name="plugin" style="width: 100px;" placeholder="支付插件" value="">
  </div>
  <div class="form-group">
    <select name="type" class="form-control"><?php echo $type_select?></select>
  </div>
  <div class="form-group">
	<select name="dstatus" class="form-control"><option value="-1">全部状态</option><option value="1">状态已开启</option><option value="0">状态已关闭</option></select>
  </div>
  <div class="form-group">
	<select name="cash_filter" class="form-control"><option value="-1">收银台全部</option><option value="1">收银台可用</option><option value="0">收银台不可用</option></select>
  </div>
  <button type="submit" class="btn btn-primary"><i class="fa fa-search"></i> 搜索</button>
  <a href="javascript:searchClear()" class="btn btn-default"><i class="fa fa-refresh"></i> 重置</a>
  <a href="javascript:openBepusdtBatchImport()" class="btn btn-info"><i class="fa fa-upload"></i> 批量导入 BEpusdt 通道</a>
  <a href="javascript:addframe()" class="btn btn-success"><i class="fa fa-plus"></i> 新增</a>
</form>

<div id="channelTablesContainer"></div>

    </div>
  </div>
</div>
<script src="<?php echo $cdnpublic?>layer/3.1.1/layer.js"></script>
<script src="../assets/js/bootstrap-table.min.js"></script>
<script src="../assets/js/bootstrap-table-page-jump-to.min.js"></script>
<script src="../assets/js/custom.js"></script>
<script>
function channelToolbarPostData() {
	var params = {};
	$('#searchToolbar').find(':input[name]').each(function() {
		var n = $(this).attr('name');
		if (n) params[n] = $(this).val();
	});
	return params;
}
function getChannelTableColumns() {
	return [
		{
			field: 'id',
			title: 'ID',
			formatter: function(value, row, index) {
				return '<b>'+value+'</b>';
			}
		},
		{
			field: 'name',
			title: '显示名称'
		},
		{
			field: 'mode',
			title: '通道模式',
			formatter: function(value, row, index) {
				if(value == '1'){
					return '商户直清'
				}else{
					return '平台代收'
				}
			}
		},
		{
			field: 'rate',
			title: '分成比例'
		},
		{
			field: 'cashier_ok',
			title: '收银台',
			formatter: function(value, row, index) {
				if(value == '0' || value === 0){
					return '<a class="btn btn-xs btn-warning" onclick="setCashierOk('+row.id+',1)" title="点击改为收银台可用">不可用</a>';
				}
				return '<a class="btn btn-xs btn-success" onclick="setCashierOk('+row.id+',0)" title="点击改为收银台不可用">可用</a>';
			}
		},
		{
			field: 'type',
			title: '支付方式',
			formatter: function(value, row, index) {
				return (typeof payTypeIconHtml === 'function' ? payTypeIconHtml(row.typename, 'type-logo') : '<img src="/assets/icon/'+row.typename+'.ico" class="type-logo" onerror="this.style.display=\'none\'">')+row.typeshowname;
			}
		},
		{
			field: 'plugin',
			title: '支付插件',
			visible: false,
			formatter: function(value, row, index) {
				return '<span onclick="showPlugin(\''+value+'\')" title="查看支付插件详情">'+value+'</span>';
			}
		},
		{
			field: '',
			title: '今日笔数',
			visible: false,
			formatter: function(value, row, index) {
				return '<a onclick="getAll(2,'+row.id+',this)" title="点此获取最新数据">[刷新]</a>';
			}
		},
		{
			field: '',
			title: '昨日笔数',
			visible: false,
			formatter: function(value, row, index) {
				return '<a onclick="getAll(3,'+row.id+',this)" title="点此获取最新数据">[刷新]</a>';
			}
		},
		{
			field: '',
			title: '今日收款',
			formatter: function(value, row, index) {
				return '<a onclick="getAll(0,'+row.id+',this)" title="点此获取最新数据">[刷新]</a>';
			}
		},
		{
			field: '',
			title: '昨日收款',
			formatter: function(value, row, index) {
				return '<a onclick="getAll(1,'+row.id+',this)" title="点此获取最新数据">[刷新]</a>';
			}
		},
		{
			field: '',
			title: '订单成功率',
			visible: false,
			formatter: function(value, row, index) {
				return '<a onclick="getSuccessRate(' + row.id +',this)" title="点此获取最新数据">[刷新]</a>';
			}
		},
		{
			field: 'status',
			title: '状态',
			formatter: function(value, row, index) {
				if(value == '1'){
					return '<a class="btn btn-xs btn-success" onclick="setStatus('+row.id+',0)">已开启</a>';
				}else{
					return '<a class="btn btn-xs btn-warning" onclick="setStatus('+row.id+',1)">已关闭</a>';
				}
			}
		},
		{
			field: '',
			title: '操作',
			formatter: function(value, row, index) {
				return '<a class="btn btn-xs btn-primary" onclick="editInfo('+row.id+')">配置密钥</a>&nbsp;<a class="btn btn-xs btn-info" onclick="editframe('+row.id+')">编辑</a>&nbsp;<a class="btn btn-xs btn-danger" onclick="delItem('+row.id+')">删除</a>&nbsp;<a href="./order.php?channel='+row.id+'" target="_blank" class="btn btn-xs btn-default">订单</a>&nbsp;<a onclick="copyframe('+row.id+')" class="btn btn-xs btn-default"><i class="fa fa-copy"></i></a>&nbsp;<a onclick="testpay('+row.id+')" class="btn btn-xs btn-default">测试</a>';
			}
		},
	];
}
function destroyChannelTables() {
	$('#channelTablesContainer').find('table[data-channel-group-table="1"]').each(function() {
		var $t = $(this);
		if ($t.data('bootstrap.table')) {
			$t.bootstrapTable('destroy');
		}
	});
	$('#channelTablesContainer').empty();
}
function loadChannelGroupedTables() {
	var postData = channelToolbarPostData();
	var ii = layer.load(1, {shade:[0.05,'#fff']});
	$.ajax({
		type: 'POST',
		url: 'ajax_pay.php?act=channelList',
		data: postData,
		dataType: 'json',
		success: function(list) {
			layer.close(ii);
			if (!$.isArray(list)) {
				layer.msg('加载失败', {icon:2, time:1500});
				return;
			}
			destroyChannelTables();
			if (list.length === 0) {
				$('#channelTablesContainer').html('<div class="alert alert-info" style="margin-top:10px">当前筛选条件下暂无支付通道。</div>');
				return;
			}
			var groups = {};
			for (var i = 0; i < list.length; i++) {
				var p = list[i].plugin != null ? String(list[i].plugin) : '';
				if (!groups[p]) groups[p] = [];
				groups[p].push(list[i]);
			}
			var keys = Object.keys(groups);
			keys.sort(function(a, b) {
				if (a === '' && b !== '') return 1;
				if (b === '' && a !== '') return -1;
				return a.localeCompare(b);
			});
			for (var k = 0; k < keys.length; k++) {
				var pluginKey = keys[k];
				var rows = groups[pluginKey];
				var shown = pluginKey === '' ? '（未绑定插件）' : pluginKey;
				var safeTitle = $('<div/>').text(shown).html();
				var panelHtml = '<div class="channel-plugin-panel panel panel-default">' +
					'<div class="panel-heading"><strong>支付插件</strong>：' + safeTitle +
					' <span class="text-muted">（共 '+rows.length+' 条）</span></div>' +
					'<div class="panel-body" style="padding:0">' +
					'<table data-channel-group-table="1" id="channelTable_'+k+'"></table>' +
					'</div></div>';
				$('#channelTablesContainer').append(panelHtml);
				$('#channelTable_'+k).bootstrapTable({
					data: rows,
					pageNumber: 1,
					pageSize: 15,
					sidePagination: 'client',
					sortable: false,
					classes: 'table table-striped table-hover table-bordered',
					columns: getChannelTableColumns(),
					toolbar: false,
					showColumns: true,
					showFullscreen: true,
					search: false
				});
			}
		},
		error: function() {
			layer.close(ii);
			layer.msg('服务器错误');
		}
	});
}
$(document).ready(function(){
	updateToolbar();
	loadChannelGroupedTables();
})

function changeType(plugin){
	plugin = plugin || null;
	if(plugin == null){
		plugin = $("#plugin").val();
	}
	var typeid = $("#type").val();
	if(typeid==0)return;
	$("#plugin").empty();
	$.ajax({
		type : 'GET',
		url : 'ajax_pay.php?act=getPlugins&typeid='+typeid,
		dataType : 'json',
		success : function(data) {
			if(data.code == 0){
				$.each(data.data, function (i, res) {
					$("#plugin").append('<option value="'+res.name+'">'+res.showname+'</option>');
				})
				if(plugin!=null)$("#plugin").val(plugin);
			}else{
				layer.msg(data.msg, {icon:2, time:1500})
			}
		},
		error:function(data){
			layer.msg('服务器错误');
			return false;
		}
	});
}
function changeMode(){
	var mode = parseInt($("#mode").val());
}
function addframe(){
	$("#modal-store").modal('show');
	$("#modal-title").html("新增支付通道");
	$("#action").val("add");
	$("#id").val('');
	$("#name").val('');
	$("#rate").val('');
	$("#costrate").val('');
	$("#type").val(0);
	$("#daytop").val('');
	$("#daymaxorder").val('');
	$("#paymin").val('');
	$("#paymax").val('');
	$("#plugin").empty();
	$("#timestart").val('');
	$("#timestop").val('');
	$("#cashier_ok").val('1');
	$("#front_showname").val('');
}
function editframe(id){
	var ii = layer.load(2, {shade:[0.1,'#fff']});
	$.ajax({
		type : 'GET',
		url : 'ajax_pay.php?act=getChannel&id='+id,
		dataType : 'json',
		success : function(data) {
			layer.close(ii);
			if(data.code == 0){
				$("#modal-store").modal('show');
				$("#modal-title").html("修改支付通道");
				$("#action").val("edit");
				$("#id").val(data.data.id);
				$("#name").val(data.data.name);
				$("#front_showname").val(data.data.front_showname != null ? data.data.front_showname : '');
				$("#rate").val(data.data.rate);
				$("#costrate").val(data.data.costrate);
				$("#type").val(data.data.type);
				$("#daytop").val(data.data.daytop);
				$("#daymaxorder").val(data.data.daymaxorder);
				$("#paymin").val(data.data.paymin);
				$("#paymax").val(data.data.paymax);
				$("#mode").val(data.data.mode);
				$("#timestart").val(data.data.timestart);
				$("#timestop").val(data.data.timestop);
				$("#cashier_ok").val((data.data.cashier_ok === 0 || data.data.cashier_ok === '0') ? '0' : '1');
				changeType(data.data.plugin);
				changeMode()
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
function copyframe(id){
	var ii = layer.load(2, {shade:[0.1,'#fff']});
	$.ajax({
		type : 'GET',
		url : 'ajax_pay.php?act=getChannel&id='+id,
		dataType : 'json',
		success : function(data) {
			layer.close(ii);
			if(data.code == 0){
				$("#modal-store").modal('show');
				$("#modal-title").html("快速复制支付通道");
				$("#action").val("copy");
				$("#id").val(data.data.id);
				$("#name").val(data.data.name);
				$("#front_showname").val(data.data.front_showname != null ? data.data.front_showname : '');
				$("#rate").val(data.data.rate);
				$("#costrate").val(data.data.costrate);
				$("#type").val(data.data.type);
				$("#daytop").val(data.data.daytop);
				$("#daymaxorder").val(data.data.daymaxorder);
				$("#paymin").val(data.data.paymin);
				$("#paymax").val(data.data.paymax);
				$("#mode").val(data.data.mode);
				$("#timestart").val(data.data.timestart);
				$("#timestop").val(data.data.timestop);
				$("#cashier_ok").val((data.data.cashier_ok === 0 || data.data.cashier_ok === '0') ? '0' : '1');
				changeType(data.data.plugin);
				changeMode()
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
	if($("#name").val()==''||$("#rate").val()==''){
		layer.alert('请确保各项不能为空！');return false;
	}
	if($("#type").val()==0){
		layer.alert('请选择支付方式！');return false;
	}
	if($("#plugin").val()==0 || $("#plugin").val()==null){
		layer.alert('请选择支付插件！');return false;
	}
	var ii = layer.load(2, {shade:[0.1,'#fff']});
	$.ajax({
		type : 'POST',
		url : 'ajax_pay.php?act=saveChannel',
		data : $("#form-store").serialize(),
		dataType : 'json',
		success : function(data) {
			layer.close(ii);
			if(data.code == 0){
				layer.alert(data.msg,{
					icon: 1,
					closeBtn: false
				}, function(){
					layer.closeAll();
					$("#modal-store").modal('hide');
					searchSubmit();
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
	var confirmobj = layer.confirm('你确实要删除此支付通道吗？', {
	  btn: ['确定','取消'], icon:0
	}, function(){
	  $.ajax({
		type : 'GET',
		url : 'ajax_pay.php?act=delChannel&id='+id,
		dataType : 'json',
		success : function(data) {
			if(data.code == 0){
				layer.closeAll();
				searchSubmit();
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
		url : 'ajax_pay.php?act=setChannel&id='+id+'&status='+status,
		dataType : 'json',
		success : function(data) {
			if(data.code == 0){
				searchSubmit();
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
function setCashierOk(id, ok) {
	$.ajax({
		type : 'GET',
		url : 'ajax_pay.php?act=setChannelCashierOk&id='+id+'&ok='+ok,
		dataType : 'json',
		success : function(data) {
			if(data.code == 0){
				searchSubmit();
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
function editInfo(id){
	var ii = layer.load(2, {shade:[0.1,'#fff']});
	$.ajax({
		type : 'GET',
		url : 'ajax_pay.php?act=channelInfo&id='+id,
		dataType : 'json',
		success : function(data) {
			layer.close(ii);
			if(data.code == 0){
				var area = [$(window).width() > 520 ? '520px' : '100%', ';max-height:100%'];
				layer.open({
				  type: 1,
				  area: area,
				  title: '配置对接密钥',
				  skin: 'layui-layer-rim',
				  content: data.data
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
function saveInfo(id){
	var ii = layer.load(2, {shade:[0.1,'#fff']});
	$.ajax({
		type : 'POST',
		url : 'ajax_pay.php?act=saveChannelInfo&id='+id,
		data : $("#form-info").serialize(),
		dataType : 'json',
		success : function(data) {
			layer.close(ii);
			if(data.code == 0){
				layer.alert(data.msg,{
					icon: 1,
					closeBtn: false
				}, function(){
					layer.closeAll();
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
function showPlugin(name){
	var ii = layer.load(2, {shade:[0.1,'#fff']});
	$.ajax({
		type : 'GET',
		url : 'ajax_pay.php?act=getPlugin&name='+name,
		dataType : 'json',
		success : function(data) {
			layer.close(ii);
			if(data.code == 0){
				var item = '<table class="table table-condensed table-hover">';
				item += '<tr><td class="info">插件名称</td><td colspan="5">'+data.data.name+'</td></tr><tr><td class="info">插件描述</td><td colspan="5">'+data.data.showname+'</td></tr><tr><td class="info">插件网址</td><td colspan="5">'+(data.data.link?'<a href="'+data.data.link+'" target="_blank" rel="noreferrer">'+data.data.author+'</a>':data.data.author)+'</td></tr><tr><td class="info">插件路径</td><td colspan="5">/plugins/'+data.data.name+'/</td></tr>';
				item += '</table>';
				layer.open({
				  type: 1,
				  shadeClose: true,
				  title: '支付插件详情',
				  content: item
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
function getAll(type, channel, obj){
	var ii = layer.load();
	$.ajax({
		type : 'GET',
		url : 'ajax_pay.php?act=getChannelMoney&type='+type+'&channel='+channel,
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
function getSuccessRate(channel, obj) {
    var ii = layer.load();
    $.ajax({
        type: 'GET',
        url: 'ajax_pay.php?act=getSuccessRate&channel=' + channel,
        dataType: 'json',
        success: function(data) {
            layer.close(ii);
            if (data.code == 0) {
                $(obj).html(data.data);
            } else {
                layer.alert(data.msg, {icon: 2})
            }
        },
        error: function(data) {
            layer.msg('服务器错误');
            return false;
        }
    });
}
function testpay(id) {
	var ii = layer.open({
		area: ['360px'],
		title: '测试支付',
		content: '<div class="form-group"><div class="input-group"><span class="input-group-addon"><span class="glyphicon glyphicon-shopping-cart"></span></span><input class="form-control" placeholder="订单名称" value="支付测试" name="test_name" type="text"></div></div><div class="form-group"><div class="input-group"><span class="input-group-addon"><span class="glyphicon glyphicon-yen"></span></span><input class="form-control" placeholder="订单金额" value="1" name="test_money" type="text"></div></div>',
		yes: function(){
			var name = $("input[name='test_name']").val();
			var money = $("input[name='test_money']").val();
			$.ajax({
				type : 'POST',
				url : 'ajax_pay.php?act=testpay',
				data : {channel:id, name:name, money:money},
				dataType : 'json',
				success : function(data) {
					if(data.code == 0){
						layer.close(ii);
						window.open(data.url);
					}else{
						layer.alert(data.msg, {icon:2});
					}
				},
				error:function(data){
					layer.msg('服务器错误');
					return false;
				}
			});
		}
	});
}
function searchSubmit() {
	loadChannelGroupedTables();
	return false;
}
function searchClear() {
	$('#searchToolbar').find('input[name]').each(function() {
		$(this).val('');
	});
	$('#searchToolbar').find('select[name]').each(function() {
		$(this).find('option:first').prop("selected", 'selected');
	});
	loadChannelGroupedTables();
}
$(function () {
  $('[data-toggle="popover"]').popover()
})

function openBepusdtBatchImport(){
	var sample = JSON.stringify([
		{
			"name":"BEpusdt-USDT-TRC20-1",
			"type":"usdt.trc20",
			"rate":"100",
			"costrate":"",
			"mode":0,
			"daytop":0,
			"daymaxorder":0,
			"paymin":"",
			"paymax":"",
			"timestart":"",
			"timestop":"",
			"config":{
				"appurl":"https://bepusdt.example.com/",
				"appkey":"your-token-here",
				"address":"",
				"timeout":"1200",
				"rate":""
			}
		}
	], null, 2);
	var content = '<div style="padding:15px 15px 5px 15px;">'
		+ '<div class="alert alert-info" style="margin-bottom:10px;line-height:1.7">'
		+ '<b>说明：</b>粘贴 JSON 数组，每个对象生成 1 条支付通道（插件固定为 <code>bepusdt</code>）。<br>'
		+ '<b>去重规则：</b>通道显示名称 <code>name</code> 已存在则跳过。<br>'
		+ '<b>type</b> 填支付方式调用值（如 <code>usdt.trc20</code>），需已在“支付方式”中存在。'
		+ '</div>'
		+ '<textarea id="bepusdtImportJson" class="form-control" rows="16" style="font-family:monospace"></textarea>'
		+ '<div style="margin-top:10px" class="text-right">'
		+ '<button type="button" class="btn btn-default" onclick="$(\'#bepusdtImportJson\').val(sampleBepusdtImportJson)">填充示例</button> '
		+ '<button type="button" class="btn btn-primary" onclick="doBepusdtBatchImport()">开始导入</button>'
		+ '</div>'
		+ '</div>';

	window.sampleBepusdtImportJson = sample;
	var area = [$(window).width() > 820 ? '820px' : '100%', $(window).height() > 720 ? '640px' : '100%'];
	layer.open({
		type: 1,
		area: area,
		title: '批量导入 BEpusdt 支付通道',
		shadeClose: true,
		content: content,
		success: function(){
			$('#bepusdtImportJson').val(sample);
		}
	});
}

function doBepusdtBatchImport(){
	var raw = ($('#bepusdtImportJson').val() || '').trim();
	if(!raw){
		layer.alert('请粘贴 JSON 内容', {icon:2});
		return;
	}
	var payload = null;
	try{
		payload = JSON.parse(raw);
	}catch(e){
		layer.alert('JSON 解析失败：' + (e && e.message ? e.message : '未知错误'), {icon:2});
		return;
	}
	if(!$.isArray(payload)){
		layer.alert('格式错误：根节点必须是 JSON 数组', {icon:2});
		return;
	}
	var ii = layer.load(2, {shade:[0.1,'#fff']});
	$.ajax({
		type: 'POST',
		url: 'ajax_pay.php?act=importBepusdtChannels',
		data: {list: raw},
		dataType: 'json',
		success: function(data){
			layer.close(ii);
			if(data && data.code === 0){
				var msg = '导入完成：新增 <b>'+data.imported+'</b> 条，跳过（已存在）<b>'+data.skipped+'</b> 条';
				if(data.failed > 0){
					msg += '，失败 <b>'+data.failed+'</b> 条';
				}
				if(data.errors && data.errors.length){
					msg += '<br><br><b>失败原因（最多展示前 10 条）：</b><br>' + data.errors.join('<br>');
				}
				layer.alert(msg, {icon:1, closeBtn:false}, function(){
					layer.closeAll();
					loadChannelGroupedTables();
				});
			}else{
				layer.alert((data && data.msg) ? data.msg : '导入失败', {icon:2});
			}
		},
		error: function(){
			layer.close(ii);
			layer.msg('服务器错误');
		}
	});
}
</script>
