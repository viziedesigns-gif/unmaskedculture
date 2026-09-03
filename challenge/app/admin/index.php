<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/push_service.php';
requireSuperAdmin();
ensureAdminInfrastructure();
ensurePushTables();

$pageTitle = 'Admin Console';
$bodyClass = 'admin-page';
$stats = [
    'users' => (int) (dbFetchOne("SELECT COUNT(*) c FROM users")['c'] ?? 0),
    'onboarded' => (int) (dbFetchOne("SELECT COUNT(*) c FROM users WHERE onboarding_completed = 1")['c'] ?? 0),
    'active1' => (int) (dbFetchOne("SELECT COUNT(*) c FROM users WHERE last_active_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 DAY)")['c'] ?? 0),
    'active7' => (int) (dbFetchOne("SELECT COUNT(*) c FROM users WHERE last_active_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 7 DAY)")['c'] ?? 0),
    'active30' => (int) (dbFetchOne("SELECT COUNT(*) c FROM users WHERE last_active_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 30 DAY)")['c'] ?? 0),
    'checks_today' => (int) (dbFetchOne("SELECT COUNT(*) c FROM daily_checklist_entries WHERE user_date = UTC_DATE() AND value = 1")['c'] ?? 0),
    'completions7' => (int) (dbFetchOne("SELECT COUNT(*) c FROM user_daily_completion WHERE user_date >= DATE_SUB(UTC_DATE(), INTERVAL 6 DAY)")['c'] ?? 0),
    'circles' => (int) (dbFetchOne("SELECT COUNT(*) c FROM inner_circles")['c'] ?? 0),
    'messages' => (int) (dbFetchOne("SELECT COUNT(*) c FROM circle_messages")['c'] ?? 0),
    'push_users' => (int) (dbFetchOne("SELECT COUNT(DISTINCT user_id) c FROM push_subscriptions")['c'] ?? 0),
    'push_devices' => (int) (dbFetchOne("SELECT COUNT(*) c FROM push_subscriptions")['c'] ?? 0),
];
$recentAudit = dbFetchAll(
    "SELECT aal.*, a.email admin_email, t.email target_email
     FROM admin_audit_log aal JOIN users a ON a.id = aal.admin_user_id
     LEFT JOIN users t ON t.id = aal.target_user_id
     ORDER BY aal.created_at_utc DESC LIMIT 12"
);
include __DIR__ . '/../../includes/header.php';
?>
<div class="admin-shell admin-console">
    <section class="page-header admin-header"><div><p class="admin-eyebrow">Super Admin</p><h1>Support Console</h1><p>Account support, app health, and notification operations.</p></div><a class="btn btn-secondary" href="/challenge/app/dashboard.php">Back to app</a></section>
    <nav class="admin-console-nav" aria-label="Admin console">
        <a href="/challenge/app/admin/users.php"><i data-lucide="users"></i><span>Users</span></a>
        <a href="/challenge/app/admin/notifications.php"><i data-lucide="bell"></i><span>Push</span></a>
        <a href="/challenge/app/admin/email_campaigns.php"><i data-lucide="mail"></i><span>Email</span></a>
    </nav>
    <section class="admin-stat-grid">
        <?php foreach ([
            ['Users', $stats['users'], $stats['onboarded'] . ' onboarded'],
            ['Active today', $stats['active1'], $stats['active7'] . ' in 7 days'],
            ['Active 30 days', $stats['active30'], 'recent accounts'],
            ['Checks today', $stats['checks_today'], 'completed items'],
            ['7-day completions', $stats['completions7'], 'completed days'],
            ['Circles', $stats['circles'], $stats['messages'] . ' feed messages'],
            ['Notifications', $stats['push_users'], $stats['push_devices'] . ' devices'],
        ] as [$label, $value, $detail]): ?>
            <article class="settings-card admin-stat-card"><span><?= h($label) ?></span><strong><?= (int) $value ?></strong><small><?= h($detail) ?></small></article>
        <?php endforeach; ?>
    </section>
    <section class="settings-card admin-audit-card"><h2>Recent admin activity</h2>
        <?php if (!$recentAudit): ?><p class="form-hint">No admin actions yet.</p><?php else: ?>
        <div class="admin-audit-list"><?php foreach ($recentAudit as $entry): ?><div><strong><?= h(str_replace('.', ' ', $entry['action'])) ?></strong><span><?= h($entry['target_email'] ?: $entry['admin_email']) ?> · <?= h($entry['created_at_utc']) ?> UTC</span></div><?php endforeach; ?></div>
        <?php endif; ?>
    </section>
</div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
