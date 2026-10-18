<?php
/** Small, non-sensitive preference snapshot for on-device reminders. */
$reminderUser = $currentUser ?? null;
$reminderContext = [
    'signedIn' => !empty($reminderUser),
    'userId' => (int) ($reminderUser['id'] ?? 0),
    'enabled' => (bool) ($reminderUser['daily_reminder_enabled'] ?? true),
    'time' => substr((string) ($reminderUser['daily_reminder_time'] ?? '18:00:00'), 0, 5),
];
?>
<script type="application/json" id="kinto-reminder-context"><?= json_encode($reminderContext, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
