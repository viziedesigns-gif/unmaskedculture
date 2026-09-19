<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
/** Run each case in a fresh process: php device_auth_test.php valid|tampered|expired|revoked|logout|issue */
require __DIR__ . '/../includes/device_auth.php';
session_start();
$case = $argv[1] ?? 'valid';
$selector = str_repeat('a',24);
$token = str_repeat('b',64);
$rows = [$selector => ['selector'=>$selector,'user_id'=>7,'token_hash'=>hash('sha256',$token),'auth_version'=>2,'current_auth_version'=>2,'expires_at'=>gmdate('Y-m-d H:i:s',time()+3600)]];
$_COOKIE[DEVICE_AUTH_COOKIE] = $selector.':'.$token;
function dbFetchOne($sql,$params) {
 global $rows;
 $row=$rows[$params[0]]??null;
 return $row && $row['expires_at']>gmdate('Y-m-d H:i:s') ? $row : null;
}
function dbQuery($sql,$params=[]) {
 global $rows;
 if (str_starts_with($sql,'DELETE FROM user_device_logins WHERE selector')) {
  if(isset($rows[$params[0]]) && $rows[$params[0]]['token_hash']===$params[1]) unset($rows[$params[0]]);
 }
 if (str_starts_with($sql,'INSERT')) $rows[$params[0]]=['selector'=>$params[0],'user_id'=>$params[1],'token_hash'=>$params[2],'auth_version'=>$params[3],'expires_at'=>$params[4]];
}
function check($value,$message) {if(!$value) throw new RuntimeException($message);}
if($case==='tampered') $_COOKIE[DEVICE_AUTH_COOKIE]=$selector.':'.str_repeat('c',64);
if($case==='expired') $rows[$selector]['expires_at']='2000-01-01 00:00:00';
if($case==='revoked') $rows[$selector]['current_auth_version']=3;
if($case==='logout') {
 forgetDeviceLogin(); check(!$rows && !isset($_COOKIE[DEVICE_AUTH_COOKIE]),'Sign-out must revoke this device');
} elseif($case==='issue') {
 rememberDeviceLogin(7,2); $parts=deviceCookieParts();
 check($parts!==null && count($rows)===1,'Credential issuance failed');
 check($rows[$parts[0]]['token_hash']===hash('sha256',$parts[1]),'Only a hash may be stored');
 check($parts[1]!==$token,'New credential must be random');
} else {
 restoreDeviceLogin();
 check(($case==='valid') === (($_SESSION['user_id']??0)===7),'Restoration outcome wrong');
 if($case==='valid') check($_SESSION['auth_version']===2,'Auth version must be restored');
}
echo "PASS $case\n";
