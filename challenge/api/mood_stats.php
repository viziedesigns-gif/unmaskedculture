<?php
/**
 * API: Get Mood Statistics
 * Kinto App
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/streak_service.php';

header('Content-Type: application/json');

// Require authentication
if (!isLoggedIn()) {
    jsonResponse(['success' => false, 'error' => 'Not authenticated'], 401);
}

$userId = getCurrentUserId();
$user = getCurrentUser();

// Get date range from query string
$range = $_GET['range'] ?? '30';
$validRanges = ['7', '30', '90', 'all'];
if (!in_array($range, $validRanges)) {
    $range = '30';
}

// Calculate date range
$timezone = $user['timezone'] ?? DEFAULT_TIMEZONE;
$userDate = computeUserDate($timezone);

if ($range === 'all') {
    $startDate = '2000-01-01';
} else {
    $startDate = (new DateTime($userDate))->modify("-{$range} days")->format('Y-m-d');
}

// Get mood entries for the date range
$moodEntries = dbFetchAll(
    "SELECT user_date, mood_level, notes 
     FROM mood_entries 
     WHERE user_id = ? AND user_date >= ? 
     ORDER BY user_date ASC",
    [$userId, $startDate]
);

// Calculate statistics
$totalEntries = count($moodEntries);
$moodValues = array_column($moodEntries, 'mood_level');

$avgMood = $totalEntries > 0 ? round(array_sum($moodValues) / $totalEntries, 1) : 0;
$highestMood = $totalEntries > 0 ? max($moodValues) : 0;
$lowestMood = $totalEntries > 0 ? min($moodValues) : 0;

// Calculate most common mood
$moodCounts = $totalEntries > 0 ? array_count_values($moodValues) : [];
$mostCommonMood = $totalEntries > 0 ? array_search(max($moodCounts), $moodCounts) : 0;

// Calculate trend (comparing last 7 days to previous 7 days)
$trend = 'stable';
$trendValue = 0;
if ($totalEntries >= 7) {
    $recentEntries = array_slice($moodEntries, -7);
    $recentAvg = array_sum(array_column($recentEntries, 'mood_level')) / count($recentEntries);
    
    if ($totalEntries >= 14) {
        $previousEntries = array_slice($moodEntries, -14, 7);
        $previousAvg = array_sum(array_column($previousEntries, 'mood_level')) / count($previousEntries);
        
        $trendValue = round($recentAvg - $previousAvg, 1);
        if ($trendValue > 0.5) {
            $trend = 'up';
        } elseif ($trendValue < -0.5) {
            $trend = 'down';
        }
    }
}

// Prepare chart data
$chartLabels = [];
$chartData = [];
foreach ($moodEntries as $entry) {
    $chartLabels[] = (new DateTime($entry['user_date']))->format('M j');
    $chartData[] = $entry['mood_level'];
}

jsonResponse([
    'success' => true,
    'range' => $range,
    'stats' => [
        'total_entries' => $totalEntries,
        'average_mood' => $avgMood,
        'highest_mood' => $highestMood,
        'lowest_mood' => $lowestMood,
        'most_common_mood' => $mostCommonMood,
        'trend' => $trend,
        'trend_value' => $trendValue
    ],
    'chart' => [
        'labels' => $chartLabels,
        'data' => $chartData
    ],
    'entries' => $moodEntries
]);
