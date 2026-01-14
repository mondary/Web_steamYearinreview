<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

$url = 'https://steamhunters.com/id/pouark/games';

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_TIMEOUT => 15,
    CURLOPT_USERAGENT => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 13_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120 Safari/537.36',
]);
$html = curl_exec($ch);
$error = curl_error($ch);
$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
// curl_close is deprecated in PHP 8.5+ and is a no-op since 8.0.

if (!$html || $error || $status >= 400) {
    http_response_code(502);
    echo json_encode([
        'ok' => false,
        'status' => $status,
        'error' => $error ?: 'Request blocked (likely Cloudflare).',
        'source' => $url,
    ]);
    exit;
}

echo json_encode([
    'ok' => true,
    'status' => $status,
    'source' => $url,
    'note' => 'Fetched successfully (no parsing implemented).',
]);
