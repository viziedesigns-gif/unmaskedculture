<?php
/**
 * Journal History Page
 * Kinto App
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/streak_service.php';

requireOnboarding();

$user = getCurrentUser();
$userId = getCurrentUserId();

// Check if user journals in-app
if (!($user['journal_in_app'] ?? 1)) {
    setFlash('info', 'Journal history is only available when journaling inside the app.');
    redirect('/challenge/app/dashboard.php');
}

// Pagination
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 10;
$offset = ($page - 1) * $perPage;

// Get total count
$totalCount = dbFetchOne(
    "SELECT COUNT(*) as count FROM mood_entries WHERE user_id = ? AND notes IS NOT NULL AND notes != ''",
    [$userId]
);
$totalEntries = (int) ($totalCount['count'] ?? 0);
$totalPages = ceil($totalEntries / $perPage);

// Get journal entries with notes
ensureWorkoutLogTable();
$entries = dbFetchAll(
    "SELECT
        me.user_date,
        me.mood_level,
        me.notes,
        me.created_at_utc,
        wl.water_total_oz,
        workout.workout_type,
        workout.workout_custom,
        workout.duration_minutes
     FROM mood_entries me
     LEFT JOIN (
        SELECT user_id, user_date, COALESCE(SUM(oz_amount), 0) AS water_total_oz
        FROM water_log
        WHERE user_id = ?
        GROUP BY user_id, user_date
     ) wl ON wl.user_id = me.user_id AND wl.user_date = me.user_date
     LEFT JOIN workout_log workout ON workout.user_id = me.user_id AND workout.user_date = me.user_date
     WHERE me.user_id = ? AND me.notes IS NOT NULL AND me.notes != ''
     ORDER BY me.user_date DESC 
     LIMIT ? OFFSET ?",
    [$userId, $userId, $perPage, $offset]
);

$requiredChecklistItems = dbFetchAll(
    "SELECT id, name, icon FROM daily_checklist_items
     WHERE active = 1 AND is_required = 1
     ORDER BY sort_order
     LIMIT 7"
);
$completedItemsByDate = [];
$entryDates = array_values(array_unique(array_column($entries, 'user_date')));
if ($entryDates) {
    $datePlaceholders = implode(',', array_fill(0, count($entryDates), '?'));
    $completedRows = dbFetchAll(
        "SELECT user_date, item_id FROM daily_checklist_entries
         WHERE user_id = ? AND value = 1 AND user_date IN ($datePlaceholders)",
        array_merge([$userId], $entryDates)
    );
    foreach ($completedRows as $completedRow) {
        $completedItemsByDate[$completedRow['user_date']][(int) $completedRow['item_id']] = true;
    }
}

$taskIcons = [
    'water' => 'droplets', 'book' => 'book-open', 'fitness' => 'dumbbell',
    'journal' => 'notebook-pen', 'heart' => 'heart',
    'no-food' => 'utensils-crossed', 'no-drink' => 'cup-soda',
];

// Mood color helper
function getMoodColor(int $level): string {
    $colors = [
        1 => '#ef4444', 2 => '#f97316', 3 => '#fb923c', 4 => '#fbbf24', 5 => '#facc15',
        6 => '#a3e635', 7 => '#84cc16', 8 => '#22c55e', 9 => '#16a34a', 10 => '#15803d',
    ];
    return $colors[$level] ?? '#facc15';
}

// Mood label helper
function getMoodLabel(int $level): string {
    $labels = [
        1 => 'Very Low', 2 => 'Low', 3 => 'Below Average', 4 => 'Slightly Low', 5 => 'Neutral',
        6 => 'Slightly Good', 7 => 'Good', 8 => 'Great', 9 => 'Excellent', 10 => 'Amazing',
    ];
    return $labels[$level] ?? 'Unknown';
}

$pageTitle = 'Journal History';
include __DIR__ . '/../includes/header.php';
?>

<div class="journal-history-page">
    <div class="page-header">
        <div class="header-content">
            <h1>Journal History</h1>
            <p>Review your past entries</p>
        </div>
        <a href="/challenge/app/export_journal.php" class="btn btn-secondary">
            <i data-lucide="download"></i> Export
        </a>
    </div>

    <?php if ($totalEntries === 0): ?>
        <div class="empty-state">
            <div class="empty-icon"><i data-lucide="book-open"></i></div>
            <h3>No journal entries yet</h3>
            <p>Start writing journal entries when you log your mood each day.</p>
            <a href="/challenge/app/journal.php" class="btn btn-primary">
                <i data-lucide="plus"></i> Write Entry
            </a>
        </div>
    <?php else: ?>
        <div class="entries-count">
            <span>Showing <?= count($entries) ?> of <?= $totalEntries ?> journal entries</span>
        </div>

        <div class="journal-entries accordion">
            <?php foreach ($entries as $index => $entry): ?>
                <?php
                $completedForDate = $completedItemsByDate[$entry['user_date']] ?? [];
                $completedTaskCount = 0;
                foreach ($requiredChecklistItems as $requiredItem) {
                    if (!empty($completedForDate[(int) $requiredItem['id']])) $completedTaskCount++;
                }
                ?>
                <article class="journal-entry accordion-item" data-entry-id="<?= $index ?>">
                    <button type="button" class="entry-header accordion-header" onclick="toggleEntry(<?= $index ?>)"
                            aria-expanded="false" aria-controls="journalEntryContent<?= $index ?>">
                        <span class="entry-date">
                            <span class="date-day"><?= (new DateTime($entry['user_date']))->format('j') ?></span>
                            <span class="date-month"><?= (new DateTime($entry['user_date']))->format('M Y') ?></span>
                        </span>
                        <span class="entry-mood">
                            <span class="mood-badge" style="background-color: <?= getMoodColor($entry['mood_level']) ?>">
                                <?= $entry['mood_level'] ?>/10
                            </span>
                            <span class="mood-label"><?= getMoodLabel($entry['mood_level']) ?></span>
                        </span>
                        <span class="entry-task-summary"><?= $completedTaskCount ?>/7 tasks</span>
                        <span class="entry-toggle">
                            <i data-lucide="chevron-down" class="toggle-icon"></i>
                        </span>
                    </button>
                    <div id="journalEntryContent<?= $index ?>" class="entry-content accordion-content" hidden>
                        <section class="entry-task-section" aria-label="Daily task results">
                            <h4>Daily checklist</h4>
                            <div class="entry-task-grid">
                                <?php foreach ($requiredChecklistItems as $task): ?>
                                    <?php
                                    $taskId = (int) $task['id'];
                                    $taskDone = !empty($completedForDate[$taskId]);
                                    $taskDetail = '';
                                    if ($taskId === 1) {
                                        $taskDetail = (int) ($entry['water_total_oz'] ?? 0) . ' oz logged';
                                    } elseif ($taskId === 3 && !empty($entry['workout_type'])) {
                                        $taskDetail = getWorkoutTypeLabel($entry['workout_type'], $entry['workout_custom'] ?? '')
                                            . (!empty($entry['duration_minutes']) ? ' · ' . (int) $entry['duration_minutes'] . ' min' : '');
                                    } elseif ($taskId === 4) {
                                        $taskDetail = 'Mood ' . (int) $entry['mood_level'] . '/10';
                                    }
                                    ?>
                                    <div class="entry-task <?= $taskDone ? 'is-complete' : 'is-missed' ?>">
                                        <span class="entry-task-status"><i data-lucide="<?= $taskDone ? 'check' : 'x' ?>"></i></span>
                                        <i data-lucide="<?= h($taskIcons[$task['icon']] ?? 'circle') ?>" class="entry-task-icon"></i>
                                        <span class="entry-task-copy">
                                            <strong><?= h($task['name']) ?></strong>
                                            <?php if ($taskDetail !== ''): ?><small><?= h($taskDetail) ?></small><?php endif; ?>
                                        </span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </section>
                        <section class="entry-note">
                            <h4>Journal entry</h4>
                            <p><?= h($entry['notes']) ?></p>
                        </section>
                        <footer class="entry-footer">
                            <span class="entry-time">
                                <i data-lucide="clock"></i>
                                <?= (new DateTime($entry['created_at_utc']))->format('g:i A') ?>
                            </span>
                        </footer>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
        
        <script>
        function toggleEntry(entryId) {
            const allEntries = document.querySelectorAll('.accordion-item');
            const clickedEntry = document.querySelector(`.accordion-item[data-entry-id="${entryId}"]`);
            const isOpen = clickedEntry.classList.contains('open');
            
            // Close all entries
            allEntries.forEach(entry => {
                entry.classList.remove('open');
                entry.querySelector('.accordion-header')?.setAttribute('aria-expanded', 'false');
                const content = entry.querySelector('.accordion-content');
                if (content) content.hidden = true;
            });
            
            // If the clicked entry wasn't open, open it
            if (!isOpen) {
                clickedEntry.classList.add('open');
                clickedEntry.querySelector('.accordion-header')?.setAttribute('aria-expanded', 'true');
                const content = clickedEntry.querySelector('.accordion-content');
                if (content) content.hidden = false;
            }
            
            // Re-initialize lucide icons
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        }
        </script>

        <?php if ($totalPages > 1): ?>
            <nav class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?page=<?= $page - 1 ?>" class="pagination-link prev">
                        ← Previous
                    </a>
                <?php endif; ?>
                
                <div class="pagination-numbers">
                    <?php
                    $startPage = max(1, $page - 2);
                    $endPage = min($totalPages, $page + 2);
                    
                    if ($startPage > 1) {
                        echo '<a href="?page=1" class="pagination-link">1</a>';
                        if ($startPage > 2) {
                            echo '<span class="pagination-ellipsis">...</span>';
                        }
                    }
                    
                    for ($i = $startPage; $i <= $endPage; $i++) {
                        $activeClass = $i === $page ? 'active' : '';
                        echo "<a href=\"?page=$i\" class=\"pagination-link $activeClass\">$i</a>";
                    }
                    
                    if ($endPage < $totalPages) {
                        if ($endPage < $totalPages - 1) {
                            echo '<span class="pagination-ellipsis">...</span>';
                        }
                        echo "<a href=\"?page=$totalPages\" class=\"pagination-link\">$totalPages</a>";
                    }
                    ?>
                </div>
                
                <?php if ($page < $totalPages): ?>
                    <a href="?page=<?= $page + 1 ?>" class="pagination-link next">
                        Next →
                    </a>
                <?php endif; ?>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
