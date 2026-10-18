<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/widget_service.php';
function expectWidget($actual, $expected): void {
    if ($actual !== $expected) throw new RuntimeException(var_export([$actual, $expected], true));
}
$tz = 'America/Indiana/Indianapolis';
expectWidget(widgetChecklistDate($tz, 'easy', new DateTimeImmutable('2026-10-19T00:30:00-04:00')), '2026-10-18');
expectWidget(widgetChecklistDate($tz, 'easy', new DateTimeImmutable('2026-10-19T01:00:00-04:00')), '2026-10-19');
expectWidget(widgetChecklistDate($tz, 'intermediate', new DateTimeImmutable('2026-10-19T00:00:00-04:00')), '2026-10-19');
expectWidget(widgetChecklistDate('Asia/Tokyo', 'intermediate', new DateTimeImmutable('2026-10-18T16:00:00Z')), '2026-10-19');
expectWidget(widgetChecklistDate($tz, 'easy', new DateTimeImmutable('2026-11-01T00:30:00-04:00')), '2026-10-31');
foreach (['water_tracker', 'mood_tracker', 'weight_tracker'] as $type) expectWidget(widgetCanToggle(['id' => 99, 'item_type' => $type]), false);
expectWidget(widgetCanToggle(['id' => 3, 'item_type' => 'checkbox']), false);
expectWidget(widgetCanToggle(['id' => 5, 'item_type' => 'checkbox']), true);
echo "Widget date, timezone, grace, DST and activity policies passed.\n";
