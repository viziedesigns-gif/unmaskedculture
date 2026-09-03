<?php
/**
 * Water Insights
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/settings_layout.php';

requireOnboarding();

$user = getCurrentUser();
$userId = getCurrentUserId();
$range = $_GET['range'] ?? '30';
$validRanges = ['7', '30', '90', 'all'];
if (!in_array($range, $validRanges, true)) {
    $range = '30';
}

$timezone = $user['timezone'] ?? DEFAULT_TIMEZONE;
$userDate = computeUserDate($timezone);
$startDate = $range === 'all'
    ? '2000-01-01'
    : (new DateTime($userDate))->modify("-{$range} days")->format('Y-m-d');

$waterEntries = dbFetchAll(
    "SELECT user_date, COALESCE(SUM(oz_amount), 0) AS total_oz
     FROM water_log
     WHERE user_id = ? AND user_date >= ?
     GROUP BY user_date
     ORDER BY user_date ASC",
    [$userId, $startDate]
);

$waterGoal = (int) ($user['daily_water_oz'] ?? 64);
$waterLabels = [];
$waterData = [];
foreach ($waterEntries as $entry) {
    $waterLabels[] = (new DateTime($entry['user_date']))->format('M j');
    $waterData[] = (int) ($entry['total_oz'] ?? 0);
}

$waterDays = count($waterData);
$waterTotal = array_sum($waterData);
$waterAverage = $waterDays > 0 ? round($waterTotal / $waterDays) : 0;
$waterHighest = $waterDays > 0 ? max($waterData) : 0;
$waterGoalDays = 0;
foreach ($waterData as $oz) {
    if ($oz >= $waterGoal) {
        $waterGoalDays++;
    }
}
$waterGoalRate = $waterDays > 0 ? round(($waterGoalDays / $waterDays) * 100) : 0;

$pageTitle = 'Water Tracking';
include __DIR__ . '/../includes/header.php';
?>

<div class="insights-page">
    <div class="settings-detail-header">
        <a href="/challenge/app/mood_stats.php" class="settings-back-link">
            <i data-lucide="chevron-left"></i>
            <span>Insights</span>
        </a>
        <h1>Water Tracking</h1>
    </div>

    <div class="range-selector">
        <a href="?range=7" class="range-btn <?= $range === '7' ? 'active' : '' ?>">7D</a>
        <a href="?range=30" class="range-btn <?= $range === '30' ? 'active' : '' ?>">30D</a>
        <a href="?range=90" class="range-btn <?= $range === '90' ? 'active' : '' ?>">90D</a>
        <a href="?range=all" class="range-btn <?= $range === 'all' ? 'active' : '' ?>">All</a>
    </div>

    <?php if ($waterDays === 0): ?>
        <div class="empty-state">
            <div class="empty-icon"><i data-lucide="droplets"></i></div>
            <h3>No water data yet</h3>
            <p>Start logging water on your daily checklist to see hydration trends here.</p>
            <a href="/challenge/app/dashboard.php" class="btn btn-primary">
                <i data-lucide="plus"></i>
                Log Water
            </a>
        </div>
    <?php else: ?>
        <div class="stats-cards">
            <div class="stat-card"><div class="stat-icon"><i data-lucide="droplets"></i></div><div class="stat-info"><span class="stat-value"><?= $waterAverage ?></span><span class="stat-label">Avg oz/day</span></div></div>
            <div class="stat-card"><div class="stat-icon"><i data-lucide="arrow-up-circle"></i></div><div class="stat-info"><span class="stat-value"><?= $waterHighest ?></span><span class="stat-label">Highest oz</span></div></div>
            <div class="stat-card"><div class="stat-icon"><i data-lucide="target"></i></div><div class="stat-info"><span class="stat-value"><?= $waterGoal ?></span><span class="stat-label">Daily goal</span></div></div>
            <div class="stat-card"><div class="stat-icon"><i data-lucide="check-circle"></i></div><div class="stat-info"><span class="stat-value"><?= $waterGoalDays ?></span><span class="stat-label">Goal days</span></div></div>
            <div class="stat-card"><div class="stat-icon"><i data-lucide="percent"></i></div><div class="stat-info"><span class="stat-value"><?= $waterGoalRate ?>%</span><span class="stat-label">Hit rate</span></div></div>
            <div class="stat-card"><div class="stat-icon"><i data-lucide="calendar-days"></i></div><div class="stat-info"><span class="stat-value"><?= $waterDays ?></span><span class="stat-label">Logged days</span></div></div>
        </div>

        <div class="chart-section">
            <div class="chart-header">
                <h2>Water Over Time</h2>
            </div>
            <div class="chart-container">
                <canvas id="waterChart"></canvas>
            </div>
        </div>

        <div class="trend-analysis">
            <div class="trend-content">
                <div class="trend-badge neutral"><i data-lucide="droplets" class="trend-icon"></i><span>Hydration rhythm</span></div>
                <?php if ($waterGoalRate >= 80): ?>
                    <p>You are hitting your water goal most logged days. Keep that rhythm steady.</p>
                <?php elseif ($waterAverage >= round($waterGoal * 0.75)): ?>
                    <p>You are close to your goal on average. A little earlier water in the day may help close the gap.</p>
                <?php else: ?>
                    <p>Your logged water is below your goal. Try using your bottle size quick-adds throughout the day.</p>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php if ($waterDays > 0): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const waterCtx = document.getElementById('waterChart').getContext('2d');
const waterGoal = <?= (int) $waterGoal ?>;
const waterData = <?= json_encode($waterData) ?>;
new Chart(waterCtx, {
    type: 'bar',
    data: {
        labels: <?= json_encode($waterLabels) ?>,
        datasets: [{
            label: 'Water (oz)',
            data: waterData,
            backgroundColor: waterData.map(value => value >= waterGoal ? '#22c55e' : '#38bdf8'),
            borderColor: waterData.map(value => value >= waterGoal ? '#16a34a' : '#0284c7'),
            borderWidth: 1,
            borderRadius: 6
        }, {
            label: 'Daily Goal',
            data: waterData.map(() => waterGoal),
            type: 'line',
            borderColor: '#ef4444',
            borderWidth: 2,
            borderDash: [6, 6],
            pointRadius: 0,
            fill: false,
            tension: 0
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: true, labels: { usePointStyle: true, boxWidth: 8 } } },
        scales: {
            y: { beginAtZero: true, suggestedMax: Math.max(waterGoal + 16, ...waterData) + 8, ticks: { callback: value => value + ' oz' }, grid: { color: 'rgba(0, 0, 0, 0.04)' } },
            x: { ticks: { maxRotation: 0 }, grid: { display: false } }
        }
    }
});
</script>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
