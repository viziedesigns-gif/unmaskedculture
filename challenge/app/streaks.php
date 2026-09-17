<?php
/** Personal and membership-scoped circle streaks. */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/streak_service.php';
requireOnboarding();
$user = getCurrentUser();
$userId = getCurrentUserId();
$circles = dbFetchAll('SELECT c.id, c.name FROM inner_circles c JOIN inner_circle_members m ON m.circle_id = c.id WHERE m.user_id = ? ORDER BY c.name', [$userId]);
$circleId = (int) ($_GET['circle'] ?? 0);
$circle = null;
foreach ($circles as $candidate) {
    if ((int) $candidate['id'] === $circleId) $circle = $candidate;
}
if ($circleId && !$circle) {
    http_response_code(403);
    exit('This circle is not available to your account.');
}
$members = $circle
    ? dbFetchAll('SELECT u.id, u.first_name, u.last_name FROM users u JOIN inner_circle_members m ON m.user_id = u.id WHERE m.circle_id = ?', [$circleId])
    : [['id' => $userId, 'first_name' => $user['first_name'], 'last_name' => $user['last_name']]];
foreach ($members as &$member) {
    $member['status'] = getStreakStatus((int) $member['id']);
    $member['name'] = trim($member['first_name'] . ' ' . $member['last_name']) ?: 'Kinto member';
}
unset($member);
usort($members, static fn($a, $b) => ($b['status']['current_streak'] <=> $a['status']['current_streak']) ?: strcasecmp($a['name'], $b['name']) ?: ($a['id'] <=> $b['id']));
$selectedId = (int) ($_GET['member'] ?? $userId);
$selected = null;
$rank = 0;
$lastScore = null;
foreach ($members as $i => &$member) {
    $score = (int) $member['status']['current_streak'];
    if ($score !== $lastScore) $rank = $i + 1;
    $member['rank'] = $score > 0 ? $rank : null;
    $lastScore = $score;
    if ((int) $member['id'] === $selectedId) $selected = $member;
}
unset($member);
if (!$selected) { http_response_code(403); exit('This member is not available in this view.'); }
$today = $selected['status']['user_date'];
$monthInput = (string) ($_GET['month'] ?? substr($today, 0, 7));
$month = DateTimeImmutable::createFromFormat('!Y-m', $monthInput);
if (!$month || $month->format('Y-m') !== $monthInput || $monthInput < '2020-01' || $monthInput > '2100-12') {
    $month = new DateTimeImmutable(substr($today, 0, 7) . '-01');
}
$start = $month->modify('first day of this month');
$end = $start->modify('+1 month');
$history = [];
foreach (dbFetchAll('SELECT user_date FROM user_daily_completion WHERE user_id = ? AND user_date >= ? AND user_date < ?', [$selectedId, $start->format('Y-m-d'), $end->format('Y-m-d')]) as $day) $history[$day['user_date']] = true;
$url = static fn($changes = []) => '/challenge/app/streaks.php?' . http_build_query(array_merge(['circle' => $circleId, 'member' => $selectedId, 'month' => $month->format('Y-m')], $changes));
$pageTitle = 'Streaks';
include __DIR__ . '/../includes/header.php';
?>
<div class="streaks-page">
    <header class="streaks-heading"><div><p class="streaks-eyebrow">Kinto · Keep becoming</p><h1>Your daily rhythm</h1><p>Small steps. Shared momentum.</p></div><a class="btn btn-secondary" href="<?= $circle ? '/challenge/app/feed.php?circle=' . $circleId : '/challenge/app/dashboard.php' ?>" aria-label="Back to <?= $circle ? 'circle' : 'dashboard' ?>"><i data-lucide="arrow-left"></i> Back</a></header>
    <nav class="streaks-tabs" aria-label="Streak views"><a href="/challenge/app/streaks.php" <?= !$circle ? 'aria-current="page"' : '' ?>>Personal</a><?php foreach ($circles as $item): ?><a href="<?= h($url(['circle' => (int) $item['id'], 'member' => $userId])) ?>" <?= (int) $item['id'] === $circleId ? 'aria-current="page"' : '' ?>><?= h($item['name']) ?></a><?php endforeach; ?></nav>
    <section class="streaks-hero" aria-label="Selected member streak"><img src="/challenge/assets/brand/kinto-bowl.svg" alt="" width="90" height="90"><div><p><?= $selectedId === $userId ? 'Your streak' : h($selected['name']) . '’s streak' ?></p><strong><?= (int) $selected['status']['current_streak'] ?><span> day<?= (int) $selected['status']['current_streak'] === 1 ? '' : 's' ?></span></strong><p>Best rhythm: <?= (int) $selected['status']['longest_streak'] ?> days · <?= $selected['status']['challenge_mode'] === 'easy' ? 'Easy' : 'Intermediate' ?> mode</p></div></section>
    <?php if ($selectedId === $userId): ?><section class="streaks-repair"><i data-lucide="shield-check" aria-hidden="true"></i><div><h2>Room to begin again</h2><p><strong><?= (int) $selected['status']['streak_repairs'] ?> streak repairs available.</strong> A repair can cover one missed day. Use it from your daily checklist when eligible.</p><a href="/challenge/app/dashboard.php">Go to today’s checklist &rarr;</a></div></section><?php endif; ?>
    <div class="streaks-layout">
    <section class="streaks-panel"><h2><?= $circle ? 'Circle leaderboard' : 'Your progress' ?></h2><p class="streaks-note">Ranked by current streak. Ties share a place; each person follows their own mode and local day.</p><ol class="streaks-leaders"><?php foreach ($members as $member): ?><li><a href="<?= h($url(['member' => (int) $member['id']])) ?>" <?= (int) $member['id'] === $selectedId ? 'aria-current="true"' : '' ?>><span class="streaks-rank"><?= $member['rank'] ? '#' . $member['rank'] : '—' ?></span><span class="streaks-person"><strong><?= h($member['name']) ?><?= (int) $member['id'] === $userId ? ' (you)' : '' ?></strong><small><?= $member['status']['challenge_mode'] === 'easy' ? 'Easy' : 'Intermediate' ?><?= $member['status']['can_repair'] ? ' · Repair needed' : '' ?></small></span><span class="streaks-score"><i data-lucide="flame" aria-hidden="true"></i><?= (int) $member['status']['current_streak'] ?><small>days</small></span></a></li><?php endforeach; ?></ol><?php if (!$circle && !$circles): ?><a href="/challenge/app/settings/circles.php">Join a circle to grow together &rarr;</a><?php endif; ?></section>
    <section class="streaks-panel"><h2>Activity calendar</h2><p class="streaks-note"><?= h($selected['name']) ?> · Completed days in their local time.</p><div class="streaks-month"><a class="btn btn-secondary" href="<?= h($url(['month' => $start->modify('-1 month')->format('Y-m')])) ?>" aria-label="Previous month">&larr;</a><h3><?= h($start->format('F Y')) ?></h3><a class="btn btn-secondary" href="<?= h($url(['month' => $end->format('Y-m')])) ?>" aria-label="Next month">&rarr;</a></div><div class="streaks-calendar" role="img" aria-label="<?= count($history) ?> completed days in <?= h($start->format('F Y')) ?>. Completed dates: <?= h(implode(', ', array_keys($history))) ?>"><?php foreach (['Sun','Mon','Tue','Wed','Thu','Fri','Sat'] as $weekday): ?><span class="streaks-weekday"><?= $weekday ?></span><?php endforeach; ?><?php for ($blank = 0; $blank < (int) $start->format('w'); $blank++): ?><span></span><?php endfor; ?><?php for ($day = $start; $day < $end; $day = $day->modify('+1 day')): $date = $day->format('Y-m-d'); ?><span class="streaks-day<?= isset($history[$date]) ? ' is-complete' : '' ?><?= $date === $today ? ' is-today' : '' ?><?= $date > $today ? ' is-future' : '' ?>" title="<?= h($date) ?><?= isset($history[$date]) ? ': Completed' : '' ?>"><?= $day->format('j') ?></span><?php endfor; ?></div><p class="streaks-legend"><span></span> Completed day · Outline marks today</p><p class="streaks-note"><?= count($history) ? count($history) . ' days of showing up this month.' : 'No completed days this month yet. Every rhythm starts with one day.' ?></p></section>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
