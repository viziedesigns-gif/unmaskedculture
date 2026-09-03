<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/push_service.php';
require_once __DIR__ . '/../../includes/mail_service.php';
requireSuperAdmin(); ensureAdminInfrastructure(); ensurePushTables();
$admin = getCurrentUser(); $adminId = (int) $admin['id'];
$userId = (int) ($_GET['id'] ?? $_POST['user_id'] ?? 0);
$target = dbFetchOne(
    "SELECT u.id,u.email,u.first_name,u.last_name,u.timezone,u.onboarding_completed,u.created_at,u.last_active_at,
            u.daily_reminder_enabled,u.daily_reminder_time,u.streak_risk_enabled,u.admin_role,
            COALESCE(us.current_streak,0) current_streak, COALESCE(us.longest_streak,0) longest_streak,
            COALESCE(ps.push_devices,0) push_devices, uns.supported,uns.permission_state,uns.last_reported_at_utc
     FROM users u LEFT JOIN user_streaks us ON us.user_id=u.id
     LEFT JOIN (SELECT user_id,COUNT(*) push_devices FROM push_subscriptions GROUP BY user_id) ps ON ps.user_id=u.id
     LEFT JOIN user_notification_status uns ON uns.user_id=u.id WHERE u.id=?",
    [$userId]
);
if (!$target) { setFlash('error','User not found.'); redirect('/challenge/app/admin/users.php'); }
$error=''; $success=''; $csrf=adminCsrfToken();

if ($_SERVER['REQUEST_METHOD']==='POST') {
    $action=(string)($_POST['action']??'');
    if (!validAdminCsrf($_POST['csrf_token']??null)) $error='Your session expired. Refresh and try again.';
    elseif (in_array($action,['promote','demote','send_reset','delete_user'],true)) {
        $confirmed=hasRecentAdminConfirmation() || confirmAdminPassword($adminId,(string)($_POST['admin_password']??''));
        if(!$confirmed) $error='Enter your current super-admin password to continue.';
        elseif($action==='send_reset') {
            $result=createAndSendPasswordReset($userId,$adminId);
            if($result['success']) $success='Password reset email sent.'; else $error=$result['error'] ?: 'Password reset email could not be sent.';
        } elseif ($action==='delete_user') {
            $confirmEmail = strtolower(trim((string) ($_POST['confirm_email'] ?? '')));
            if ($userId === $adminId) $error = 'You cannot delete the account you are currently using.';
            elseif ($confirmEmail !== strtolower((string) $target['email'])) $error = 'Type the userâ€™s full email address to confirm deletion.';
            elseif ($target['admin_role'] === 'super_admin' && getSuperAdminCount() <= 1) $error = 'Promote another super admin before deleting the final super admin.';
            else {
                $deletedEmail = (string) $target['email'];
                [$deleted, $message] = deleteUserAccount($userId);
                if ($deleted) {
                    auditAdminAction($adminId, 'user.deleted', null, ['deleted_user_id' => $userId, 'email_hash' => hash('sha256', strtolower($deletedEmail))]);
                    setFlash('success', 'Account and associated data permanently deleted.');
                    redirect('/challenge/app/admin/users.php');
                }
                $error = $message;
            }
        } else {
            [$ok,$message]=setUserAdminRole($adminId,$userId,$action==='promote'?'super_admin':'user');
            if($ok)$success=$message;else$error=$message;
        }
    } elseif ($action==='send_push') {
        $title=trim((string)($_POST['title']??'')); $body=trim((string)($_POST['body']??'')); $url=trim((string)($_POST['target_url']??'/challenge/app/dashboard.php'));
        if($title===''||$body==='')$error='Add a title and message.';
        elseif(strlen($title)>120||strlen($body)>500)$error='The notification is too long.';
        elseif(!str_starts_with($url,'/challenge/'))$error='The destination must stay inside the challenge app.';
        else { $result=sendPushToUser($userId,$title,$body,$url); auditAdminAction($adminId,'push.targeted',$userId,['sent'=>(int)($result['sent']??0),'failed'=>(int)($result['failed']??0)]); $success='Notification sent to '.(int)($result['sent']??0).' device'.((int)($result['sent']??0)===1?'':'s').'.'; }
    }
    if ($success !== '') {
        setFlash('success', $success);
        redirect('/challenge/app/admin/user.php?id=' . $userId);
    }
    $target = dbFetchOne(
        "SELECT u.id,u.email,u.first_name,u.last_name,u.timezone,u.onboarding_completed,u.created_at,u.last_active_at,
                u.daily_reminder_enabled,u.daily_reminder_time,u.streak_risk_enabled,u.admin_role,
                COALESCE(us.current_streak,0) current_streak,COALESCE(us.longest_streak,0) longest_streak,
                COALESCE(ps.push_devices,0) push_devices,uns.supported,uns.permission_state,uns.last_reported_at_utc
         FROM users u LEFT JOIN user_streaks us ON us.user_id=u.id
         LEFT JOIN (SELECT user_id,COUNT(*) push_devices FROM push_subscriptions GROUP BY user_id) ps ON ps.user_id=u.id
         LEFT JOIN user_notification_status uns ON uns.user_id=u.id WHERE u.id=?",[$userId]);
}

$activity=dbFetchOne("SELECT COUNT(DISTINCT dce.user_date) active_days,COUNT(*) checked_items,MAX(dce.user_date) last_check_date FROM daily_checklist_entries dce WHERE dce.user_id=? AND dce.value=1",[$userId]);
$circleCount=(int)(dbFetchOne("SELECT COUNT(*) c FROM inner_circle_members WHERE user_id=?",[$userId])['c']??0);
$ownedCircleCount=(int)(dbFetchOne("SELECT COUNT(*) c FROM inner_circles WHERE created_by=?",[$userId])['c']??0);
$completionCount=(int)(dbFetchOne("SELECT COUNT(*) c FROM user_daily_completion WHERE user_id=?",[$userId])['c']??0);
$recentPush=dbFetchAll("SELECT notification_type,user_date,sent_at_utc FROM push_notification_log WHERE user_id=? ORDER BY sent_at_utc DESC LIMIT 10",[$userId]);
$notificationStatus=(int)$target['push_devices']>0?'enabled':(($target['permission_state']??'')==='denied'?'denied':(!empty($target['supported'])&&in_array(($target['permission_state']??''),['default','granted'],true)?'not enabled':'unknown'));
$mailStatus=getMailConfigStatus();
$pageTitle='User Support';$bodyClass='admin-page'; include __DIR__.'/../../includes/header.php';
?>
<div class="admin-shell admin-console"><section class="page-header admin-header"><div><p class="admin-eyebrow">User #<?= $userId ?></p><h1><?= h(trim($target['first_name'].' '.$target['last_name']) ?: 'Unnamed user') ?></h1><p><?= h($target['email']) ?></p></div><a class="btn btn-secondary" href="/challenge/app/admin/users.php">Back to users</a></section>
<?php if($success):?><div class="alert alert-success"><?=h($success)?></div><?php endif;?><?php if($error):?><div class="alert alert-error"><?=h($error)?></div><?php endif;?>
<section class="admin-detail-grid">
<article class="settings-card"><h2>Account</h2><dl class="admin-detail-list"><div><dt>Role</dt><dd><?=h(str_replace('_',' ',$target['admin_role']))?></dd></div><div><dt>Onboarding</dt><dd><?=$target['onboarding_completed']?'Complete':'Incomplete'?></dd></div><div><dt>Timezone</dt><dd><?=h($target['timezone'])?></dd></div><div><dt>Created</dt><dd><?=h($target['created_at'])?></dd></div><div><dt>Last active</dt><dd><?=h($target['last_active_at']??'Never')?></dd></div></dl></article>
<article class="settings-card"><h2>Activity summary</h2><dl class="admin-detail-list"><div><dt>Current streak</dt><dd><?= (int)$target['current_streak'] ?> days</dd></div><div><dt>Longest streak</dt><dd><?= (int)$target['longest_streak'] ?> days</dd></div><div><dt>Active checklist days</dt><dd><?= (int)($activity['active_days']??0) ?></dd></div><div><dt>Completed days</dt><dd><?=$completionCount?></dd></div><div><dt>Circles</dt><dd><?=$circleCount?></dd></div></dl></article>
<article class="settings-card"><h2>Notifications</h2><dl class="admin-detail-list"><div><dt>Status</dt><dd><span class="admin-status status-<?=h(str_replace(' ','_',$notificationStatus))?>"><?=h($notificationStatus)?></span></dd></div><div><dt>Active devices</dt><dd><?= (int)$target['push_devices'] ?></dd></div><div><dt>Daily reminder</dt><dd><?=$target['daily_reminder_enabled']?'On at '.h(substr($target['daily_reminder_time'],0,5)):'Off'?></dd></div><div><dt>Streak alert</dt><dd><?=$target['streak_risk_enabled']?'On':'Off'?></dd></div><div><dt>Last browser report</dt><dd><?=h($target['last_reported_at_utc']??'Never')?><?=!empty($target['last_reported_at_utc'])?' UTC':''?></dd></div></dl></article>
</section>
<section class="settings-card admin-action-card"><h2>Account support</h2><p class="form-hint">Sensitive actions require your current password at least once every 15 minutes.</p><div class="admin-support-actions">
<form method="POST"><input type="hidden" name="csrf_token" value="<?=h($csrf)?>"><input type="hidden" name="user_id" value="<?=$userId?>"><input type="hidden" name="action" value="send_reset"><input type="password" name="admin_password" placeholder="Your admin password"><button class="btn btn-primary" type="submit" <?= $mailStatus['configured'] ? '' : 'disabled' ?>>Email password reset</button><?php if(!$mailStatus['configured']):?><small>SMTP configuration is required.</small><?php endif;?></form>
<form method="POST"><input type="hidden" name="csrf_token" value="<?=h($csrf)?>"><input type="hidden" name="user_id" value="<?=$userId?>"><input type="hidden" name="action" value="<?=$target['admin_role']==='super_admin'?'demote':'promote'?>"><input type="password" name="admin_password" placeholder="Your admin password"><button class="btn <?=$target['admin_role']==='super_admin'?'btn-danger':'btn-secondary'?>" type="submit"><?=$target['admin_role']==='super_admin'?'Remove super admin':'Promote to super admin'?></button></form>
</div></section>
<section class="settings-card admin-danger-card"><h2>Delete account</h2><p>This permanently deletes the account and its associated checklist, journal, mood, health, circle, message, notification, and profile data.</p><?php if($ownedCircleCount>0):?><div class="alert alert-warning">This user owns <?= $ownedCircleCount ?> circle<?= $ownedCircleCount===1?'':'s' ?>. Deleting the account will also delete those circles and their feed history.</div><?php endif;?><form method="POST" class="admin-delete-user-form"><input type="hidden" name="csrf_token" value="<?=h($csrf)?>"><input type="hidden" name="user_id" value="<?=$userId?>"><input type="hidden" name="action" value="delete_user"><div class="form-group"><label for="confirm_email">Type <?=h($target['email'])?> to confirm</label><input type="email" id="confirm_email" name="confirm_email" autocomplete="off" required></div><div class="form-group"><label for="delete_admin_password">Your super-admin password</label><input type="password" id="delete_admin_password" name="admin_password" autocomplete="current-password"></div><button class="btn btn-danger" type="submit">Permanently delete account</button></form></section>
<section class="settings-card admin-action-card"><h2>Send troubleshooting notification</h2><form method="POST" class="admin-form"><input type="hidden" name="csrf_token" value="<?=h($csrf)?>"><input type="hidden" name="user_id" value="<?=$userId?>"><input type="hidden" name="action" value="send_push"><div class="form-group"><label>Title</label><input name="title" maxlength="120" required value="Kinto"></div><div class="form-group"><label>Message</label><textarea name="body" maxlength="500" required></textarea></div><div class="form-group"><label>Click destination</label><input name="target_url" value="/challenge/app/settings/notifications.php" required></div><button class="btn btn-primary" type="submit" <?= (int)$target['push_devices']>0?'':'disabled' ?>>Send to this user</button></form></section>
<section class="settings-card"><h2>Recent notification delivery</h2><?php if(!$recentPush):?><p class="form-hint">No recorded automatic notifications.</p><?php else:?><div class="admin-audit-list"><?php foreach($recentPush as $push):?><div><strong><?=h(str_replace('_',' ',$push['notification_type']))?></strong><span><?=h($push['sent_at_utc'])?> UTC</span></div><?php endforeach;?></div><?php endif;?></section>
</div><?php include __DIR__.'/../../includes/footer.php';?>
