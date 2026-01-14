<?php
declare(strict_types=1);

if (php_sapi_name() !== 'cli') {
    fwrite(STDERR, "CLI only.\n");
    exit(1);
}

$inputPath = $argv[1] ?? '';
$year = isset($argv[2]) ? (int) $argv[2] : 0;
$steamId = $argv[3] ?? '';

if ($inputPath === '' || $year === 0 || !preg_match('/^\d{17}$/', $steamId)) {
    fwrite(STDERR, "Usage: php backend/tools/extract_yir.php <html_path> <year> <steamid>\n");
    exit(1);
}

if (!is_file($inputPath)) {
    fwrite(STDERR, "File not found: {$inputPath}\n");
    exit(1);
}

$html = (string) file_get_contents($inputPath);
$html = preg_replace("/=\\r?\\n/", "", $html);
$html = str_replace("=3D", "=", $html);
$pattern = '/data-yearinreview_\\d+_' . $year . '=\"([^\"]+)\"/s';
$summaryMatch = [];

if (!preg_match($pattern, $html, $summaryMatch)) {
    fwrite(STDERR, "Year in Review payload not found in HTML.\n");
    exit(1);
}

$summaryJson = html_entity_decode($summaryMatch[1], ENT_QUOTES | ENT_HTML5);
$summary = json_decode($summaryJson, true);

if (!is_array($summary) || !isset($summary['playtime_stats'])) {
    fwrite(STDERR, "Invalid Year in Review payload.\n");
    exit(1);
}

$previousYear = 0;
$previousMatch = [];
if (preg_match('/data-yearinreview_' . $year . '_previous_year_summary=\"([^\"]+)\"/s', $html, $previousMatch)) {
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
    'source' => 'manual-import',
    'games_played' => $gamesPlayed,
    'new_games' => $newGames,
    'demos_played' => $demosPlayed,
    'games_delta' => $delta,
    'sessions' => $sessions,
    'achievements' => $achievements,
    'timeline' => $timeline,
];

$manualDir = __DIR__ . '/../manual';
if (!is_dir($manualDir)) {
    mkdir($manualDir, 0755, true);
}

$outFile = $manualDir . '/yir_' . $year . '_' . $steamId . '.json';
file_put_contents($outFile, json_encode($result));

fwrite(STDOUT, "Wrote {$outFile}\n");
