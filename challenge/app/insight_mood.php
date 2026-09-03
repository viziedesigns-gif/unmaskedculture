<?php
/**
 * Mood Insights
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

$moodEntries = dbFetchAll(
    "SELECT user_date, mood_level, notes
     FROM mood_entries
     WHERE user_id = ? AND user_date >= ?
     ORDER BY user_date ASC",
    [$userId, $startDate]
);

$totalEntries = count($moodEntries);
$moodValues = array_column($moodEntries, 'mood_level');
$avgMood = $totalEntries > 0 ? round(array_sum($moodValues) / $totalEntries, 1) : 0;
$highestMood = $totalEntries > 0 ? max($moodValues) : 0;
$lowestMood = $totalEntries > 0 ? min($moodValues) : 0;
$moodCounts = $totalEntries > 0 ? array_count_values($moodValues) : [];
$mostCommonMood = $totalEntries > 0 ? array_search(max($moodCounts), $moodCounts, true) : 0;

$trend = 'stable';
$trendValue = 0;
$trendReason = 'not_enough_data';
if ($totalEntries >= 7) {
    $recentEntries = array_slice($moodEntries, -7);
    $recentAvg = array_sum(array_column($recentEntries, 'mood_level')) / count($recentEntries);
    if ($totalEntries >= 14) {
        $previousEntries = array_slice($moodEntries, -14, 7);
        $previousAvg = array_sum(array_column($previousEntries, 'mood_level')) / count($previousEntries);
        $trendValue = round($recentAvg - $previousAvg, 1);
        if ($trendValue > 0.5) {
            $trend = 'up';
            $trendReason = 'up';
        } elseif ($trendValue < -0.5) {
            $trend = 'down';
            $trendReason = 'down';
        } else {
            $trendReason = 'stable';
        }
    } else {
        $trendReason = 'first_week';
    }
}

$chartLabels = [];
$chartData = [];
foreach ($moodEntries as $entry) {
    $chartLabels[] = (new DateTime($entry['user_date']))->format('M j');
    $chartData[] = (int) $entry['mood_level'];
}

function getMoodInsightColor(int $level): string {
    $colors = [
        1 => '#ef4444', 2 => '#f97316', 3 => '#fb923c', 4 => '#fbbf24', 5 => '#facc15',
        6 => '#a3e635', 7 => '#84cc16', 8 => '#22c55e', 9 => '#16a34a', 10 => '#15803d',
    ];
    return $colors[$level] ?? '#facc15';
}

$pageTitle = 'Mood Insights';
include __DIR__ . '/../includes/header.php';
?>

<div class="insights-page">
    <div class="settings-detail-header">
        <a href="/challenge/app/mood_stats.php" class="settings-back-link">
            <i data-lucide="chevron-left"></i>
            <span>Insights</span>
        </a>
        <h1>Mood Insights</h1>
    </div>

    <div class="range-selector">
        <a href="?range=7" class="range-btn <?= $range === '7' ? 'active' : '' ?>">7D</a>
        <a href="?range=30" class="range-btn <?= $range === '30' ? 'active' : '' ?>">30D</a>
        <a href="?range=90" class="range-btn <?= $range === '90' ? 'active' : '' ?>">90D</a>
        <a href="?range=all" class="range-btn <?= $range === 'all' ? 'active' : '' ?>">All</a>
    </div>

    <?php if ($totalEntries === 0): ?>
        <div class="empty-state">
            <div class="empty-icon"><i data-lucide="bar-chart-3"></i></div>
            <h3>No mood data yet</h3>
            <p>Start logging your mood daily to see insights and trends here.</p>
            <a href="/challenge/app/journal.php" class="btn btn-primary">
                <i data-lucide="plus"></i>
                Log Today's Mood
            </a>
        </div>
    <?php else: ?>
        <div class="stats-cards">
            <div class="stat-card">
                <div class="stat-icon"><i data-lucide="activity"></i></div>
                <div class="stat-info">
                    <span class="stat-value" style="color: <?= getMoodInsightColor((int) round($avgMood)) ?>"><?= $avgMood ?></span>
                    <span class="stat-label">Average</span>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i data-lucide="arrow-up-circle"></i></div>
                <div class="stat-info">
                    <span class="stat-value" style="color: <?= getMoodInsightColor((int) $highestMood) ?>"><?= $highestMood ?></span>
                    <span class="stat-label">Highest</span>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i data-lucide="arrow-down-circle"></i></div>
                <div class="stat-info">
                    <span class="stat-value" style="color: <?= getMoodInsightColor((int) $lowestMood) ?>"><?= $lowestMood ?></span>
                    <span class="stat-label">Lowest</span>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i data-lucide="target"></i></div>
                <div class="stat-info">
                    <span class="stat-value" style="color: <?= getMoodInsightColor((int) $mostCommonMood) ?>"><?= $mostCommonMood ?></span>
                    <span class="stat-label">Common</span>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i data-lucide="calendar-days"></i></div>
                <div class="stat-info">
                    <span class="stat-value"><?= $totalEntries ?></span>
                    <span class="stat-label">Days</span>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i data-lucide="<?= $trend === 'up' ? 'trending-up' : ($trend === 'down' ? 'trending-down' : 'minus') ?>"></i></div>
                <div class="stat-info">
                    <span class="stat-value trend-<?= $trend ?>">
                        <?= $trend === 'up' ? '+' . abs($trendValue) : ($trend === 'down' ? '-' . abs($trendValue) : 'Stable') ?>
                    </span>
                    <span class="stat-label">7-day</span>
                </div>
            </div>
        </div>

        <div class="chart-section">
            <div class="chart-header">
                <h2>Mood Over Time</h2>
            </div>
            <div class="chart-container">
                <canvas id="moodChart"></canvas>
            </div>
        </div>

        <div class="trend-analysis">
            <div class="trend-content">
                <?php if ($trend === 'up'): ?>
                    <div class="trend-badge positive"><i data-lucide="trending-up" class="trend-icon"></i><span>Trending upward</span></div>
                    <p>Your average mood over the last 7 days is higher than the previous week. Keep it up!</p>
                <?php elseif ($trend === 'down'): ?>
                    <div class="trend-badge negative"><i data-lucide="trending-down" class="trend-icon"></i><span>Trending lower</span></div>
                    <p>Your mood has been lower recently. Consider reaching out to your Inner Circle for support.</p>
                <?php else: ?>
                    <div class="trend-badge neutral"><i data-lucide="minus" class="trend-icon"></i><span>Stable mood</span></div>
                    <?php if ($trendReason === 'first_week' || $trendReason === 'not_enough_data'): ?>
                        <p>Keep logging daily to build a fuller trend. Your current average is <?= $avgMood ?>/10.</p>
                    <?php elseif ($avgMood <= 4): ?>
                        <p>Your mood has been steady, but it is sitting on the lower side. Consider reaching out to your Inner Circle for support.</p>
                    <?php elseif ($avgMood >= 7): ?>
                        <p>Your mood has been steady in a strong range. Keep protecting the rhythms that are helping.</p>
                    <?php else: ?>
                        <p>Your mood has been steady near the middle. Keep watching what lifts your mood and what drains it.</p>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php if ($totalEntries > 0): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const ctx = document.getElementById('moodChart').getContext('2d');
const gradient = ctx.createLinearGradient(0, 0, 0, 400);
gradient.addColorStop(0, 'rgba(99, 102, 241, 0.3)');
gradient.addColorStop(1, 'rgba(99, 102, 241, 0.0)');
function getMoodPointColor(value) {
    const colors = {
        1: '#ef4444', 2: '#f97316', 3: '#fb923c', 4: '#fbbf24', 5: '#facc15',
        6: '#a3e635', 7: '#84cc16', 8: '#22c55e', 9: '#16a34a', 10: '#15803d'
    };
    return colors[Math.round(value)] || '#facc15';
}
const chartData = <?= json_encode($chartData) ?>;
new Chart(ctx, {
    type: 'line',
    data: {
        labels: <?= json_encode($chartLabels) ?>,
        datasets: [{
            label: 'Mood Level',
            data: chartData,
            borderColor: '#C4A35A',
            backgroundColor: gradient,
            borderWidth: 3,
            fill: true,
            tension: 0.4,
            pointBackgroundColor: chartData.map(val => getMoodPointColor(val)),
            pointBorderColor: chartData.map(val => getMoodPointColor(val)),
            pointRadius: 4,
            pointHoverRadius: 7
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
            y: { min: 1, max: 10, ticks: { stepSize: 1 }, grid: { color: 'rgba(0, 0, 0, 0.04)' } },
            x: { ticks: { maxRotation: 0 }, grid: { display: false } }
        },
        interaction: { intersect: false, mode: 'index' }
    }
});
</script>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
