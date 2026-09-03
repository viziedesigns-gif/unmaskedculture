<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/mail_service.php';
requireSuperAdmin(); ensureAdminInfrastructure();

const WE_MISS_YOU_CAMPAIGN = 'we_miss_you_30_day_v1';
$adminId = (int) getCurrentUserId();
$csrf = adminCsrfToken();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validAdminCsrf($_POST['csrf_token'] ?? null)) {
        $error = 'Your session expired. Refresh and try again.';
    } elseif (!hasRecentAdminConfirmation() && !confirmAdminPassword($adminId, (string) ($_POST['admin_password'] ?? ''))) {
        $error = 'Enter your current super-admin password to start the campaign.';
    } elseif (trim((string) ($_POST['confirm_text'] ?? '')) !== 'SEND WE MISS YOU') {
        $error = 'Type SEND WE MISS YOU to confirm the campaign.';
    } elseif (!getMailConfigStatus()['configured']) {
        $error = 'SMTP is not configured.';
    } else {
        dbQuery(
            "INSERT IGNORE INTO admin_email_campaign_log (campaign_key,user_id,status,created_at_utc)
             SELECT ?,u.id,'pending',UTC_TIMESTAMP() FROM users u
             WHERE ((u.last_active_at IS NOT NULL AND u.last_active_at < DATE_SUB(UTC_TIMESTAMP(),INTERVAL 30 DAY))
                OR (u.last_active_at IS NULL AND u.created_at < DATE_SUB(UTC_TIMESTAMP(),INTERVAL 30 DAY)))",
            [WE_MISS_YOU_CAMPAIGN]
        );
        $_SESSION['inactive_email_campaign_until'] = time() + 1800;
        auditAdminAction($adminId, 'email_campaign.started', null, ['campaign' => WE_MISS_YOU_CAMPAIGN]);
        redirect('/challenge/app/admin/email_campaigns.php?run=1');
    }
}

$eligible = (int) (dbFetchOne(
    "SELECT COUNT(*) c FROM users u LEFT JOIN admin_email_campaign_log ecl ON ecl.user_id=u.id AND ecl.campaign_key=?
     WHERE ecl.id IS NULL AND ((u.last_active_at IS NOT NULL AND u.last_active_at < DATE_SUB(UTC_TIMESTAMP(),INTERVAL 30 DAY))
       OR (u.last_active_at IS NULL AND u.created_at < DATE_SUB(UTC_TIMESTAMP(),INTERVAL 30 DAY)))",
    [WE_MISS_YOU_CAMPAIGN]
)['c'] ?? 0);
$summary = dbFetchOne(
    "SELECT COUNT(*) total,SUM(status='pending') pending,SUM(status='sent') sent,SUM(status='failed') failed
     FROM admin_email_campaign_log WHERE campaign_key=?",
    [WE_MISS_YOU_CAMPAIGN]
) ?: [];
$preview = dbFetchAll(
    "SELECT u.email,u.first_name,u.last_active_at,u.created_at FROM users u
     LEFT JOIN admin_email_campaign_log ecl ON ecl.user_id=u.id AND ecl.campaign_key=?
     WHERE ecl.id IS NULL AND ((u.last_active_at IS NOT NULL AND u.last_active_at < DATE_SUB(UTC_TIMESTAMP(),INTERVAL 30 DAY))
       OR (u.last_active_at IS NULL AND u.created_at < DATE_SUB(UTC_TIMESTAMP(),INTERVAL 30 DAY)))
     ORDER BY COALESCE(u.last_active_at,u.created_at) ASC LIMIT 20",
    [WE_MISS_YOU_CAMPAIGN]
);
$pageTitle='Email Campaigns';$bodyClass='admin-page';include __DIR__.'/../../includes/header.php';
?>
<div class="admin-shell admin-console"><section class="page-header admin-header"><div><p class="admin-eyebrow">Super Admin</p><h1>Email Campaigns</h1><p>Reconnect with accounts inactive for at least 30 days.</p></div><a class="btn btn-secondary" href="/challenge/app/admin/">Console</a></section>
<?php if($error):?><div class="alert alert-error"><?=h($error)?></div><?php endif;?>
<section class="admin-stat-grid"><article class="settings-card admin-stat-card"><span>Ready to receive</span><strong><?=$eligible?></strong><small>not previously sent</small></article><article class="settings-card admin-stat-card"><span>Sent</span><strong><?=(int)($summary['sent']??0)?></strong><small>campaign deliveries</small></article><article class="settings-card admin-stat-card"><span>Failed</span><strong><?=(int)($summary['failed']??0)?></strong><small>available to retry</small></article></section>
<section class="settings-card admin-action-card"><h2>We Miss You</h2><p>A branded individual email will be sent to each eligible account, with <strong><?=h(APP_SMTP_FROM_EMAIL)?></strong> copied. Each account receives this campaign only once.</p>
<?php if($eligible>0):?><form method="POST" class="admin-form"><input type="hidden" name="csrf_token" value="<?=h($csrf)?>"><div class="form-group"><label for="confirm_text">Type SEND WE MISS YOU</label><input id="confirm_text" name="confirm_text" required autocomplete="off"></div><div class="form-group"><label for="admin_password">Your super-admin password</label><input type="password" id="admin_password" name="admin_password" autocomplete="current-password"></div><button class="btn btn-primary" type="submit">Queue and send <?=$eligible?> emails</button></form><?php else:?><p class="form-hint">No new eligible accounts.</p><?php endif;?></section>
<?php if($preview):?><section class="settings-card"><h2>Recipient preview</h2><div class="admin-audit-list"><?php foreach($preview as $row):?><div><strong><?=h($row['email'])?></strong><span><?=!empty($row['last_active_at'])?'Last active '.h($row['last_active_at']).' UTC':'Never active · joined '.h($row['created_at'])?></span></div><?php endforeach;?></div></section><?php endif;?>
<section class="settings-card" id="campaignProgress" <?=isset($_GET['run'])?'':'hidden'?>><h2>Sending campaign</h2><p id="campaignProgressText">Preparing the first batch…</p><div class="progress-bar"><div class="progress-fill" id="campaignProgressFill" style="width:0%"></div></div></section></div>
<?php if(isset($_GET['run'])):?><script>
(async function sendCampaignBatches(){
 const text=document.getElementById('campaignProgressText'),fill=document.getElementById('campaignProgressFill');
 try{const response=await fetch('/challenge/api/admin_send_inactive_email_batch.php',{method:'POST',headers:{'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},body:JSON.stringify({csrf_token:<?=json_encode($csrf)?>})});const data=await response.json();if(!data.success)throw new Error(data.error||'Unable to send this batch');const total=Number(data.total)||0,complete=(Number(data.sent)||0)+(Number(data.failed)||0);fill.style.width=(total?Math.round(complete/total*100):100)+'%';text.textContent=`${Number(data.sent)||0} sent · ${Number(data.failed)||0} failed · ${Number(data.pending)||0} remaining`;if(Number(data.pending)>0)setTimeout(sendCampaignBatches,500);else setTimeout(()=>window.location.assign('/challenge/app/admin/email_campaigns.php'),1200);}catch(error){text.textContent=error.message||'Campaign paused. Refresh to resume.';}
})();
</script><?php endif;?>
<?php include __DIR__.'/../../includes/footer.php';?>
