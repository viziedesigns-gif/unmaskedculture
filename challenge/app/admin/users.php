<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/push_service.php';
requireSuperAdmin(); ensureAdminInfrastructure(); ensurePushTables();
$pageTitle = 'Admin Users'; $bodyClass = 'admin-page';

$q = trim((string) ($_GET['q'] ?? ''));
$onboarding = (string) ($_GET['onboarding'] ?? 'all');
$activity = (string) ($_GET['activity'] ?? 'all');
$notifications = (string) ($_GET['notifications'] ?? 'all');
$page = max(1, (int) ($_GET['page'] ?? 1)); $perPage = 25; $offset = ($page - 1) * $perPage;
$where = ['1=1']; $params = [];
if ($q !== '') { $where[] = "(u.email LIKE ? OR CONCAT_WS(' ', u.first_name, u.last_name) LIKE ? OR u.id = ?)"; $like = '%' . $q . '%'; array_push($params, $like, $like, ctype_digit($q) ? (int) $q : 0); }
if ($onboarding === 'yes') $where[] = 'u.onboarding_completed = 1';
if ($onboarding === 'no') $where[] = 'u.onboarding_completed = 0';
if ($activity === 'active7') $where[] = 'u.last_active_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 7 DAY)';
if ($activity === 'inactive30') $where[] = '(u.last_active_at IS NULL OR u.last_active_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL 30 DAY))';
$notifyCase = "CASE WHEN COALESCE(ps.push_devices,0) > 0 THEN 'enabled' WHEN uns.permission_state = 'denied' THEN 'denied' WHEN uns.supported = 1 AND uns.permission_state IN ('default','granted') THEN 'not_enabled' ELSE 'unknown' END";
$joins = "LEFT JOIN user_streaks us ON us.user_id=u.id
          LEFT JOIN (SELECT user_id,COUNT(*) circle_count FROM inner_circle_members GROUP BY user_id) icm ON icm.user_id=u.id
          LEFT JOIN (SELECT user_id,COUNT(*) push_devices FROM push_subscriptions GROUP BY user_id) ps ON ps.user_id=u.id
          LEFT JOIN user_notification_status uns ON uns.user_id=u.id";
if (in_array($notifications, ['enabled','denied','not_enabled','unknown'], true)) { $where[] = "$notifyCase = ?"; $params[] = $notifications; }
$whereSql = implode(' AND ', $where);
$total = (int) (dbFetchOne("SELECT COUNT(*) c FROM users u $joins WHERE $whereSql", $params)['c'] ?? 0);
$rows = dbFetchAll(
    "SELECT u.id,u.email,u.first_name,u.last_name,u.onboarding_completed,u.last_active_at,u.created_at,u.admin_role,
            COALESCE(us.current_streak,0) current_streak, COALESCE(icm.circle_count,0) circle_count,
            COALESCE(ps.push_devices,0) push_devices, uns.permission_state, uns.last_reported_at_utc, $notifyCase notification_status
     FROM users u $joins WHERE $whereSql ORDER BY u.created_at DESC LIMIT $perPage OFFSET $offset",
    $params
);
include __DIR__ . '/../../includes/header.php';
?>
<div class="admin-shell admin-console"><section class="page-header admin-header"><div><p class="admin-eyebrow">Super Admin</p><h1>Users</h1><p>Search accounts and review safe support information.</p></div><div class="admin-header-actions"><a class="btn btn-secondary" href="/challenge/app/admin/export_emails.php"><i data-lucide="download"></i> Export emails</a><a class="btn btn-secondary" href="/challenge/app/admin/">Console</a></div></section>
<form method="GET" class="settings-card admin-user-filters"><input type="search" name="q" value="<?= h($q) ?>" placeholder="Name, email, or user ID">
<select name="onboarding"><option value="all">All onboarding</option><option value="yes" <?= $onboarding==='yes'?'selected':'' ?>>Onboarded</option><option value="no" <?= $onboarding==='no'?'selected':'' ?>>Not onboarded</option></select>
<select name="activity"><option value="all">All activity</option><option value="active7" <?= $activity==='active7'?'selected':'' ?>>Active in 7 days</option><option value="inactive30" <?= $activity==='inactive30'?'selected':'' ?>>Inactive 30+ days</option></select>
<select name="notifications"><option value="all">All notifications</option><option value="enabled" <?= $notifications==='enabled'?'selected':'' ?>>Enabled</option><option value="denied" <?= $notifications==='denied'?'selected':'' ?>>Denied</option><option value="not_enabled" <?= $notifications==='not_enabled'?'selected':'' ?>>Not enabled</option><option value="unknown" <?= $notifications==='unknown'?'selected':'' ?>>Unknown</option></select>
<button class="btn btn-primary" type="submit">Search</button></form>
<p class="admin-result-count"><?= $total ?> matching user<?= $total===1?'':'s' ?></p>
<div class="admin-user-list"><?php foreach ($rows as $row): ?><a class="settings-card admin-user-row" href="/challenge/app/admin/user.php?id=<?= (int)$row['id'] ?>"><div><strong><?= h(trim($row['first_name'].' '.$row['last_name']) ?: 'Unnamed user') ?></strong><span><?= h($row['email']) ?> · #<?= (int)$row['id'] ?></span></div><div class="admin-user-meta"><span class="admin-status status-<?= h($row['notification_status']) ?>"><?= h(str_replace('_',' ',$row['notification_status'])) ?></span><span><?= (int)$row['push_devices'] ?> device<?= (int)$row['push_devices']===1?'':'s' ?></span><span><?= (int)$row['current_streak'] ?> day streak</span></div></a><?php endforeach; ?><?php if(!$rows): ?><div class="settings-card"><p>No users match these filters.</p></div><?php endif; ?></div>
<?php if ($total > $perPage): ?><nav class="admin-pagination"><?php if($page>1): ?><a class="btn btn-secondary btn-sm" href="?<?= h(http_build_query(array_merge($_GET,['page'=>$page-1]))) ?>">Previous</a><?php endif; ?><span>Page <?= $page ?></span><?php if($page*$perPage<$total): ?><a class="btn btn-secondary btn-sm" href="?<?= h(http_build_query(array_merge($_GET,['page'=>$page+1]))) ?>">Next</a><?php endif; ?></nav><?php endif; ?></div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
