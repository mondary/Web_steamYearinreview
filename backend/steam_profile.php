<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

$steamIdParam = isset($_GET['steamid']) ? (string) $_GET['steamid'] : '';
$steamIdParam = preg_match('/^\d{17}$/', $steamIdParam) ? $steamIdParam : '';

$cacheSuffix = $steamIdParam ?: 'vanity';
$cacheFile = __DIR__ . '/cache/steam_profile_cache_' . $cacheSuffix . '.json';
$cacheTtl = 3 * 60 * 60;

if (file_exists($cacheFile)) {
    $cached = json_decode((string) file_get_contents($cacheFile), true);
    if (is_array($cached) && isset($cached['fetched_at']) && (time() - (int) $cached['fetched_at'] < $cacheTtl)) {
        echo json_encode($cached);
        exit;
    }
}

$url = $steamIdParam
    ? 'https://steamcommunity.com/profiles/' . $steamIdParam . '/'
    : 'https://steamcommunity.com/id/pouark/';

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

libxml_use_internal_errors(true);
$doc = new DOMDocument();
$doc->loadHTML($html);
$xpath = new DOMXPath($doc);

$getText = static function (?DOMNode $node): string {
    return $node ? trim($node->textContent) : '';
};

$getAttr = static function (?DOMNode $node, string $attr): string {
    if (!$node || !$node->attributes) {
        return '';
    }
    $attrNode = $node->attributes->getNamedItem($attr);
    return $attrNode ? trim($attrNode->nodeValue) : '';
};

$levelNode = $xpath->query("//div[contains(@class,'persona_level')]//span[contains(@class,'friendPlayerLevelNum')]")->item(0);
$badgesNode = $xpath->query("//div[contains(@class,'profile_count_link')]/a[span[contains(@class,'count_link_label') and normalize-space()='Badges']]/span[contains(@class,'profile_count_link_total')]")->item(0);
$gamesNode = $xpath->query("//div[contains(@class,'profile_count_link')]/a[span[contains(@class,'count_link_label') and normalize-space()='Games']]/span[contains(@class,'profile_count_link_total')]")->item(0);
$statusNode = $xpath->query("//div[contains(@class,'profile_in_game_header')]")->item(0);
$gamesPlayedNode = $xpath->query("//div[contains(@class,'games_played_ctn')]//div[contains(@class,'big_stat')]")->item(0);
$titleNode = $xpath->query('//title')->item(0);

$steamId = $steamIdParam;
if (preg_match('/\"steamid\"\\s*:\\s*\"(\\d{17})\"/', $html, $matches)) {
    $steamId = $matches[1];
}

$memberNode = $xpath->query("//*[contains(@data-tooltip-html, 'Member since')]")->item(0);
$memberTooltip = $memberNode ? html_entity_decode($getAttr($memberNode, 'data-tooltip-html')) : '';
$memberSince = '';
if ($memberTooltip && preg_match('/Member since\\s+([0-9]{1,2}\\s+[A-Za-z]+,\\s+[0-9]{4})/i', $memberTooltip, $matches)) {
    $memberSince = $matches[1];
}

$accountAge = '';
$accountDays = '';
if ($memberSince) {
    $date = DateTime::createFromFormat('j F, Y', $memberSince);
    if ($date) {
        $diff = $date->diff(new DateTime('now'));
        $years = (int) $diff->y;
        $accountDays = (string) $diff->days;
        $accountAge = $years . ' ans';
    }
}

$titleText = $getText($titleNode);
$personaName = '';
if ($titleText && strpos($titleText, '::') !== false) {
    $parts = array_map('trim', explode('::', $titleText));
    $personaName = end($parts);
}

$result = [
    'ok' => true,
    'fetched_at' => time(),
    'source' => $url,
    'steamid' => $steamId,
    'persona_name' => $personaName,
    'status' => $getText($statusNode),
    'level' => $getText($levelNode),
    'badges' => $getText($badgesNode),
    'games_owned' => $getText($gamesNode),
    'games_played' => $getText($gamesPlayedNode),
    'member_since' => $memberSince,
    'account_age' => $accountAge,
    'account_days' => $accountDays,
];

file_put_contents($cacheFile, json_encode($result));
echo json_encode($result);
