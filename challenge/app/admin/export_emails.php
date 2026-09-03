<?php
/** Confirm and download the super-admin user email directory as CSV. */
require_once __DIR__ . '/../../includes/auth.php';
requireSuperAdmin();

$adminId = (int) getCurrentUserId();
$error = '';
$csrf = adminCsrfToken();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validAdminCsrf($_POST['csrf_token'] ?? null)) {
        $error = 'Your session expired. Refresh and try again.';
    } elseif (!hasRecentAdminConfirmation() && !confirmAdminPassword($adminId, (string) ($_POST['admin_password'] ?? ''))) {
        $error = 'Enter your current super-admin password to export the email list.';
    } else {
        $users = dbFetchAll(
            "SELECT id,email,first_name,last_name,onboarding_completed,created_at,last_active_at
             FROM users ORDER BY created_at DESC"
        );
        auditAdminAction($adminId, 'users.email_exported', null, ['row_count' => count($users)]);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="kinto-user-emails-' . gmdate('Y-m-d') . '.csv"');
        header('Cache-Control: no-store, max-age=0');

        $safeCsvValue = static function ($value): string {
            $value = (string) ($value ?? '');
            return preg_match('/^[=+\-@]/', $value) ? "'" . $value : $value;
        };
        $output = fopen('php://output', 'wb');
        fputcsv($output, ['User ID','Email','First name','Last name','Onboarded','Created UTC','Last active UTC']);
        foreach ($users as $user) {
            fputcsv($output, [
                (int) $user['id'], $safeCsvValue($user['email']), $safeCsvValue($user['first_name']),
                $safeCsvValue($user['last_name']), !empty($user['onboarding_completed']) ? 'Yes' : 'No',
                $user['created_at'], $user['last_active_at'],
            ]);
        }
        fclose($output);
        exit;
    }
}

$pageTitle = 'Export User Emails';
$bodyClass = 'admin-page';
include __DIR__ . '/../../includes/header.php';
?>
<div class="admin-shell admin-console">
    <section class="page-header admin-header"><div><p class="admin-eyebrow">Super Admin</p><h1>Export user emails</h1><p>Download every registered email address and basic account metadata as a CSV file.</p></div><a class="btn btn-secondary" href="/challenge/app/admin/users.php">Back to users</a></section>
    <?php if ($error): ?><div class="alert alert-error"><?= h($error) ?></div><?php endif; ?>
    <section class="settings-card admin-action-card">
        <p class="form-hint">This sensitive export is audited and requires recent password confirmation.</p>
        <form method="POST" class="admin-form">
            <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
            <div class="form-group"><label for="admin_password">Your super-admin password</label><input type="password" id="admin_password" name="admin_password" autocomplete="current-password"></div>
            <button class="btn btn-primary" type="submit"><i data-lucide="download"></i> Download CSV</button>
        </form>
    </section>
</div>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
