<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

$vanity = isset($_GET['vanity']) ? (string) $_GET['vanity'] : '';
$vanity = trim($vanity);
if ($vanity === '' || !preg_match('/^[a-zA-Z0-9_-]+$/', $vanity)) {
    http_response_code(400);
    echo json_encode([
        'ok' => false,
        'error' => 'Invalid vanity name.',
    ]);
    exit;
}

$cacheFile = __DIR__ . '/cache/resolve_' . $vanity . '.json';
$cacheTtl = 24 * 60 * 60;

if (file_exists($cacheFile)) {
    $cached = json_decode((string) file_get_contents($cacheFile), true);
    if (is_array($cached) && isset($cached['fetched_at']) && (time() - (int) $cached['fetched_at'] < $cacheTtl)) {
        echo json_encode($cached);
        exit;
    }
}

$url = 'https://steamcommunity.com/id/' . rawurlencode($vanity) . '/';

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_TIMEOUT => 15,
    CURLOPT_USERAGENT => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 13_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120 Safari/537.36',
]);
$html = curl_exec($ch);
$error = curl_error($ch);
// curl_close is deprecated in PHP 8.5+ and is a no-op since 8.0.

if (!$html || $error) {
    http_response_code(502);
    echo json_encode([
        'ok' => false,
        'error' => $error ?: 'Failed to fetch Steam community profile.',
    ]);
    exit;
}

$steamId = '';
if (preg_match('/\"steamid\"\\s*:\\s*\"(\\d{17})\"/', $html, $matches)) {
    $steamId = $matches[1];
}

$personaName = '';
if (preg_match('/\"personaname\"\\s*:\\s*\"([^\"]+)\"/', $html, $matches)) {
    $personaName = $matches[1];
}

if ($steamId === '') {
    http_response_code(404);
    echo json_encode([
        'ok' => false,
        'error' => 'SteamID not found for this vanity name.',
    ]);
    exit;
}

$result = [
    'ok' => true,
    'fetched_at' => time(),
    'source' => $url,
    'vanity' => $vanity,
    'steamid' => $steamId,
    'persona_name' => $personaName,
];

file_put_contents($cacheFile, json_encode($result));
echo json_encode($result);
