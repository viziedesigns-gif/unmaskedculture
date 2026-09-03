<?php
/**
 * Journal Page - Dedicated mood tracking and journaling
 * Kinto App
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/streak_service.php';

requireOnboarding();

$user = getCurrentUser();
$userId = getCurrentUserId();
$journalInApp = $user['journal_in_app'] ?? 1;

expireStreakIfNeeded($userId);

$timezone = $user['timezone'] ?? DEFAULT_TIMEZONE;
$userDate = computeUserDate($timezone);

// Get today's mood entry if exists. Mood tracking is used for both
// in-app and external journaling so Insights stay available to everyone.
$moodEntry = dbFetchOne(
    "SELECT * FROM mood_entries WHERE user_id = ? AND user_date = ?",
    [$userId, $userDate]
);

// Get today's journal checklist status (for external journaling)
$journalChecklist = dbFetchOne(
    "SELECT * FROM daily_checklist_entries WHERE user_id = ? AND item_id = 4 AND user_date = ?",
    [$userId, $userDate]
);
$externalJournalCompleted = $journalChecklist && (bool) ($journalChecklist['value'] ?? false);

$todayWorkout = getWorkoutForDate($userId, $userDate);
$todayWorkoutLabel = $todayWorkout
    ? getWorkoutTypeLabel($todayWorkout['workout_type'], $todayWorkout['workout_custom'] ?? '')
    : '';
$todayWater = dbFetchOne(
    "SELECT COALESCE(SUM(oz_amount), 0) as total FROM water_log WHERE user_id = ? AND user_date = ?",
    [$userId, $userDate]
);
$todayWaterTotal = (int) ($todayWater['total'] ?? 0);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$moodEntry) {
        $moodLevel = (int) ($_POST['mood_level'] ?? 5);
        $notes = $journalInApp ? trim($_POST['notes'] ?? '') : '';
        
        if ($moodLevel < 1 || $moodLevel > 10) {
            $moodLevel = 5;
        }

        if ($journalInApp && $notes === '') {
            setFlash('error', 'Please write a journal entry before submitting.');
            redirect('/challenge/app/journal.php');
        }
        
        // Save mood entry (insert only - no updates)
        dbQuery(
            "INSERT INTO mood_entries (user_id, user_date, mood_level, notes, created_at_utc) 
             VALUES (?, ?, ?, ?, UTC_TIMESTAMP())",
            [$userId, $userDate, $moodLevel, $notes]
        );
        
        // Mark the journal checklist item as complete
        upsertChecklistEntry($userId, 4, $userDate, true);

        require_once __DIR__ . '/../includes/xp_service.php';
        awardItemCheckXp($userId, 4, true, $userDate);
        awardCalmPoints($userId, 'mood_log', XP_MOOD_LOG, $userDate, true);
        
        // Check if this completes the day
        $newlyCompleted = evaluateAndCompleteDay($userId, $userDate);
        if ($newlyCompleted) {
            updateStreakIfNeeded($userId, $userDate);
            $statusPreview = getStreakStatus($userId, false);
            awardDayCompletionXp(
                $userId,
                $userDate,
                (int) $statusPreview['items_completed'],
                (int) $statusPreview['items_required'],
                (int) $statusPreview['current_streak']
            );
        }
        
        setFlash('success', $journalInApp ? 'Journal entry saved!' : 'Mood logged and external journal confirmed!');
        redirect('/challenge/app/journal.php');
    }
}

// Mood color helper
function getMoodColor(int $level): string {
    $colors = [
        1 => '#B45454', 2 => '#C4784A', 3 => '#C4924A', 4 => '#C4A35A', 5 => '#B8A46A',
        6 => '#9A9A6A', 7 => '#7A9470', 8 => '#6B8F71', 9 => '#5A7F68', 10 => '#4A6F5C',
    ];
    return $colors[$level] ?? '#C4A35A';
}

// Mood label helper
function getMoodLabel(int $level): string {
    $labels = [
        1 => 'Very Low', 2 => 'Low', 3 => 'Below Average', 4 => 'Slightly Low', 5 => 'Neutral',
        6 => 'Slightly Good', 7 => 'Good', 8 => 'Great', 9 => 'Excellent', 10 => 'Amazing',
    ];
    return $labels[$level] ?? 'Neutral';
}

$pageTitle = 'Journal';
$bodyClass = (!$moodEntry && $journalInApp) ? 'journal-compose-page' : 'journal-view-page';
include __DIR__ . '/../includes/header.php';
?>

<div class="journal-page <?= (!$moodEntry && $journalInApp) ? 'journal-page--compose' : '' ?>">
    <?php if ($moodEntry): ?>
    <div class="journal-card journal-card--flat">
        <div class="journal-card-header">
            <h2>Today's entry</h2>
            <p><?= (new DateTime($userDate))->format('l, F j, Y') ?></p>
        </div>
            <div class="mood-logged-display">
                <div class="mood-logged-badge" style="background-color: <?= getMoodColor($moodEntry['mood_level']) ?>">
                    <?= $moodEntry['mood_level'] ?>
                </div>
                <div class="mood-logged-label"><?= getMoodLabel($moodEntry['mood_level']) ?></div>
                <div class="mood-logged-date">
                    <i data-lucide="check-circle"></i>
                    Entry saved for today
                </div>
                <div class="entry-stats">
                    <div class="entry-stat-pill">
                        <i data-lucide="dumbbell"></i>
                        <span>
                            Workout:
                            <strong>
                                <?= $todayWorkout ? h($todayWorkoutLabel) . ' (' . (int) ($todayWorkout['duration_minutes'] ?? 30) . ' min)' : 'Not logged' ?>
                            </strong>
                        </span>
                    </div>
                    <div class="entry-stat-pill">
                        <i data-lucide="droplets"></i>
                        <span>Water: <strong><?= $todayWaterTotal ?> oz</strong></span>
                    </div>
                </div>
                
                <?php if ($journalInApp && !empty($moodEntry['notes'])): ?>
                    <div class="mood-logged-notes mood-logged-notes--fullscreen-read">
                        <p><?= nl2br(h($moodEntry['notes'])) ?></p>
                    </div>
                <?php elseif ($journalInApp): ?>
                    <div class="mood-logged-notes empty">
                        <p>No journal notes for today.</p>
                    </div>
                <?php else: ?>
                    <div class="mood-logged-notes empty">
                        <p>Journal completed outside the app.</p>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="journal-complete-message">
                <i data-lucide="lock"></i>
                <span>Today's entry is complete and cannot be edited.</span>
            </div>
    </div>
    <?php else: ?>
            <div class="journal-compose-shellbar">
                <a href="/challenge/app/dashboard.php" class="btn btn-secondary btn-sm" aria-label="Back to daily checklist">
                    <i data-lucide="arrow-left"></i>
                    Back
                </a>
                <strong>Journal entry</strong>
                <span aria-hidden="true"></span>
            </div>
            <form method="POST" class="journal-form journal-form--fullscreen">
                <div class="journal-compose-top">
                    <div class="journal-card-header">
                        <?php if ($journalInApp): ?>
                            <h2>How are you feeling today?</h2>
                        <?php else: ?>
                            <h2>Daily Journal Check-in</h2>
                        <?php endif; ?>
                        <p><?= (new DateTime($userDate))->format('l, F j, Y') ?></p>
                    </div>
                    <div class="mood-slider-container">
                        <div class="mood-value-display" id="moodValueDisplay" style="--mood-color: <?= getMoodColor(5) ?>">
                            5
                        </div>
                        <div class="mood-label-display" id="moodLabelDisplay">
                            <?= getMoodLabel(5) ?>
                        </div>
                        <div class="mood-slider-wrapper">
                            <input type="range"
                                   id="moodSlider"
                                   name="mood_level"
                                   min="1"
                                   max="10"
                                   value="5"
                                   class="mood-slider"
                                   oninput="updateMoodDisplay(this.value)">
                            <div class="mood-slider-labels">
                                <span>1</span><span>2</span><span>3</span><span>4</span><span>5</span>
                                <span>6</span><span>7</span><span>8</span><span>9</span><span>10</span>
                            </div>
                        </div>
                        <div class="mood-endpoints">
                            <span class="endpoint low">Low</span>
                            <span class="endpoint high">High</span>
                        </div>
                    </div>
                </div>

                <?php if ($journalInApp): ?>
                    <div class="journal-write-surface">
                        <label for="journalNotes" class="visually-hidden">What's on your mind?</label>
                        <textarea
                            id="journalNotes"
                            name="notes"
                            class="journal-textarea journal-textarea--fullscreen"
                            placeholder="Write about your day, thoughts, gratitude, or anything you'd like to reflect on..."
                            required
                        ></textarea>
                    </div>
                <?php else: ?>
                    <div class="external-journal-note">
                        <i data-lucide="book-open"></i>
                        <span>Log your mood here, then complete your written journal outside the app.</span>
                    </div>
                <?php endif; ?>

                <div class="journal-actions journal-actions--sticky">
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i data-lucide="save"></i>
                        Save Entry
                    </button>
                </div>
            </form>
    <?php endif; ?>

    <!-- Link to history - only for in-app journaling -->
    <?php if ($journalInApp): ?>
    <div class="journal-history-link">
        <a href="/challenge/app/journal_history.php">
            <i data-lucide="history"></i>
            View past entries
        </a>
        <a href="/challenge/app/export_journal.php">
            <i data-lucide="download"></i>
            Export entries
        </a>
    </div>
    <?php endif; ?>
</div>

<style>
.mood-logged-display {
    text-align: center;
}

.mood-logged-date {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    color: var(--success);
    font-size: 0.875rem;
    margin-top: 0.5rem;
}

.mood-logged-date svg {
    width: 16px;
    height: 16px;
}

.mood-logged-notes {
    margin-top: 1.5rem;
    padding: 1rem;
    background: var(--surface-hover);
    border-radius: var(--radius);
    text-align: left;
}

.mood-logged-notes.empty {
    text-align: center;
}

.mood-logged-notes.empty p {
    font-style: italic;
}

.mood-logged-notes p {
    color: var(--text-secondary);
    font-size: 0.9375rem;
    line-height: 1.6;
    white-space: pre-wrap;
    margin: 0;
}

.journal-complete-message {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    margin-top: 1.5rem;
    padding: 1rem;
    background: var(--surface-hover);
    border-radius: var(--radius);
    color: var(--text-secondary);
    font-size: 0.875rem;
}

.journal-complete-message svg {
    width: 16px;
    height: 16px;
    color: var(--text-muted);
}

.btn-lg {
    padding: 1rem 2rem;
    font-size: 1rem;
}

.btn-lg i {
    margin-right: 0.5rem;
}

/* External Journal Styles */
.external-journal-form,
.external-journal-complete {
    text-align: center;
    padding: 1rem 0;
}

.external-journal-icon {
    width: 80px;
    height: 80px;
    margin: 0 auto 1.5rem;
    border-radius: 50%;
    background: var(--success);
    display: flex;
    align-items: center;
    justify-content: center;
}

.external-journal-icon svg {
    width: 40px;
    height: 40px;
    color: white;
}

.external-journal-icon.pending {
    background: var(--primary);
}

.external-journal-complete h3 {
    color: var(--success);
    margin-bottom: 0.5rem;
}

.external-journal-form p,
.external-journal-complete p {
    color: var(--text-secondary);
    margin-bottom: 1.5rem;
}

.checkbox-large {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.75rem;
    padding: 1rem;
    background: var(--surface-hover);
    border-radius: var(--radius);
    cursor: pointer;
    margin-bottom: 1.5rem;
    transition: background-color 0.2s;
}

.checkbox-large:hover {
    background: var(--border);
}

.checkbox-large input[type="checkbox"] {
    width: 24px;
    height: 24px;
    accent-color: var(--primary);
}

.checkbox-large .checkbox-label {
    font-weight: 500;
    font-size: 1rem;
}
</style>

<script>
const moodLabels = {
    1: 'Very Low', 2: 'Low', 3: 'Below Average', 4: 'Slightly Low', 5: 'Neutral',
    6: 'Slightly Good', 7: 'Good', 8: 'Great', 9: 'Excellent', 10: 'Amazing'
};

const moodColors = {
    1: '#B45454', 2: '#C4784A', 3: '#C4924A', 4: '#C4A35A', 5: '#B8A46A',
    6: '#9A9A6A', 7: '#7A9470', 8: '#6B8F71', 9: '#5A7F68', 10: '#4A6F5C'
};

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

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    const slider = document.getElementById('moodSlider');
    if (slider) {
        updateMoodDisplay(slider.value);
    }
    
    // Re-initialize Lucide icons
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
