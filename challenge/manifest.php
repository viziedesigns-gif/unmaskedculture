<?php
/**
 * Dynamic web app manifest with versioned icon URLs.
 */

require_once __DIR__ . '/includes/functions.php';

header('Content-Type: application/manifest+json; charset=utf-8');

$icon192 = faviconUrl('kinto-app-icon-192.png');
$icon512 = faviconUrl('kinto-app-icon-512.png');

echo json_encode([
    'name' => 'Kinto',
    'short_name' => 'Kinto',
    'description' => 'Kinto — Heal. Grow. Become. Daily check-ins, journaling, mood insights, and community support.',
    'id' => '/challenge/app/dashboard.php',
    'start_url' => '/challenge/app/dashboard.php',
    'scope' => '/challenge/',
    'display' => 'standalone',
    'display_override' => ['standalone', 'minimal-ui', 'browser'],
    'orientation' => 'portrait',
    'background_color' => '#F5F0E8',
    'theme_color' => '#F5F0E8',
    'categories' => ['health', 'lifestyle', 'productivity'],
    'icons' => [
        [
            'src' => $icon192,
            'sizes' => '192x192',
            'type' => 'image/png',
            'purpose' => 'any maskable',
        ],
        [
            'src' => $icon512,
            'sizes' => '512x512',
            'type' => 'image/png',
            'purpose' => 'any maskable',
        ],
    ],
    'shortcuts' => [
        [
            'name' => 'Daily Check-in',
            'short_name' => 'Daily',
            'description' => 'Open today\'s challenge checklist.',
            'url' => '/challenge/app/dashboard.php',
            'icons' => [
                ['src' => $icon192, 'sizes' => '192x192', 'type' => 'image/png'],
            ],
        ],
        [
            'name' => 'Journal',
            'short_name' => 'Journal',
            'description' => 'Open your journal.',
            'url' => '/challenge/app/journal.php',
            'icons' => [
                ['src' => $icon192, 'sizes' => '192x192', 'type' => 'image/png'],
            ],
        ],
    ],
], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
