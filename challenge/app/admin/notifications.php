<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/push_service.php';
requireSuperAdmin(); ensurePushTables(); ensureAdminInfrastructure();
$admin=getCurrentUser();$adminId=(int)$admin['id'];$csrf=adminCsrfToken();$error='';$success='';
if($_SERVER['REQUEST_METHOD']==='POST'){
 $title=trim((string)($_POST['title']??''));$body=trim((string)($_POST['body']??''));$url=trim((string)($_POST['target_url']??'/challenge/app/dashboard.php'));
 if(!validAdminCsrf($_POST['csrf_token']??null))$error='Your session expired. Refresh and try again.';
 elseif($title===''||$body==='')$error='Add a title and message.';
 elseif(strlen($title)>120||strlen($body)>500)$error='The notification is too long.';
 elseif(!str_starts_with($url,'/challenge/'))$error='The destination must stay inside the challenge app.';
 else{$result=sendBroadcastPush($adminId,$title,$body,$url,'all_onboarded');auditAdminAction($adminId,'push.broadcast',null,['sent'=>(int)($result['sent']??0),'failed'=>(int)($result['failed']??0)]);$success='Sent to '.(int)($result['sent']??0).' device'.((int)($result['sent']??0)===1?'':'s').'.';setFlash('success',$success);redirect('/challenge/app/admin/notifications.php');}
}
$pushStatus=getPushConfigStatus();$deviceCount=getBroadcastPushDeviceCount();
$history=dbFetchAll("SELECT pb.*,u.email FROM push_broadcasts pb JOIN users u ON u.id=pb.admin_user_id ORDER BY pb.created_at_utc DESC LIMIT 15");
$pageTitle='Admin Push';$bodyClass='admin-page';include __DIR__.'/../../includes/header.php';
?>
<div class="admin-shell admin-console"><section class="page-header admin-header"><div><p class="admin-eyebrow">Super Admin</p><h1>Push Notifications</h1><p>Broadcast to onboarded users with active device subscriptions.</p></div><a class="btn btn-secondary" href="/challenge/app/admin/">Console</a></section>
<?php if($success):?><div class="alert alert-success"><?=h($success)?></div><?php endif;?><?php if($error):?><div class="alert alert-error"><?=h($error)?></div><?php endif;?>
<?php if(!$pushStatus['configured']):?><div class="alert alert-warning">Push is not fully configured on this server.</div><?php endif;?>
<section class="settings-card admin-broadcast-card"><div class="settings-card-header"><h2>New broadcast</h2><span class="admin-device-count"><?=$deviceCount?> target devices</span></div><form method="POST" class="admin-form"><input type="hidden" name="csrf_token" value="<?=h($csrf)?>"><div class="form-group"><label>Title</label><input name="title" maxlength="120" required></div><div class="form-group"><label>Message</label><textarea name="body" maxlength="500" rows="4" required></textarea></div><div class="form-group"><label>Click destination</label><input name="target_url" value="/challenge/app/dashboard.php" required></div><button type="submit" class="btn btn-primary" <?= $pushStatus['configured'] && $deviceCount > 0 ? '' : 'disabled' ?>>Send notification</button></form></section>
<section class="settings-card"><h2>Recent broadcasts</h2><?php if(!$history):?><p class="form-hint">No broadcasts yet.</p><?php else:?><div class="admin-history-list"><?php foreach($history as $item):?><article class="admin-history-item"><div><h3><?=h($item['title'])?></h3><p><?=h($item['body'])?></p><span><?=h($item['created_at_utc'])?> UTC by <?=h($item['email'])?></span></div><div class="admin-history-stats"><strong><?=(int)$item['sent_count']?></strong><span>sent</span><strong><?=(int)$item['failed_count']?></strong><span>failed</span></div></article><?php endforeach;?></div><?php endif;?></section></div>
<?php include __DIR__.'/../../includes/footer.php';?>
