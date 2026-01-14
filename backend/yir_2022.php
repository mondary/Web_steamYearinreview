<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

$configFile = __DIR__ . '/config.php';
$steamCookie = '';
if (file_exists($configFile)) {
    $config = include $configFile;
    if (is_array($config) && isset($config['steam_cookie'])) {
        $steamCookie = (string) $config['steam_cookie'];
    }
}

$year = 2022;
$steamIdParam = isset($_GET['steamid']) ? (string) $_GET['steamid'] : '';
$steamIdParam = preg_match('/^\d{17}$/', $steamIdParam) ? $steamIdParam : '76561197974617624';
$url = 'https://store.steampowered.com/yearinreview/' . $steamIdParam . '/' . $year;

$manualFile = __DIR__ . '/manual/yir_' . $year . '_' . $steamIdParam . '.json';
if (file_exists($manualFile)) {
    $manual = json_decode((string) file_get_contents($manualFile), true);
    if (is_array($manual)) {
        $manual['ok'] = true;
        $manual['manual'] = true;
        $manual['source'] = $manual['source'] ?? $url;
        echo json_encode($manual);
        exit;
    }
}

$cacheFile = __DIR__ . '/cache/yir_2022_' . $steamIdParam . '.json';
$cacheTtl = 6 * 60 * 60;
$staleCache = null;

if (file_exists($cacheFile)) {
    $cached = json_decode((string) file_get_contents($cacheFile), true);
    $hasTimeline = is_array($cached) && isset($cached['timeline']) && is_array($cached['timeline']);
    $staleCache = $hasTimeline ? $cached : null;
    if ($hasTimeline && isset($cached['fetched_at']) && (time() - (int) $cached['fetched_at'] < $cacheTtl)) {
        echo json_encode($cached);
        exit;
    }
}

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_TIMEOUT => 15,
    CURLOPT_USERAGENT => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 13_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120 Safari/537.36',
]);
if ($steamCookie !== '') {
    curl_setopt($ch, CURLOPT_COOKIE, $steamCookie);
}
$html = curl_exec($ch);
$error = curl_error($ch);
// curl_close is deprecated in PHP 8.5+ and is a no-op since 8.0.

if (!$html || $error) {
    if ($staleCache) {
        $staleCache['stale'] = true;
        echo json_encode($staleCache);
        exit;
    }
    http_response_code(502);
    echo json_encode([
        'ok' => false,
        'error' => $error ?: 'Failed to fetch Steam Year in Review.',
    ]);
    exit;
}

$summaryMatch = [];
$pattern = '/data-yearinreview_\\d+_' . $year . '=\"([^\"]+)\"/s';
if (!preg_match($pattern, $html, $summaryMatch)) {
    if ($staleCache) {
        $staleCache['stale'] = true;
        echo json_encode($staleCache);
        exit;
    }
    http_response_code(502);
    echo json_encode([
        'ok' => false,
        'error' => 'Year in Review data not found.',
    ]);
    exit;
}

$summaryJson = html_entity_decode($summaryMatch[1], ENT_QUOTES | ENT_HTML5);
$summary = json_decode($summaryJson, true);

if (is_array($summary) && $summary === []) {
    $result = [
        'ok' => true,
        'fetched_at' => time(),
        'source' => $url,
        'timeline' => [],
    ];
    file_put_contents($cacheFile, json_encode($result));
    echo json_encode($result);
    exit;
}

if (!is_array($summary) || !isset($summary['playtime_stats'])) {
    if ($staleCache) {
        $staleCache['stale'] = true;
        echo json_encode($staleCache);
        exit;
    }
    http_response_code(502);
    echo json_encode([
        'ok' => false,
        'error' => 'Invalid Year in Review payload.',
    ]);
    exit;
}

$previousMatch = [];
$previousYear = 0;
$previousPattern = '/data-yearinreview_' . $year . '_previous_year_summary=\"([^\"]+)\"/s';
if (preg_match($previousPattern, $html, $previousMatch)) {
    $previousJson = html_entity_decode($previousMatch[1], ENT_QUOTES | ENT_HTML5);
    $previous = json_decode($previousJson, true);
    if (is_array($previous) && isset($previous['games_played'])) {
        $previousYear = (int) $previous['games_played'];
    }
}

$gameSummary = $summary['playtime_stats']['game_summary'] ?? [];
$filtered = array_filter($gameSummary, static function ($game) {
    if (!is_array($game)) {
        return false;
    }
    return (int) ($game['demo'] ?? 0) !== 1 && (int) ($game['playtest'] ?? 0) !== 1;
});

$gamesPlayed = count($filtered);
$newGames = count(array_filter($filtered, static function ($game) {
    return (int) ($game['new_this_year'] ?? 0) === 1;
}));

$demosPlayed = (int) ($summary['playtime_stats']['demos_played'] ?? 0);
$delta = $previousYear ? $gamesPlayed - $previousYear : 0;
$sessions = (int) ($summary['playtime_stats']['total_stats']['total_sessions'] ?? 0);
$achievements = (int) ($summary['playtime_stats']['summary_stats']['total_achievements'] ?? 0);

$months = $summary['playtime_stats']['months'] ?? [];
$timeline = [];
foreach ($months as $month) {
    if (!is_array($month) || !isset($month['rtime_month'])) {
        continue;
    }

    $monthGames = $month['game_summary'] ?? [];
    if (!is_array($monthGames)) {
        $monthGames = [];
    }

    usort($monthGames, static function ($a, $b) {
        $aScore = (int) ($a['relative_playtime_percentagex100'] ?? 0);
        $bScore = (int) ($b['relative_playtime_percentagex100'] ?? 0);
        return $bScore <=> $aScore;
    });

    $games = [];
    foreach ($monthGames as $entry) {
        if (!isset($entry['appid'])) {
            continue;
        }
        $percent = (int) round(((int) ($entry['relative_playtime_percentagex100'] ?? 0)) / 100);
        $games[] = [
            'appid' => (int) $entry['appid'],
            'percent' => $percent,
        ];
    }

    $timeline[] = [
        'rtime_month' => (int) $month['rtime_month'],
        'games' => $games,
    ];
}

$result = [
    'ok' => true,
    'fetched_at' => time(),
    'source' => $url,
    'games_played' => $gamesPlayed,
    'new_games' => $newGames,
    'demos_played' => $demosPlayed,
    'games_delta' => $delta,
    'sessions' => $sessions,
    'achievements' => $achievements,
    'timeline' => $timeline,
];

file_put_contents($cacheFile, json_encode($result));
echo json_encode($result);
