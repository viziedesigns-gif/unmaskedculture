<?php
/**
 * Journal Export
 * Downloads all in-app journal entries for the current user as CSV.
 */

require_once __DIR__ . '/../includes/auth.php';

requireOnboarding();

$user = getCurrentUser();
$userId = getCurrentUserId();

if (!($user['journal_in_app'] ?? 1)) {
    setFlash('info', 'Journal export is only available when journaling inside the app.');
    redirect('/challenge/app/journal.php');
}

$entries = dbFetchAll(
    "SELECT user_date, mood_level, notes, created_at_utc
     FROM mood_entries
     WHERE user_id = ?
     ORDER BY user_date ASC",
    [$userId]
);

$filenameDate = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d');
$filename = 'unfiltered-journal-' . $filenameDate . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

$out = fopen('php://output', 'w');
fputcsv($out, ['Date', 'Mood Level', 'Journal Entry', 'Created At UTC']);

foreach ($entries as $entry) {
    fputcsv($out, [
        $entry['user_date'],
        $entry['mood_level'],
        $entry['notes'] ?? '',
        $entry['created_at_utc'],
    ]);
}

fclose($out);
exit;
