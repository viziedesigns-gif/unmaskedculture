<?php $dailyPercent = $itemsRequired > 0 ? min(100, round(($itemsDone / $itemsRequired) * 100)) : 0; ?>
<section class="daily-focus" aria-labelledby="dailyFocusTitle">
    <div>
        <span class="kinto-eyebrow"><?= $challengeMode === 'easy' ? 'Your pace · Easy' : 'Your pace · Intermediate' ?></span>
        <h2 id="dailyFocusTitle"><?= $streakStatus['is_today_completed'] ? 'You showed up for yourself.' : 'Small steps. A stronger you.' ?></h2>
        <p><?= $streakStatus['is_today_completed'] ? 'Take a breath. Every bit of care counts.' : 'A little movement, a mindful moment. Start wherever you are.' ?></p>
        <a href="#dailyRhythm" class="daily-focus-link"><?= $streakStatus['is_today_completed'] ? 'See your day' : 'Continue your day' ?><i data-lucide="arrow-up-right" aria-hidden="true"></i></a>
    </div>
    <div class="daily-orbit" aria-hidden="true"><img src="/challenge/assets/brand/kinto-vase.svg" alt="" width="100" height="120"></div>
</section>
<div class="daily-metrics">
    <a class="daily-metric" href="/challenge/app/streaks.php"><i data-lucide="flame" aria-hidden="true"></i><strong class="streak-count"><?= (int) $streakStatus['current_streak'] ?></strong><small>day streak</small></a>
    <a class="daily-metric" href="/challenge/app/settings/shop.php"><i data-lucide="sparkles" aria-hidden="true"></i><strong id="calmPointsTotal"><?= (int) $calmPoints ?></strong><small>calm points · L<span id="calmLevel"><?= (int) $calmLevel ?></span></small></a>
    <a class="daily-metric" href="/challenge/app/streaks.php"><i data-lucide="shield-check" aria-hidden="true"></i><strong id="streakRepairsTotal"><?= (int) $streakStatus['streak_repairs'] ?></strong><small>streak repairs</small></a>
</div>
<div class="daily-progress" role="status" aria-live="polite">
    <strong id="dailyProgressText"><?= (int) $itemsDone ?>/<?= (int) $itemsRequired ?> complete</strong>
    <div class="daily-progress-track" aria-hidden="true"><span id="dailyProgressBar" style="width:<?= (int) $dailyPercent ?>%"></span></div>
    <div hidden class="progress-ring" data-progress="<?= (int) $dailyPercent ?>"><span class="progress-text"><?= (int) $itemsDone ?>/<?= (int) $itemsRequired ?></span></div>
</div>
