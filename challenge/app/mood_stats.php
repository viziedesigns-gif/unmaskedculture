<?php
/**
 * Insights Hub
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/streak_service.php';
require_once __DIR__ . '/../includes/retention_service.php';
require_once __DIR__ . '/../includes/settings_layout.php';

requireOnboarding();

$user = getCurrentUser();
$userId = getCurrentUserId();
$timezone = $user['timezone'] ?? DEFAULT_TIMEZONE;
$waterGoal = (int) ($user['daily_water_oz'] ?? 64);
$streakStatus = getStreakStatus($userId);
$currentStreak = (int) ($streakStatus['current_streak'] ?? 0);
$pathDay = min(77, $currentStreak);
$progressPercent = round(($pathDay / 77) * 100);
$challengeMode = normalizeChallengeMode($streakStatus['challenge_mode'] ?? ($user['challenge_mode'] ?? 'intermediate'));
$itemsDone = (int) ($streakStatus['items_completed'] ?? 0);
$itemsRequired = (int) ($streakStatus['items_required'] ?? 7);
$dayCountsToday = !empty($streakStatus['is_today_completed']);
$honestyStats = getHonestyStats($userId, $streakStatus['user_date'] ?? computeUserDate($timezone));
$weeklyInsight = getWeeklyInsightSummary($userId, $timezone, $waterGoal);

$pageTitle = 'Insights';
include __DIR__ . '/../includes/header.php';
?>

<div class="profile-page settings-hub-page insights-hub-page">
    <div class="page-header">
        <h1>Insights</h1>
    </div>

    <section class="path-overview-card insights-path-summary">
        <div>
            <p class="profile-kicker"><?= $challengeMode === 'easy' ? 'Easy Challenge' : 'Intermediate Challenge' ?></p>
            <h1>Day <?= $pathDay ?> of 77</h1>
        </div>
        <div class="path-overview-progress" aria-label="<?= $progressPercent ?> percent complete">
            <div class="progress-track">
                <div class="progress-fill-large" style="width: <?= $progressPercent ?>%"></div>
            </div>
            <span><?= $progressPercent ?>% complete</span>
        </div>
    </section>

    <section class="partial-day-strip <?= $dayCountsToday ? 'is-counting' : '' ?>">
        <div class="partial-day-strip__meter">
            <div class="partial-day-strip__fill" style="width: <?= $itemsRequired > 0 ? min(100, round(($itemsDone / $itemsRequired) * 100)) : 0 ?>%"></div>
        </div>
        <p class="partial-day-strip__copy">
            <strong><?= $itemsDone ?> / <?= $itemsRequired ?> done today</strong>
            <span>
                <?php if ($dayCountsToday): ?>
                    <?= $challengeMode === 'easy' ? 'This day counts toward your 77.' : 'Full protocol complete for today.' ?>
                <?php elseif ($challengeMode === 'easy'): ?>
                    Check at least one item and this day will count.
                <?php else: ?>
                    Finish all required items for today to count.
                <?php endif; ?>
            </span>
        </p>
    </section>

    <section class="honesty-panel insights-reminder-panel" aria-label="Simple checklist reminder">
        <div class="honesty-panel__header">
            <h2>Friendly reminder</h2>
            <p>A quick look at what is done and what is still open today.</p>
        </div>
        <div class="honesty-columns">
            <div>
                <h3>Done today</h3>
                <?php if (empty($honestyStats['today_done'])): ?>
                    <p class="honesty-empty">Nothing checked yet.</p>
                <?php else: ?>
                    <ul class="honesty-list">
                        <?php foreach ($honestyStats['today_done'] as $row): ?>
                            <li><i data-lucide="check"></i> <?= h($row['name']) ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
            <div>
                <h3>Still open</h3>
                <?php if (empty($honestyStats['today_skipped'])): ?>
                    <p class="honesty-empty">All required items done. Nice work.</p>
                <?php else: ?>
                    <ul class="honesty-list honesty-list--open">
                        <?php foreach ($honestyStats['today_skipped'] as $row): ?>
                            <li><i data-lucide="circle"></i> <?= h($row['name']) ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <div class="weekly-insight-card insights-quick-card">
        <div class="weekly-insight-header">
            <h2><i data-lucide="calendar-range"></i> This Week</h2>
            <span class="weekly-insight-range">
                <?= (new DateTime($weeklyInsight['start_date']))->format('M j') ?>
                -
                <?= (new DateTime($weeklyInsight['end_date']))->format('M j') ?>
            </span>
        </div>
        <div class="weekly-insight-stats">
            <div class="weekly-insight-stat">
                <span class="weekly-insight-value"><?= (int) $weeklyInsight['completed_days'] ?>/7</span>
                <span class="weekly-insight-label">Checklist days</span>
            </div>
            <div class="weekly-insight-stat">
                <span class="weekly-insight-value">
                    <?= $weeklyInsight['avg_mood'] !== null ? number_format($weeklyInsight['avg_mood'], 1) : '-' ?>
                </span>
                <span class="weekly-insight-label">Avg mood</span>
            </div>
            <div class="weekly-insight-stat">
                <span class="weekly-insight-value"><?= (int) $weeklyInsight['water_goal_days'] ?>/7</span>
                <span class="weekly-insight-label">Water goal days</span>
            </div>
            <div class="weekly-insight-stat">
                <span class="weekly-insight-value"><?= (int) $weeklyInsight['current_streak'] ?></span>
                <span class="weekly-insight-label">Current streak</span>
            </div>
        </div>
    </div>

    <?php renderSettingsHubSection('Track Your Patterns', function () {
        renderSettingsHubRow('/challenge/app/insight_mood.php', 'activity', 'Mood Insights', 'Mood averages, trends, and history');
        renderSettingsHubRow('/challenge/app/insight_water.php', 'droplets', 'Water Tracking', 'Hydration totals and goal days');
        renderSettingsHubRow('/challenge/app/insight_weight.php', 'scale', 'Weight & BMI', 'Weight logs, BMI, and body metrics');
        renderSettingsHubRow('/challenge/app/journal_history.php', 'book-open', 'Past Journal Entries', 'Review previous reflections and mood notes');
    }); ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
