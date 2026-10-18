<?php
/** Shared widget policy. The server remains authoritative for dates and completion. */
function widgetCanToggle(array $item): bool {
    return !in_array($item['item_type'], ['water_tracker', 'mood_tracker', 'weight_tracker'], true)
        && (int) $item['id'] !== 3; // Workouts require an activity record, just like the dashboard.
}

function widgetChecklistDate(string $timezone, string $mode, ?DateTimeImmutable $now = null): string {
    $context = resolveChecklistDate($timezone, null, $now, checklistAllowsGracePeriod($mode));
    // Keep the closing checklist visible through Easy's final hour.
    return $context['can_log_yesterday'] ? $context['yesterday'] : $context['today'];
}

function widgetSnapshot(int $userId, array $user): array {
    $mode = normalizeChallengeMode($user['challenge_mode'] ?? null);
    $timezone = $user['timezone'] ?? DEFAULT_TIMEZONE;
    $date = widgetChecklistDate($timezone, $mode);
    $items = getTodayChecklist($userId, $date);
    return [
        'success' => true,
        'csrf' => $_SESSION['widget_csrf'],
        'user_date' => $date,
        'deadline_ms' => (new DateTimeImmutable(getChecklistDeadlineUtcIso($timezone, $date, $mode)))->getTimestamp() * 1000,
        'completed' => count(array_filter($items, fn($item) => $item['completed'])),
        'total' => count($items),
        'items' => array_map(fn($item) => [
            'id' => (int) $item['id'], 'name' => $item['name'],
            'completed' => (bool) $item['completed'], 'toggleable' => widgetCanToggle($item),
            'type' => (int) $item['id'] === 3 ? 'workout' : $item['item_type'],
        ], $items),
    ];
}
