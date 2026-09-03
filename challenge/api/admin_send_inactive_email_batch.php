<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/mail_service.php';
if (!isLoggedIn() || !isCurrentUserSuperAdmin()) jsonResponse(['success'=>false,'error'=>'Not authorized'],403);
if ($_SERVER['REQUEST_METHOD']!=='POST') jsonResponse(['success'=>false,'error'=>'Method not allowed'],405);
$payload=json_decode(file_get_contents('php://input'),true)?:[];
if(!validAdminCsrf($payload['csrf_token']??null))jsonResponse(['success'=>false,'error'=>'Session expired'],403);
if((int)($_SESSION['inactive_email_campaign_until']??0)<time())jsonResponse(['success'=>false,'error'=>'Campaign confirmation expired. Return to Email Campaigns and confirm again.'],403);

$campaign='we_miss_you_30_day_v1';
$lockName='umcf_we_miss_you_campaign';
$lock=dbFetchOne("SELECT GET_LOCK(?,0) acquired",[$lockName]);
if((int)($lock['acquired']??0)!==1){
 jsonResponse(['success'=>true,'busy'=>true,'total'=>0,'sent'=>0,'failed'=>0,'pending'=>1]);
}

try {
 $rows=dbFetchAll("SELECT ecl.id,ecl.user_id FROM admin_email_campaign_log ecl WHERE ecl.campaign_key=? AND ecl.status IN ('pending','failed') AND ecl.attempts<3 ORDER BY ecl.id LIMIT 1",[$campaign]);
 foreach($rows as $row){
  $claim=dbQuery("UPDATE admin_email_campaign_log SET status='sending',attempts=attempts+1 WHERE id=? AND status IN ('pending','failed')",[(int)$row['id']]);
  if($claim->rowCount()!==1)continue;
  $result=sendWeMissYouEmail((int)$row['user_id']);
  if(!empty($result['success']))dbQuery("UPDATE admin_email_campaign_log SET status='sent',sent_at_utc=UTC_TIMESTAMP(),error_message=NULL WHERE id=?",[(int)$row['id']]);
  else dbQuery("UPDATE admin_email_campaign_log SET status='failed',error_message=? WHERE id=?",[substr((string)($result['error']??'Delivery failed'),0,255),(int)$row['id']]);
 }
 $summary=dbFetchOne("SELECT COUNT(*) total,SUM(status='sent') sent,SUM(status='failed' AND attempts>=3) failed,SUM(status IN ('pending','sending') OR (status='failed' AND attempts<3)) pending FROM admin_email_campaign_log WHERE campaign_key=?",[$campaign])?:[];
 if((int)($summary['pending']??0)===0){auditAdminAction((int)getCurrentUserId(),'email_campaign.completed',null,['campaign'=>$campaign,'sent'=>(int)($summary['sent']??0),'failed'=>(int)($summary['failed']??0)]);unset($_SESSION['inactive_email_campaign_until']);}
} finally {
 dbFetchOne("SELECT RELEASE_LOCK(?) released",[$lockName]);
}
jsonResponse(['success'=>true,'total'=>(int)($summary['total']??0),'sent'=>(int)($summary['sent']??0),'failed'=>(int)($summary['failed']??0),'pending'=>(int)($summary['pending']??0)]);
