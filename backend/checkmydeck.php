<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

$cacheFile = __DIR__ . '/cache/checkmydeck.json';
$cacheTtl = 6 * 60 * 60;

if (file_exists($cacheFile)) {
    $cached = json_decode((string) file_get_contents($cacheFile), true);
    if (is_array($cached) && isset($cached['fetched_at']) && (time() - (int) $cached['fetched_at'] < $cacheTtl)) {
        echo json_encode($cached);
        exit;
    }
}

$script = __DIR__ . '/checkmydeck_fetch.mjs';
if (file_exists($script)) {
    $output = shell_exec('node ' . escapeshellarg($script) . ' 2>&1');
    if (file_exists($cacheFile)) {
        $cached = json_decode((string) file_get_contents($cacheFile), true);
        if (is_array($cached)) {
            echo json_encode($cached);
            exit;
        }
    }

    http_response_code(502);
    echo json_encode([
        'ok' => false,
        'error' => 'Failed to refresh CheckMyDeck data.',
        'details' => $output,
    ]);
    exit;
}

http_response_code(500);
echo json_encode([
    'ok' => false,
    'error' => 'CheckMyDeck fetch script is missing.',
]);
