<?php
/**
 * Weight & BMI Insights
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

ensureWeightTrackingReady();
$weightEntries = dbFetchAll(
    "SELECT user_date, weight_lbs, bmi
     FROM weight_log
     WHERE user_id = ? AND user_date >= ?
     ORDER BY user_date ASC",
    [$userId, $startDate]
);

$weightLabels = [];
$weightData = [];
$bmiData = [];
foreach ($weightEntries as $entry) {
    $weightLabels[] = (new DateTime($entry['user_date']))->format('M j');
    $weightData[] = (float) $entry['weight_lbs'];
    $bmiData[] = (float) $entry['bmi'];
}

$weightDays = count($weightData);
$latestWeightEntry = $weightDays > 0 ? $weightEntries[$weightDays - 1] : null;
$firstWeight = $weightDays > 0 ? (float) $weightData[0] : null;
$latestWeight = $latestWeightEntry ? (float) $latestWeightEntry['weight_lbs'] : (isset($user['weight_lbs']) ? (float) $user['weight_lbs'] : null);
$latestBmi = $latestWeightEntry ? (float) $latestWeightEntry['bmi'] : (isset($user['bmi']) ? (float) $user['bmi'] : null);
$weightChange = ($firstWeight !== null && $latestWeight !== null) ? round($latestWeight - $firstWeight, 1) : null;
$latestWeightDate = $latestWeightEntry ? (new DateTime($latestWeightEntry['user_date']))->format('M j, Y') : null;
$waterGoal = (int) ($user['daily_water_oz'] ?? 64);

$pageTitle = 'Weight & BMI';
include __DIR__ . '/../includes/header.php';
?>

<div class="insights-page">
    <div class="settings-detail-header">
        <a href="/challenge/app/mood_stats.php" class="settings-back-link">
            <i data-lucide="chevron-left"></i>
            <span>Insights</span>
        </a>
        <h1>Weight &amp; BMI</h1>
    </div>

    <div class="range-selector">
        <a href="?range=7" class="range-btn <?= $range === '7' ? 'active' : '' ?>">7D</a>
        <a href="?range=30" class="range-btn <?= $range === '30' ? 'active' : '' ?>">30D</a>
        <a href="?range=90" class="range-btn <?= $range === '90' ? 'active' : '' ?>">90D</a>
        <a href="?range=all" class="range-btn <?= $range === 'all' ? 'active' : '' ?>">All</a>
    </div>

    <div class="weight-update-card">
        <div>
            <h2>Update Weight</h2>
            <p>BMI and your daily water goal recalculate automatically.</p>
        </div>
        <form class="weight-update-form" id="insightsWeightForm">
            <div class="input-with-unit">
                <input
                    type="number"
                    id="insightsWeightInput"
                    min="50"
                    max="700"
                    step="0.1"
                    value="<?= h((string) ($latestWeight ?? '')) ?>"
                    placeholder="Weight"
                >
                <span class="input-unit">lbs</span>
            </div>
            <button type="submit" class="btn btn-primary">
                <i data-lucide="save"></i>
                Save
            </button>
        </form>
        <?php if (empty($user['height_inches'])): ?>
            <p class="form-hint">Add your height in Health & Water settings before logging weight so BMI can calculate.</p>
        <?php endif; ?>
    </div>

    <?php if ($weightDays === 0): ?>
        <div class="empty-state">
            <div class="empty-icon"><i data-lucide="scale"></i></div>
            <h3>No weight data yet</h3>
            <p>Log your current weight here to start tracking BMI over time.</p>
        </div>
    <?php else: ?>
        <div class="stats-cards">
            <div class="stat-card"><div class="stat-icon"><i data-lucide="scale"></i></div><div class="stat-info"><span class="stat-value"><?= number_format((float) $latestWeight, 1) ?></span><span class="stat-label">Current lbs</span></div></div>
            <div class="stat-card"><div class="stat-icon"><i data-lucide="activity"></i></div><div class="stat-info"><span class="stat-value"><?= number_format((float) $latestBmi, 1) ?></span><span class="stat-label">Current BMI</span></div></div>
            <div class="stat-card"><div class="stat-icon"><i data-lucide="<?= ($weightChange ?? 0) > 0 ? 'trending-up' : (($weightChange ?? 0) < 0 ? 'trending-down' : 'minus') ?>"></i></div><div class="stat-info"><span class="stat-value"><?= $weightChange !== null ? (($weightChange > 0 ? '+' : '') . number_format($weightChange, 1)) : '&mdash;' ?></span><span class="stat-label">Range change</span></div></div>
            <div class="stat-card"><div class="stat-icon"><i data-lucide="calendar-days"></i></div><div class="stat-info"><span class="stat-value"><?= $weightDays ?></span><span class="stat-label">Logged days</span></div></div>
            <div class="stat-card"><div class="stat-icon"><i data-lucide="droplets"></i></div><div class="stat-info"><span class="stat-value"><?= $waterGoal ?></span><span class="stat-label">Water goal</span></div></div>
            <div class="stat-card"><div class="stat-icon"><i data-lucide="clock"></i></div><div class="stat-info"><span class="stat-value stat-value-date"><?= h($latestWeightDate) ?></span><span class="stat-label">Latest log</span></div></div>
        </div>

        <div class="chart-section">
            <div class="chart-header">
                <h2>Weight &amp; BMI Over Time</h2>
            </div>
            <div class="chart-container">
                <canvas id="weightChart"></canvas>
            </div>
        </div>

        <div class="trend-analysis">
            <div class="trend-content">
                <div class="trend-badge neutral"><i data-lucide="scale" class="trend-icon"></i><span>Body metrics</span></div>
                <p>Your BMI updates whenever your weight changes, using the height saved in Health & Water settings.</p>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php if ($weightDays > 0): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const weightCtx = document.getElementById('weightChart').getContext('2d');
new Chart(weightCtx, {
    type: 'line',
    data: {
        labels: <?= json_encode($weightLabels) ?>,
        datasets: [{
            label: 'Weight (lbs)',
            data: <?= json_encode($weightData) ?>,
            borderColor: '#C4A35A',
            backgroundColor: 'rgba(15, 118, 110, 0.12)',
            borderWidth: 3,
            fill: true,
            tension: 0.35,
            pointRadius: 4,
            yAxisID: 'y'
        }, {
            label: 'BMI',
            data: <?= json_encode($bmiData) ?>,
            borderColor: '#7c3aed',
            backgroundColor: 'rgba(124, 58, 237, 0.08)',
            borderWidth: 2,
            fill: false,
            tension: 0.35,
            pointRadius: 4,
            yAxisID: 'y1'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: true, labels: { usePointStyle: true, boxWidth: 8 } } },
        scales: {
            y: { type: 'linear', position: 'left', ticks: { callback: value => value + ' lbs' }, grid: { color: 'rgba(0, 0, 0, 0.04)' } },
            y1: { type: 'linear', position: 'right', grid: { drawOnChartArea: false } },
            x: { ticks: { maxRotation: 0 }, grid: { display: false } }
        }
    }
});
</script>
<?php endif; ?>

<script>
document.getElementById('insightsWeightForm')?.addEventListener('submit', async function(event) {
    event.preventDefault();
    const input = document.getElementById('insightsWeightInput');
    const weightLbs = parseFloat(input ? input.value : '');
    if (!weightLbs || weightLbs < 50 || weightLbs > 700) {
        alert('Enter a valid weight between 50 and 700 lbs');
        input?.focus();
        return;
    }
    try {
        const response = await fetch('/challenge/api/weight_log.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({ weight_lbs: weightLbs })
        });
        const data = await response.json();
        if (data.success) {
            location.reload();
        } else {
            alert(data.error || 'Failed to save weight');
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Failed to save weight');
    }
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
