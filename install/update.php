<?php
error_reporting(0);
define('IN_DB_UPGRADE', true);
require '../includes/common.php';

@header('Content-Type: text/html; charset=UTF-8');

if(!isset($islogin) || $islogin !== 1){
	http_response_code(403);
	exit('请先登录管理后台，再执行数据库升级。');
}

if($_SERVER['REQUEST_METHOD'] !== 'POST'){
	$_SESSION['db_upgrade_token'] = bin2hex(random_bytes(32));
	$token = htmlspecialchars($_SESSION['db_upgrade_token'], ENT_QUOTES, 'UTF-8');
	echo '<h2>数据库升级确认</h2>';
	echo '<p>升级前请先备份数据库。此操作仅允许已登录的管理员执行。</p>';
	echo '<form method="post">';
	echo '<input type="hidden" name="token" value="'.$token.'">';
	echo '<button type="submit">确认执行升级</button>';
	echo '</form>';
	exit;
}

$submittedToken = isset($_POST['token']) ? (string)$_POST['token'] : '';
$sessionToken = isset($_SESSION['db_upgrade_token']) ? (string)$_SESSION['db_upgrade_token'] : '';
if($sessionToken === '' || !hash_equals($sessionToken, $submittedToken)){
	http_response_code(403);
	exit('升级请求校验失败，请返回升级页面后重试。');
}
unset($_SESSION['db_upgrade_token']);

try{
	$db=new PDO("mysql:host=".$dbconfig['host'].";dbname=".$dbconfig['dbname'].";port=".$dbconfig['port'],$dbconfig['user'],$dbconfig['pwd']);
}catch(Exception $e){
	exit('连接数据库失败:'.htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
}
date_default_timezone_set("PRC");
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_SILENT);
$db->exec("set sql_mode = ''");
$db->exec("set names utf8");

$configTable = '`'.$dbconfig['dbqz'].'_config`';
$version = 0;
if($rs = $db->query("SELECT v FROM {$configTable} WHERE k='version' LIMIT 1")){
	$version = (int)$rs->fetchColumn();
}

if($version === (int)DB_VERSION){
	exit('你的网站已经升级到最新版本了');
}
if($version > (int)DB_VERSION){
	exit('数据库版本不兼容，请使用与程序匹配的数据库！');
}

$migrationFiles = [];
if($version < 2044) $migrationFiles[] = 'update2.sql';
if($version < 2055) $migrationFiles[] = 'update3.sql';
if($version < 2056) $migrationFiles[] = 'update4.sql';
if($version < 2057) $migrationFiles[] = 'update5.sql';
if($version < 2058) $migrationFiles[] = 'update6.sql';

$success = 0;
foreach($migrationFiles as $migrationFile){
	$sql = file_get_contents(__DIR__.'/'.$migrationFile);
	if($sql === false){
		exit('读取升级文件失败：'.htmlspecialchars($migrationFile, ENT_QUOTES, 'UTF-8'));
	}
	foreach(explode(';', $sql) as $statement){
		$statement = trim($statement);
		if($statement === '') continue;
		$statement = str_replace('pre_', $dbconfig['dbqz'].'_', $statement);
		if($db->exec($statement) === false){
			$error = $db->errorInfo();
			$message = isset($error[2]) ? $error[2] : '未知数据库错误';
			exit(
				'数据库升级失败，版本号未更新。文件：'.
				htmlspecialchars($migrationFile, ENT_QUOTES, 'UTF-8').
				'；错误：'.htmlspecialchars($message, ENT_QUOTES, 'UTF-8')
			);
		}
		$success++;
	}
}

$versionSql = "UPDATE {$configTable} SET `v`=:version WHERE `k`='version'";
$statement = $db->prepare($versionSql);
if(!$statement || !$statement->execute([':version'=>DB_VERSION])){
	$error = $statement ? $statement->errorInfo() : $db->errorInfo();
	$message = isset($error[2]) ? $error[2] : '未知数据库错误';
	exit('数据库结构已升级，但版本号写入失败：'.htmlspecialchars($message, ENT_QUOTES, 'UTF-8'));
}

$cacheTable = '`'.$dbconfig['dbqz'].'_cache`';
$db->exec("UPDATE {$cacheTable} SET `v`='' WHERE `k`='config'");

echo '数据库已升级到 '.DB_VERSION.'，成功执行SQL语句'.$success.'条！<br/>';
echo '<hr/><a href="/">点此返回首页</a>';
?>
