<?php
/**
 * Dashboard - Daily Checklist
 * Kinto App
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/streak_service.php';
require_once __DIR__ . '/../includes/retention_service.php';
require_once __DIR__ . '/../includes/xp_service.php';

requireOnboarding();

ensureXpTablesAndColumns();

$user = getCurrentUser();
$userId = getCurrentUserId();
$timezone = $user['timezone'] ?? DEFAULT_TIMEZONE;
$allowGrace = checklistAllowsGracePeriod(normalizeChallengeMode($user['challenge_mode'] ?? null));

try {
    $checklistDateContext = resolveChecklistDate($timezone, $_GET['date'] ?? null, null, $allowGrace);
} catch (InvalidArgumentException $e) {
    $checklistDateContext = resolveChecklistDate($timezone, null, null, $allowGrace);
}
$selectedChecklistDate = $checklistDateContext['selected_date'];
$viewingYesterday = $checklistDateContext['is_yesterday'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'dismiss_milestone') {
    require_once __DIR__ . '/../includes/settings_handlers.php';
    processSettingsRequest($userId, $user);
}

// Check streak continuity only once per day (cached in session for performance)
$userLocalDate = computeUserDate($timezone);
$cacheKey = 'streak_checked_' . $userLocalDate;
$streakWasCheckedThisRequest = false;
if (!isset($_SESSION[$cacheKey])) {
    checkStreakContinuity($userId);
    $_SESSION[$cacheKey] = true;
    $streakWasCheckedThisRequest = true;
}

// Get streak status and checklist
$streakStatus = getStreakStatus($userId, false, $selectedChecklistDate);
$checklist = getTodayChecklist($userId, $selectedChecklistDate);

// Get water progress
$waterGoal = $user['daily_water_oz'] ?? 64;
$waterLogged = dbFetchOne(
    "SELECT COALESCE(SUM(oz_amount), 0) as total 
     FROM water_log 
     WHERE user_id = ? AND user_date = ?",
    [$userId, $streakStatus['user_date']]
);
$waterProgress = (int) ($waterLogged['total'] ?? 0);
$waterPercent = min(100, round(($waterProgress / $waterGoal) * 100));

// Get mood entry for today
$moodEntry = dbFetchOne(
    "SELECT * FROM mood_entries WHERE user_id = ? AND user_date = ?",
    [$userId, $streakStatus['user_date']]
);

// Get workout entry for today
$workoutOptions = getWorkoutTypeOptions();
$workoutEntry = getWorkoutForDate($userId, $streakStatus['user_date']);
$workoutLabel = $workoutEntry
    ? getWorkoutTypeLabel($workoutEntry['workout_type'], $workoutEntry['workout_custom'] ?? '')
    : '';

// Check journal preference
$journalInApp = $user['journal_in_app'] ?? 1;

$milestoneCelebration = getMilestoneCelebration(
    $userId,
    (int) ($streakStatus['current_streak'] ?? 0),
    $streakStatus['user_date']
);

$challengeMode = normalizeChallengeMode($streakStatus['challenge_mode'] ?? ($user['challenge_mode'] ?? 'intermediate'));
$calmPoints = (int) ($streakStatus['total_calm_points'] ?? 0);
$calmLevel = (int) ($streakStatus['calm_level'] ?? getCalmLevel($calmPoints));
$itemsDone = (int) ($streakStatus['items_completed'] ?? 0);
$itemsRequired = (int) ($streakStatus['items_required'] ?? 7);

// Helper function to get mood color (red to green gradient)
function getMoodColor(int $level): string {
    $colors = [
        1 => '#B45454',
        2 => '#C4784A',
        3 => '#C4924A',
        4 => '#C4A35A',
        5 => '#B8A46A',
        6 => '#9A9A6A',
        7 => '#7A9470',
        8 => '#6B8F71',
        9 => '#5A7F68',
        10 => '#4A6F5C',
    ];
    return $colors[$level] ?? '#C4A35A';
}

// Helper function to get mood label
function getMoodLabel(int $level): string {
    $labels = [
        1 => 'Very Low',
        2 => 'Low',
        3 => 'Below Average',
        4 => 'Slightly Low',
        5 => 'Neutral',
        6 => 'Slightly Good',
        7 => 'Good',
        8 => 'Great',
        9 => 'Excellent',
        10 => 'Amazing',
    ];
    return $labels[$level] ?? 'Neutral';
}

// Icons mapping (Lucide icon names)
$itemIcons = [
    'water' => 'droplets',
    'book' => 'book-open',
    'fitness' => 'dumbbell',
    'journal' => 'pen-line',
    'heart' => 'heart',
    'no-food' => 'utensils-crossed',
    'no-drink' => 'cup-soda',
    'scale' => 'scale'
];

$pageTitle = 'Daily Challenge';
$bodyClass = 'dashboard-page mode-' . $challengeMode;
include __DIR__ . '/../includes/header.php';
?>

<div class="dashboard">
    <!-- Streak Header -->
    <div class="streak-header">
        <div class="streak-display <?= $streakStatus['is_today_completed'] ? 'completed' : '' ?>">
            <div class="streak-flame <?= $streakStatus['current_streak'] > 0 ? 'active' : '' ?>">
                <i data-lucide="flame"></i>
            </div>
            <div class="streak-count"><?= $streakStatus['current_streak'] ?></div>
            <div class="streak-label">Day Streak</div>
        </div>
        
        <div class="streak-stats">
            <div class="stat">
                <span class="stat-value"><?= $streakStatus['streak_repairs'] ?></span>
                <span class="stat-label">Repairs</span>
            </div>
            <div class="stat calm-stat">
                <span class="stat-value" id="calmPointsTotal"><?= $calmPoints ?></span>
                <span class="stat-label">Calm Points · L<span id="calmLevel"><?= $calmLevel ?></span></span>
            </div>
            <div class="stat mode-stat">
                <span class="stat-value mode-badge"><?= $challengeMode === 'easy' ? 'Easy' : 'Intermediate' ?></span>
                <span class="stat-label">Mode</span>
            </div>
        </div>

        <?php if ($streakStatus['is_today_completed']): ?>
            <div class="day-complete-badge">
                <i data-lucide="check-circle" class="badge-icon"></i>
                <span><?= $challengeMode === 'easy' ? 'Day counted!' : 'Today Complete!' ?></span>
            </div>
        <?php else: ?>
            <div class="progress-ring-container">
                <div class="progress-ring" data-progress="<?= $itemsRequired > 0 ? round(($itemsDone / $itemsRequired) * 100) : 0 ?>">
                    <span class="progress-text"><?= $itemsDone ?>/<?= $itemsRequired ?></span>
                </div>
                <span class="progress-label">items today</span>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($streakStatus['streak_at_risk'] && !$streakStatus['is_today_completed']): ?>
        <div class="alert alert-warning streak-warning">
            <i data-lucide="alert-triangle" class="warning-icon"></i>
            <span><?= $challengeMode === 'easy'
                ? 'Your streak is at risk! Check in with at least one item before 1:00 AM.'
                : 'Your streak is at risk! Complete all items before midnight to keep it going.' ?></span>
            <span class="time-remaining">
                <?= sprintf('%02d:%02d:%02d', 
                    $streakStatus['time_remaining']['hours'],
                    $streakStatus['time_remaining']['minutes'],
                    $streakStatus['time_remaining']['seconds']
                ) ?> remaining
            </span>
        </div>
    <?php endif; ?>

    <?php
    // Persistent broken/lost/never-started banner. Always visible above the
    // checklist until the user either uses a repair or restarts the challenge.
    $showBrokenBanner = (
        $streakStatus['streak_broken']
        || $streakStatus['streak_lost']
        || !empty($streakStatus['never_started'])
    ) && !$streakStatus['is_today_completed'];
    ?>
    <?php if ($showBrokenBanner): ?>
        <div class="streak-broken-banner" role="alert">
            <div class="streak-broken-banner__glyph">
                <i data-lucide="alert-octagon"></i>
            </div>
            <div class="streak-broken-banner__body">
                <?php if ($streakStatus['can_repair']): ?>
                    <h3 class="streak-broken-banner__title">Your streak is about to break</h3>
                    <p class="streak-broken-banner__message">
                        You missed <strong><?= formatDate($streakStatus['missed_date'], 'l, F j') ?></strong>.
                        Use a streak repair now to save your <?= $streakStatus['current_streak'] ?>-day streak,
                        or restart the challenge from Day 0.
                    </p>
                <?php elseif (!empty($streakStatus['never_started'])): ?>
                    <h3 class="streak-broken-banner__title">Your challenge hasn't started yet</h3>
                    <p class="streak-broken-banner__message">
                        It's been <strong><?= $streakStatus['days_missed'] ?> day<?= $streakStatus['days_missed'] === 1 ? '' : 's' ?></strong>
                        since you signed up and you haven't completed a day.
                        Restart to set today as Day 1, or <?= $challengeMode === 'easy' ? 'check at least one item' : 'complete all ' . $streakStatus['items_required'] . ' items' ?> below.
                    </p>
                <?php else: ?>
                    <h3 class="streak-broken-banner__title">Your streak is lost</h3>
                    <p class="streak-broken-banner__message">
                        You missed <?= $streakStatus['days_missed'] ?> day<?= $streakStatus['days_missed'] > 1 ? 's' : '' ?>
                        and your streak has reset.
                        <?php if ($streakStatus['streak_repairs'] > 0): ?>
                            Your <?= $streakStatus['streak_repairs'] ?> streak repair<?= $streakStatus['streak_repairs'] === 1 ? '' : 's' ?> can't cover this gap &mdash; restart to begin again.
                        <?php else: ?>
                            You have no streak repairs remaining &mdash; restart to begin again.
                        <?php endif; ?>
                    </p>
                <?php endif; ?>
                <div class="streak-broken-banner__actions">
                    <?php if ($streakStatus['can_repair']): ?>
                        <form action="/challenge/api/streak_action.php" method="POST" class="streak-broken-banner__form">
                            <input type="hidden" name="action" value="repair">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i data-lucide="shield"></i> Use Streak Repair (<?= $streakStatus['streak_repairs'] ?> left)
                            </button>
                        </form>
                        <button type="button" class="btn btn-outline btn-lg" onclick="openRestartModeModal()">
                            <i data-lucide="refresh-cw"></i> Restart Instead
                        </button>
                    <?php else: ?>
                        <button type="button" class="btn btn-primary btn-lg" onclick="openRestartModeModal()">
                            <i data-lucide="refresh-cw"></i>
                            <?= !empty($streakStatus['never_started']) ? 'Start Fresh (Day 1)' : 'Restart My Challenge' ?>
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($milestoneCelebration): ?>
        <div class="milestone-banner" role="status">
            <div class="milestone-banner-icon"><i data-lucide="trophy"></i></div>
            <div class="milestone-banner-copy">
                <h3><?= h($milestoneCelebration['title']) ?></h3>
                <p><?= h($milestoneCelebration['message']) ?></p>
                <?php if (!empty($milestoneCelebration['prompt_intermediate'])): ?>
                    <button type="button" class="btn btn-primary btn-sm" onclick="openRestartModeModal('intermediate')">
                        Start Intermediate
                    </button>
                <?php endif; ?>
            </div>
            <form method="POST" class="milestone-banner-dismiss">
                <input type="hidden" name="action" value="dismiss_milestone">
                <input type="hidden" name="milestone_day" value="<?= (int) $milestoneCelebration['day'] ?>">
                <button type="submit" class="btn btn-sm btn-secondary">Dismiss</button>
            </form>
        </div>
    <?php endif; ?>

    <!-- Daily Checklist -->
    <div class="checklist-section">
        <h2 class="section-title"><?= $viewingYesterday ? "Yesterday's Challenge" : "Today's Challenge" ?></h2>
        <p class="section-date"><?= formatDate($streakStatus['user_date'], 'l, F j, Y') ?></p>

        <?php if ($checklistDateContext['can_log_yesterday']): ?>
            <nav class="checklist-date-picker" aria-label="Choose checklist date">
                <a class="btn btn-sm <?= $viewingYesterday ? 'btn-primary' : 'btn-outline' ?>"
                   href="/challenge/app/dashboard.php?date=<?= h($checklistDateContext['yesterday']) ?>">Yesterday</a>
                <a class="btn btn-sm <?= !$viewingYesterday ? 'btn-primary' : 'btn-outline' ?>"
                   href="/challenge/app/dashboard.php?date=<?= h($checklistDateContext['today']) ?>">Today</a>
            </nav>
            <p class="checklist-grace-note">
                <?= $viewingYesterday ? 'You can finish yesterday\'s checklist until 1:00 AM.' : 'Yesterday is still available until 1:00 AM.' ?>
            </p>
        <?php endif; ?>
        
        <!-- Countdown to the selected checklist deadline -->
        <div class="midnight-countdown" id="midnightCountdown">
            <i data-lucide="clock"></i>
            <span id="countdownText">
                <?= sprintf('%02d:%02d:%02d', 
                    $streakStatus['time_remaining']['hours'],
                    $streakStatus['time_remaining']['minutes'],
                    $streakStatus['time_remaining']['seconds']
                ) ?> until the <?= h($streakStatus['deadline_label'] ?? ($challengeMode === 'easy' ? '1:00 AM' : 'midnight')) ?> deadline
            </span>
        </div>

        <div class="checklist">
            <?php foreach ($checklist as $item): ?>
                <?php if ($item['item_type'] === 'water_tracker'): ?>
                    <!-- Water Tracker Item -->
                    <div class="checklist-item water-item <?= $item['completed'] ? 'completed' : '' ?>" data-item-id="<?= $item['id'] ?>">
                        <div class="item-icon"><i data-lucide="<?= $itemIcons[$item['icon']] ?? 'check' ?>"></i></div>
                        <div class="item-content">
                            <div class="item-header">
                                <span class="item-name"><?= h($item['name']) ?></span>
                                <span class="item-goal"><?= $waterProgress ?> / <?= $waterGoal ?> oz</span>
                            </div>
                            <div class="water-visual" data-water-percent="<?= (int) $waterPercent ?>">
                                <canvas id="waterSceneCanvas" class="water-scene-canvas" width="320" height="96" aria-hidden="true"></canvas>
                                <div class="water-progress-bar water-progress-bar--fallback">
                                    <div class="water-fill" style="width: <?= $waterPercent ?>%"></div>
                                </div>
                            </div>
                            <?php
                            $bottleOz = (int) ($user['water_bottle_oz'] ?? 24);
                            $bottlePresets = [16, 24, 32];
                            $isBottleCustom = !in_array($bottleOz, $bottlePresets, true);
                            ?>
                            <div class="water-controls">
                                <?php if ($isBottleCustom): ?>
                                    <button type="button" class="btn btn-sm btn-water" onclick="logWater(<?= $bottleOz ?>)">+<?= $bottleOz ?>oz (Bottle)</button>
                                <?php else: ?>
                                    <?php foreach ($bottlePresets as $preset): ?>
                                        <button type="button" class="btn btn-sm btn-water" onclick="logWater(<?= $preset ?>)">+<?= $preset ?>oz</button>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                                <button type="button" class="btn btn-sm btn-water-custom" onclick="showWaterModal()">Custom</button>
                            </div>
                        </div>
                        <div class="item-check <?= $item['completed'] ? 'checked' : '' ?>">
                            <?php if ($item['completed']): ?><i data-lucide="check"></i><?php endif; ?>
                        </div>
                    </div>
                <?php elseif ($item['item_type'] === 'mood_tracker'): ?>
                    <!-- Mood / Journal Item with Slider -->
                    <div class="checklist-item mood-item <?= $item['completed'] ? 'completed' : '' ?>" data-item-id="<?= $item['id'] ?>">
                        <div class="item-icon"><i data-lucide="<?= $itemIcons[$item['icon']] ?? 'check' ?>"></i></div>
                        <div class="item-content">
                            <div class="item-header">
                                <span class="item-name"><?= h($item['name']) ?></span>
                            </div>
                            <?php if ($moodEntry): ?>
                                <div class="mood-display-slider">
                                    <div class="mood-value-badge" style="--mood-color: <?= getMoodColor($moodEntry['mood_level']) ?>">
                                        <?= $moodEntry['mood_level'] ?>/10
                                    </div>
                                    <span class="mood-label"><?= getMoodLabel($moodEntry['mood_level']) ?></span>
                                </div>
                            <?php else: ?>
                                <button type="button" class="btn btn-sm btn-mood" onclick="showMoodModal()">
                                    <?= $journalInApp ? 'Log Mood & Journal' : 'Log Mood' ?>
                                </button>
                            <?php endif; ?>
                        </div>
                        <div class="item-check <?= $item['completed'] ? 'checked' : '' ?>">
                            <?php if ($item['completed']): ?><i data-lucide="check"></i><?php endif; ?>
                        </div>
                    </div>
                <?php elseif ((int) $item['id'] === 3): ?>
                    <!-- Workout Item with Activity Selection -->
                    <div class="checklist-item workout-item <?= $item['completed'] ? 'completed' : '' ?>" data-item-id="<?= $item['id'] ?>">
                        <div class="item-icon"><i data-lucide="<?= $itemIcons[$item['icon']] ?? 'dumbbell' ?>"></i></div>
                        <div class="item-content">
                            <div class="item-header">
                                <span class="item-name"><?= h($item['name']) ?></span>
                                <span class="item-goal">30 min</span>
                            </div>
                            <?php if ($workoutEntry): ?>
                                <div class="workout-summary">
                                    <span><?= h($workoutLabel) ?></span>
                                    <button type="button" class="btn btn-sm btn-outline" onclick="showWorkoutModal()">Change</button>
                                </div>
                            <?php else: ?>
                                <button type="button" class="btn btn-sm btn-workout" onclick="showWorkoutModal()">
                                    Log Workout
                                </button>
                            <?php endif; ?>
                        </div>
                        <div class="item-check <?= $item['completed'] ? 'checked' : '' ?>">
                            <?php if ($item['completed']): ?><i data-lucide="check"></i><?php endif; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- Regular Checkbox Item -->
                    <div class="checklist-item <?= $item['completed'] ? 'completed' : '' ?>" 
                         data-item-id="<?= $item['id'] ?>"
                         onclick="toggleItem(<?= $item['id'] ?>, <?= $item['completed'] ? 'false' : 'true' ?>)">
                        <div class="item-icon"><i data-lucide="<?= $itemIcons[$item['icon']] ?? 'check' ?>"></i></div>
                        <div class="item-content">
                            <span class="item-name"><?= h($item['name']) ?></span>
                        </div>
                        <div class="item-check <?= $item['completed'] ? 'checked' : '' ?>">
                            <?php if ($item['completed']): ?><i data-lucide="check"></i><?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Workout Modal -->
<div id="workoutModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Log Your 30-Minute Workout</h3>
            <button type="button" class="modal-close" onclick="closeModal('workoutModal')">&times;</button>
        </div>
        <div class="modal-body">
            <div class="form-group">
                <label for="workoutType">What did you do?</label>
                <select id="workoutType" class="form-select" onchange="toggleCustomWorkout()">
                    <option value="">Select activity</option>
                    <?php foreach ($workoutOptions as $value => $label): ?>
                        <option value="<?= h($value) ?>" <?= $workoutEntry && $workoutEntry['workout_type'] === $value ? 'selected' : '' ?>>
                            <?= h($label) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" id="customWorkoutGroup" <?= $workoutEntry && $workoutEntry['workout_type'] === 'custom' ? '' : 'hidden' ?>>
                <label for="customWorkout">Custom activity</label>
                <input
                    type="text"
                    id="customWorkout"
                    class="form-input"
                    maxlength="100"
                    value="<?= h($workoutEntry['workout_custom'] ?? '') ?>"
                    placeholder="Example: pickleball, hiking, rowing"
                >
            </div>
            <input type="hidden" id="workoutDuration" value="<?= h((string) ($workoutEntry['duration_minutes'] ?? 30)) ?>">
            <p class="form-hint">This completes your 30-minute workout checklist item and adds the activity to today's journal stats.</p>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal('workoutModal')">Cancel</button>
            <button type="button" class="btn btn-primary" onclick="submitWorkout()">Save Workout</button>
        </div>
    </div>
</div>

<!-- Water Modal -->
<div id="waterModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Log Water Intake</h3>
            <button type="button" class="modal-close" onclick="closeModal('waterModal')">&times;</button>
        </div>
        <div class="modal-body">
            <div class="form-group">
                <label for="waterAmount">Amount (oz)</label>
                <input type="number" id="waterAmount" min="1" max="128" value="8" class="form-input">
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal('waterModal')">Cancel</button>
            <button type="button" class="btn btn-primary" onclick="submitWater()">Add Water</button>
        </div>
    </div>
</div>

<!-- Mood Modal -->
<div id="moodModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>How are you feeling today?</h3>
            <button type="button" class="modal-close" onclick="closeModal('moodModal')">&times;</button>
        </div>
        <div class="modal-body">
            <div class="mood-slider-container">
                <div class="mood-value-display" id="moodValueDisplay">5</div>
                <div class="mood-label-display" id="moodLabelDisplay">Neutral</div>
                <div class="mood-slider-wrapper">
                    <input type="range" 
                           id="moodSlider" 
                           name="mood" 
                           min="1" 
                           max="10" 
                           value="5" 
                           class="mood-slider"
                           oninput="updateMoodDisplay(this.value)">
                    <div class="mood-slider-labels">
                        <span>1</span>
                        <span>2</span>
                        <span>3</span>
                        <span>4</span>
                        <span>5</span>
                        <span>6</span>
                        <span>7</span>
                        <span>8</span>
                        <span>9</span>
                        <span>10</span>
                    </div>
                </div>
                <div class="mood-endpoints">
                    <span class="endpoint low">Low</span>
                    <span class="endpoint high">High</span>
                </div>
            </div>
            <div class="form-group" id="moodNotesGroup" hidden>
                <label for="moodNotes">Journal Entry</label>
                <button type="button" class="btn btn-secondary btn-block" id="openJournalFullscreenBtn" onclick="openJournalFullscreen()">
                    <i data-lucide="maximize-2"></i> Write full-screen
                </button>
                <textarea id="moodNotes" rows="3" class="form-textarea" placeholder="Tap Write full-screen to draft your entry..." <?= $journalInApp ? 'required' : '' ?> readonly onclick="openJournalFullscreen()"></textarea>
            </div>
            <?php if (!$journalInApp): ?>
                <div class="external-journal-note">
                    <i data-lucide="book-open"></i>
                    <span>Log your mood here, then complete your written journal outside the app.</span>
                </div>
            <?php endif; ?>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal('moodModal')">Cancel</button>
            <button type="button" class="btn btn-primary" onclick="<?= $journalInApp ? 'advanceMoodToJournal()' : 'submitMood()' ?>">
                <?= $journalInApp ? 'Next: Journal' : 'Save Mood' ?>
            </button>
        </div>
    </div>
</div>

<!-- Full-screen journal composer (dashboard mood modal) -->
<div id="journalFullscreen" class="journal-fullscreen" role="dialog" aria-modal="true" aria-labelledby="journalFullscreenTitle" hidden>
    <div class="journal-fullscreen__bar">
        <button type="button" class="btn btn-secondary btn-sm" onclick="closeJournalFullscreen()">Back</button>
        <span id="journalFullscreenTitle">Journal entry</span>
        <button type="button" class="btn btn-primary btn-sm" onclick="saveJournalFullscreen()">Save Entry</button>
    </div>
    <textarea id="journalFullscreenInput" class="journal-fullscreen__textarea" aria-label="Journal entry" placeholder="Write about your day, thoughts, gratitude, or anything you'd like to reflect on..."></textarea>
</div>

<!-- Day Complete Celebration -->
<div id="celebrationOverlay" class="celebration-overlay" style="display: none;">
    <div class="celebration-content">
        <div class="celebration-icon"><i data-lucide="party-popper"></i></div>
        <h2>Day Complete!</h2>
        <p id="celebrationCopy"><?= $challengeMode === 'easy'
            ? 'Today counts — you showed up. Keep building your path.'
            : 'You\'ve completed all ' . (int) $streakStatus['items_required'] . ' required items today!' ?></p>
        <div class="streak-update">
            <i data-lucide="flame" class="fire"></i>
            <span class="streak-number" id="newStreakNumber"></span>
            <span class="streak-text">Day Streak</span>
        </div>
        <button type="button" class="btn btn-primary" onclick="closeCelebration()">Awesome!</button>
    </div>
</div>

<?php
// Show the streak break modal whenever the user is in a broken / lost / never-started
// state. We no longer suppress it per-session on the dashboard so the user can't
// scroll past the warning without acting on it (repair or restart).
$showStreakBreakModal = (
    $streakStatus['streak_broken']
    || $streakStatus['streak_lost']
    || !empty($streakStatus['never_started'])
) && !$streakStatus['is_today_completed'];
?>

<?php if ($showStreakBreakModal): ?>
<!-- Streak Break Modal -->
<div id="streakBreakModal" class="modal streak-break-modal active">
    <div class="modal-content">
        <div class="modal-body streak-break-content">
            <div class="streak-break-icon">
                <i data-lucide="alert-triangle"></i>
            </div>

            <?php if ($streakStatus['can_repair']): ?>
                <!-- Can use streak repair -->
                <h2>Streak at Risk!</h2>
                <p class="streak-break-message">
                    You missed your daily check-in on <strong><?= formatDate($streakStatus['missed_date'], 'l, F j') ?></strong>.
                </p>
                <div class="streak-break-info">
                    <div class="info-row">
                        <span class="info-label">Current Streak</span>
                        <span class="info-value"><?= $streakStatus['current_streak'] ?> days</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Streak Repairs Available</span>
                        <span class="info-value"><?= $streakStatus['streak_repairs'] ?></span>
                    </div>
                </div>
                <p class="streak-break-hint">Use a streak repair to save your progress and continue your 77-day challenge!</p>
                <div class="streak-break-actions">
                    <form action="/challenge/api/streak_action.php" method="POST" class="streak-action-form">
                        <input type="hidden" name="action" value="repair">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i data-lucide="shield"></i> Use Streak Repair (<?= $streakStatus['streak_repairs'] ?> left)
                        </button>
                    </form>
                    <button type="button" class="btn btn-secondary btn-lg" onclick="openRestartModeModal()">
                        <i data-lucide="refresh-cw"></i> Restart Challenge Instead
                    </button>
                </div>
            <?php elseif (!empty($streakStatus['never_started'])): ?>
                <!-- Account is stale and user has never completed a day -->
                <h2>Your Challenge Hasn't Started</h2>
                <p class="streak-break-message">
                    It's been <strong><?= $streakStatus['days_missed'] ?> day<?= $streakStatus['days_missed'] === 1 ? '' : 's' ?></strong> since you signed up and you haven't completed a day yet.
                </p>
                <div class="streak-break-info lost">
                    <div class="info-row">
                        <span class="info-label">Current Streak</span>
                        <span class="info-value">0 days</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Streak Repairs</span>
                        <span class="info-value"><?= $streakStatus['streak_repairs'] ?></span>
                    </div>
                </div>
                <p class="streak-break-hint">Restart to set today as Day 1, or <?= $challengeMode === 'easy' ? 'check at least one item' : 'complete all ' . (int) $streakStatus['items_required'] . ' items' ?> below to begin.</p>
                <div class="streak-break-actions">
                    <button type="button" class="btn btn-primary btn-lg" onclick="openRestartModeModal()">
                        <i data-lucide="refresh-cw"></i> Start Fresh (Day 1)
                    </button>
                    <button type="button" class="btn btn-secondary btn-lg" onclick="closeStreakBreakModal()">
                        I'll Complete Today Instead
                    </button>
                </div>
            <?php else: ?>
                <!-- Streak lost, no repairs available -->
                <h2>Streak Lost</h2>
                <p class="streak-break-message">
                    You missed <?= $streakStatus['days_missed'] ?> day<?= $streakStatus['days_missed'] > 1 ? 's' : '' ?>
                    <?php if ($streakStatus['streak_repairs'] <= 0): ?>
                        and have no streak repairs remaining.
                    <?php else: ?>
                        &mdash; too many to repair.
                    <?php endif; ?>
                </p>
                <div class="streak-break-info lost">
                    <div class="info-row">
                        <span class="info-label">Previous Streak</span>
                        <span class="info-value"><?= $streakStatus['current_streak'] ?> days</span>
                    </div>
                </div>
                <p class="streak-break-hint">Don't worry &mdash; every day is a chance to start fresh!</p>
                <div class="streak-break-actions">
                    <button type="button" class="btn btn-primary btn-lg" onclick="openRestartModeModal()">
                        <i data-lucide="refresh-cw"></i> Restart My Challenge
                    </button>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Restart with mode selection -->
<div id="restartModeModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Restart the 77-Day Challenge?</h3>
            <button type="button" class="modal-close" onclick="closeRestartModeModal()">&times;</button>
        </div>
        <form method="POST" action="/challenge/api/streak_action.php" id="restartModeForm">
            <input type="hidden" name="action" value="restart">
            <div class="modal-body">
                <p>This resets your streak to Day 0. Lifetime Calm Points and journal history stay.</p>
                <fieldset class="mode-select-fieldset">
                    <legend>Choose mode for this run</legend>
                    <label class="mode-radio">
                        <input type="radio" name="challenge_mode" value="easy" id="restartModeEasy">
                        <span><strong>Easy</strong> — 1+ items advances the day</span>
                    </label>
                    <label class="mode-radio">
                        <input type="radio" name="challenge_mode" value="intermediate" id="restartModeIntermediate" checked>
                        <span><strong>Intermediate</strong> — all items required</span>
                    </label>
                </fieldset>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeRestartModeModal()">Cancel</button>
                <button type="submit" class="btn btn-danger">
                    <i data-lucide="refresh-cw"></i> Restart
                </button>
            </div>
        </form>
    </div>
</div>

<div id="xpToast" class="xp-toast" aria-live="polite" hidden></div>

<script>
const WATER_ITEM_ID = 1;
const WORKOUT_ITEM_ID = 3;
const MOOD_ITEM_ID = 4;
const CHECKLIST_DATE = <?= json_encode($selectedChecklistDate) ?>;
const JOURNAL_IN_APP = <?= $journalInApp ? 'true' : 'false' ?>;
const NEXT_MIDNIGHT_UTC = <?= json_encode($streakStatus['next_midnight_utc']) ?>;
const CHALLENGE_MODE = <?= json_encode($challengeMode) ?>;
const DEADLINE_LABEL = <?= json_encode($streakStatus['deadline_label'] ?? ($challengeMode === 'easy' ? '1:00 AM' : 'midnight')) ?>;
const ITEMS_REQUIRED = <?= (int) $itemsRequired ?>;

function closeStreakBreakModal() {
    var m = document.getElementById('streakBreakModal');
    if (m) { m.classList.remove('active'); m.style.display = 'none'; }
}

function openRestartModeModal(preselect) {
    const modal = document.getElementById('restartModeModal');
    if (!modal) return;
    const easy = document.getElementById('restartModeEasy');
    const mid = document.getElementById('restartModeIntermediate');
    if (preselect === 'easy' && easy) easy.checked = true;
    else if (preselect === 'intermediate' && mid) mid.checked = true;
    modal.classList.add('active');
    closeStreakBreakModal();
    if (typeof lucide !== 'undefined') lucide.createIcons();
}

function closeRestartModeModal() {
    const modal = document.getElementById('restartModeModal');
    if (modal) modal.classList.remove('active');
}

function showXpToast(xp) {
    if (!xp || !xp.awarded) return;
    const el = document.getElementById('xpToast');
    if (!el) return;
    const sign = xp.awarded > 0 ? '+' : '';
    el.textContent = `${sign}${xp.awarded} Calm Points`;
    el.hidden = false;
    el.classList.add('show');
    const totalEl = document.getElementById('calmPointsTotal');
    const levelEl = document.getElementById('calmLevel');
    if (totalEl && typeof xp.total === 'number') totalEl.textContent = xp.total;
    if (levelEl && typeof xp.level === 'number') levelEl.textContent = xp.level;
    clearTimeout(showXpToast._t);
    showXpToast._t = setTimeout(() => {
        el.classList.remove('show');
        el.hidden = true;
    }, 2200);
}

function updatePartialDayStrip(streak) {
    const countEl = document.getElementById('partialDayCount');
    const hintEl = document.getElementById('partialDayHint');
    const strip = document.getElementById('partialDayStrip');
    const fill = strip ? strip.querySelector('.partial-day-strip__fill') : null;
    if (!streak || !countEl) return;
    const done = streak.items_completed || 0;
    const req = streak.items_required || ITEMS_REQUIRED;
    countEl.textContent = `${done} / ${req} done`;
    if (fill) fill.style.width = `${req ? Math.min(100, Math.round((done / req) * 100)) : 0}%`;
    const counts = !!streak.is_today_completed;
    if (strip) strip.classList.toggle('is-counting', counts);
    if (hintEl) {
        if (counts) {
            hintEl.textContent = CHALLENGE_MODE === 'easy'
                ? 'This day counts toward your 77.'
                : 'Full protocol complete for today.';
        } else if (CHALLENGE_MODE === 'easy') {
            hintEl.textContent = 'Check at least one item and this day will count.';
        } else {
            hintEl.textContent = 'Finish all required items for today to count.';
        }
    }
}

function updateChallengePath(streakDays) {
    const nodes = document.querySelectorAll('#challengePathTrack .path-node');
    const day = Math.min(77, streakDays || 0);
    nodes.forEach((node) => {
        const d = parseInt(node.dataset.day, 10);
        node.classList.toggle('filled', d <= day);
        node.classList.toggle('current', d === day);
    });
    const track = document.getElementById('challengePathTrack');
    const current = track ? track.querySelector('.path-node.current') : null;
    if (current && track) {
        const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        current.scrollIntoView({ inline: 'center', block: 'nearest', behavior: reduceMotion ? 'auto' : 'smooth' });
    }
}

// Mood labels for 1-10 scale
const moodLabels = {
    1: 'Very Low',
    2: 'Low',
    3: 'Below Average',
    4: 'Slightly Low',
    5: 'Neutral',
    6: 'Slightly Good',
    7: 'Good',
    8: 'Great',
    9: 'Excellent',
    10: 'Amazing'
};

const moodColors = {
    1: '#B45454',
    2: '#C4784A',
    3: '#C4924A',
    4: '#C4A35A',
    5: '#B8A46A',
    6: '#9A9A6A',
    7: '#7A9470',
    8: '#6B8F71',
    9: '#5A7F68',
    10: '#4A6F5C'
};

// Update mood display when slider changes
function updateMoodDisplay(value) {
    const valueDisplay = document.getElementById('moodValueDisplay');
    const labelDisplay = document.getElementById('moodLabelDisplay');
    const slider = document.getElementById('moodSlider');
    const color = moodColors[value] || '#C4A35A';
    
    valueDisplay.textContent = value;
    labelDisplay.textContent = moodLabels[value] || 'Neutral';
    valueDisplay.style.setProperty('--mood-color', color);
    valueDisplay.style.background = color;
    if (slider) {
        slider.style.setProperty('--mood-color', color);
    }
}

function showWorkoutModal() {
    document.getElementById('workoutModal').classList.add('active');
    toggleCustomWorkout();
}

function toggleCustomWorkout() {
    const workoutType = document.getElementById('workoutType');
    const customGroup = document.getElementById('customWorkoutGroup');
    if (!workoutType || !customGroup) return;

    customGroup.hidden = workoutType.value !== 'custom';
}

async function submitWorkout() {
    const workoutType = document.getElementById('workoutType').value;
    const customWorkout = document.getElementById('customWorkout').value.trim();
    const durationMinutes = parseInt(document.getElementById('workoutDuration').value, 10) || 30;

    if (!workoutType) {
        alert('Choose a workout type');
        return;
    }

    if (workoutType === 'custom' && !customWorkout) {
        alert('Enter your custom workout');
        return;
    }

    try {
        const response = await fetch('/challenge/api/workout_log.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                workout_type: workoutType,
                workout_custom: customWorkout,
                duration_minutes: durationMinutes,
                user_date: CHECKLIST_DATE
            })
        });

        const data = await response.json();

        if (data.success) {
            updateWorkoutUI(data);
            updateUI(data);
            closeModal('workoutModal');
        } else {
            alert(data.error || 'Failed to save workout');
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Failed to save workout');
    }
}

// Toggle regular checklist item
async function toggleItem(itemId, checked) {
    try {
        const response = await fetch('/challenge/api/toggle_item.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({ item_id: itemId, checked: checked, user_date: CHECKLIST_DATE })
        });
        
        const data = await response.json();
        
        if (data.success) {
            updateUI(data);
        } else {
            alert(data.error || 'Failed to update item');
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Failed to update item');
    }
}

// Log water intake
async function logWater(amount) {
    try {
        const response = await fetch('/challenge/api/water_log.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({ amount: amount, user_date: CHECKLIST_DATE })
        });
        
        const data = await response.json();
        
        if (data.success) {
            updateWaterUI(data);
            updateUI(data);
        } else {
            alert(data.error || 'Failed to log water');
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Failed to log water');
    }
}

// Show water modal
function showWaterModal() {
    document.getElementById('waterModal').classList.add('active');
}

// Submit custom water amount
function submitWater() {
    const amount = parseInt(document.getElementById('waterAmount').value);
    if (amount > 0 && amount <= 128) {
        logWater(amount);
        closeModal('waterModal');
    }
}

// Show mood modal
function showMoodModal() {
    document.getElementById('moodModal').classList.add('active');
    const slider = document.getElementById('moodSlider');
    if (slider) {
        updateMoodDisplay(slider.value);
    }
}

function advanceMoodToJournal() {
    // Mood is intentionally chosen first; the selected slider value remains
    // in the modal while the journal composer takes over the second step.
    openJournalFullscreen();
}

let journalFullscreenReturnFocus = null;

function openJournalFullscreen() {
    const source = document.getElementById('moodNotes');
    const overlay = document.getElementById('journalFullscreen');
    const input = document.getElementById('journalFullscreenInput');
    if (!source || !overlay || !input) return;
    journalFullscreenReturnFocus = document.activeElement;
    input.value = source.value || '';
    overlay.hidden = false;
    document.body.classList.add('journal-fullscreen-open');
    setTimeout(() => input.focus(), 50);
}

function closeJournalFullscreen() {
    const source = document.getElementById('moodNotes');
    const overlay = document.getElementById('journalFullscreen');
    const input = document.getElementById('journalFullscreenInput');
    if (source && input) source.value = input.value;
    if (overlay) overlay.hidden = true;
    document.body.classList.remove('journal-fullscreen-open');
    if (journalFullscreenReturnFocus instanceof HTMLElement) {
        journalFullscreenReturnFocus.focus();
    }
}

function saveJournalFullscreen() {
    const source = document.getElementById('moodNotes');
    const input = document.getElementById('journalFullscreenInput');
    if (source && input) source.value = input.value;
    closeJournalFullscreen();
    submitMood();
}

document.addEventListener('keydown', function(event) {
    const overlay = document.getElementById('journalFullscreen');
    if (!overlay || overlay.hidden) return;
    if (event.key === 'Escape') {
        event.preventDefault();
        closeJournalFullscreen();
        return;
    }
    if (event.key !== 'Tab') return;
    const focusable = Array.from(overlay.querySelectorAll('button:not([disabled]), textarea:not([disabled])'));
    if (focusable.length === 0) return;
    const first = focusable[0];
    const last = focusable[focusable.length - 1];
    if (event.shiftKey && document.activeElement === first) {
        event.preventDefault();
        last.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first.focus();
    }
});

// Submit mood entry
async function submitMood() {
    const moodSlider = document.getElementById('moodSlider');
    const notesField = document.getElementById('moodNotes');
    const fullscreenInput = document.getElementById('journalFullscreenInput');
    if (notesField && fullscreenInput && !document.getElementById('journalFullscreen')?.hidden) {
        notesField.value = fullscreenInput.value;
        closeJournalFullscreen();
    }
    const notes = notesField ? notesField.value.trim() : '';
    
    const moodLevel = parseInt(moodSlider.value);

    if (JOURNAL_IN_APP && !notes) {
        alert('Please write a journal entry before submitting.');
        openJournalFullscreen();
        return;
    }
    
    try {
        const response = await fetch('/challenge/api/mood_entry.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({ 
                mood_level: moodLevel,
                notes: notes,
                external_journal: !JOURNAL_IN_APP,
                user_date: CHECKLIST_DATE
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            closeModal('moodModal');
            location.reload(); // Refresh to show updated mood
        } else {
            alert(data.error || 'Failed to save mood');
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Failed to save mood');
    }
}

// Close modal
function closeModal(modalId) {
    document.getElementById(modalId).classList.remove('active');
}

// Update water UI
function updateWaterUI(data) {
    const waterItem = document.querySelector('.water-item');
    if (!waterItem) return;
    const goalSpan = waterItem.querySelector('.item-goal');
    const fillBar = waterItem.querySelector('.water-fill');
    const visual = waterItem.querySelector('.water-visual');
    
    if (goalSpan) goalSpan.textContent = `${data.water_progress} / ${data.water_goal} oz`;
    if (fillBar) fillBar.style.width = `${data.water_percent}%`;
    if (visual) visual.dataset.waterPercent = String(data.water_percent);
    if (window.WaterScene && typeof window.WaterScene.setLevel === 'function') {
        window.WaterScene.setLevel(data.water_percent, true);
    }
    
    if (data.water_complete) {
        waterItem.classList.add('completed');
        const check = waterItem.querySelector('.item-check');
        if (check) {
            check.classList.add('checked');
            check.innerHTML = '<i data-lucide="check"></i>';
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        }
    }
}

function updateWorkoutUI(data) {
    const workoutItem = document.querySelector('.workout-item');
    if (!workoutItem || !data.workout) return;

    workoutItem.classList.add('completed');
    const check = workoutItem.querySelector('.item-check');
    if (check) {
        check.classList.add('checked');
        check.innerHTML = '<i data-lucide="check"></i>';
    }

    const content = workoutItem.querySelector('.item-content');
    const header = content ? content.querySelector('.item-header') : null;
    if (content && header) {
        const existingSummary = content.querySelector('.workout-summary');
        const existingButton = content.querySelector('.btn-workout');
        if (existingSummary) existingSummary.remove();
        if (existingButton) existingButton.remove();

        header.insertAdjacentHTML('afterend', `
            <div class="workout-summary">
                <span>${escapeHtml(data.workout.label)}</span>
                <button type="button" class="btn btn-sm btn-outline" onclick="showWorkoutModal()">Change</button>
            </div>
        `);
    }

    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
}

// Update UI after changes
function updateUI(data) {
    if (!data || !data.streak) return;

    const streakCount = document.querySelector('.streak-count');
    if (streakCount) streakCount.textContent = data.streak.current_streak;
    const repairsEl = document.querySelector('.streak-stats .stat-value');
    if (repairsEl) repairsEl.textContent = data.streak.streak_repairs;

    const xp = data.xp || data.streak.xp;
    if (xp) showXpToast(xp);

    updatePartialDayStrip(data.streak);
    updateChallengePath(data.streak.current_streak);
    
    // Update progress
    const progressRing = document.querySelector('.progress-ring');
    if (progressRing) {
        const progress = Math.round((data.streak.items_completed / data.streak.items_required) * 100);
        progressRing.dataset.progress = progress;
        const pt = progressRing.querySelector('.progress-text');
        if (pt) {
            pt.textContent = `${data.streak.items_completed}/${data.streak.items_required}`;
        }
    }
    
    // Update item UI
    if (data.item_id) {
        const item = document.querySelector(`[data-item-id="${data.item_id}"]`);
        if (item && !item.classList.contains('water-item') && !item.classList.contains('mood-item') && !item.classList.contains('workout-item')) {
            if (data.checked) {
                item.classList.add('completed');
                item.querySelector('.item-check').classList.add('checked');
                item.querySelector('.item-check').innerHTML = '<i data-lucide="check"></i>';
                if (typeof lucide !== 'undefined') {
                    lucide.createIcons();
                }
                item.onclick = () => toggleItem(data.item_id, false);
            } else {
                item.classList.remove('completed');
                item.querySelector('.item-check').classList.remove('checked');
                item.querySelector('.item-check').innerHTML = '';
                item.onclick = () => toggleItem(data.item_id, true);
            }
        }
    }
    
    // Show celebration if day just completed
    if (data.day_just_completed) {
        showCelebration(data.streak.current_streak);
    }
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text || '';
    return div.innerHTML;
}

// Show celebration overlay
function showCelebration(streak) {
    document.getElementById('newStreakNumber').textContent = streak;
    document.getElementById('celebrationOverlay').style.display = 'flex';
}

// Close celebration
function closeCelebration() {
    document.getElementById('celebrationOverlay').style.display = 'none';
    location.reload();
}

// Close modal on outside click
document.querySelectorAll('.modal').forEach(modal => {
    modal.addEventListener('click', (e) => {
        if (e.target === modal) {
            modal.classList.remove('active');
        }
    });
});

// Live countdown to the selected checklist deadline
function startMidnightCountdown() {
    const countdownEl = document.getElementById('countdownText');
    if (!countdownEl) return;
    const midnight = new Date(NEXT_MIDNIGHT_UTC);
    
    function updateCountdown() {
        const now = new Date();
        const diff = midnight - now;
        
        if (diff <= 0) {
            // Midnight reached, refresh the page
            location.reload();
            return;
        }
        
        const hours = Math.floor(diff / (1000 * 60 * 60));
        const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
        const seconds = Math.floor((diff % (1000 * 60)) / 1000);
        
        const timeStr = String(hours).padStart(2, '0') + ':' + 
                       String(minutes).padStart(2, '0') + ':' + 
                       String(seconds).padStart(2, '0');
        
        countdownEl.textContent = timeStr + ' until the ' + DEADLINE_LABEL + ' deadline';
    }
    
    updateCountdown();
    setInterval(updateCountdown, 1000);
}

startMidnightCountdown();

// Initialize mood display
if (document.getElementById('moodSlider')) {
    updateMoodDisplay(document.getElementById('moodSlider').value);
}

// Initialize Lucide icons for modal
if (typeof lucide !== 'undefined') {
    lucide.createIcons();
}
</script>
<script type="importmap">
{
  "imports": {
    "three": "https://unpkg.com/three@0.160.0/build/three.module.js"
  }
}
</script>
<script type="module" src="<?= h(assetUrl('/challenge/assets/js/water-scene.js')) ?>"></script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
