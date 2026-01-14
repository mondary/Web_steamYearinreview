<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

$steamIdParam = isset($_GET['steamid']) ? (string) $_GET['steamid'] : '';
$steamIdParam = preg_match('/^\d{17}$/', $steamIdParam) ? $steamIdParam : '';

$cacheDir = __DIR__ . '/cache';
if (!is_dir($cacheDir)) {
    echo json_encode(['ok' => true, 'deleted' => 0]);
    exit;
}

$deleted = 0;

$patterns = $steamIdParam
    ? [
        $cacheDir . '/yir_2025_' . $steamIdParam . '.json',
        $cacheDir . '/yir_2024_' . $steamIdParam . '.json',
        $cacheDir . '/yir_2023_' . $steamIdParam . '.json',
        $cacheDir . '/yir_2022_' . $steamIdParam . '.json',
        $cacheDir . '/steam_profile_cache_' . $steamIdParam . '.json',
      ]
    : [
        $cacheDir . '/yir_2025_*.json',
        $cacheDir . '/yir_2024_*.json',
        $cacheDir . '/yir_2023_*.json',
        $cacheDir . '/yir_2022_*.json',
        $cacheDir . '/steam_profile_cache_*.json',
      ];

foreach ($patterns as $pattern) {
    foreach (glob($pattern) ?: [] as $file) {
        if (is_file($file) && @unlink($file)) {
            $deleted++;
        }
    }
}

echo json_encode([
    'ok' => true,
    'deleted' => $deleted,
]);
