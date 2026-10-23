<?php
// New Horizon RP – holt Ankündigungen und Team aus dem Forum (forum.newhorizonrp.de)
// und speichert sie 5 Minuten zwischen. Nur öffentliche Daten des Forums.
declare(strict_types=1);

const FORUM = 'https://forum.newhorizonrp.de';
const CACHE_SECONDS = 300;
const NEWS_CATEGORY = 'ankuendigungen/5';
// Reihenfolge = Rangfolge auf der Webseite
const RANKS = [
    'projektleitung'      => 'Projektleitung',
    'stv_projektleitung'  => 'Stv. Projektleitung',
    'administration'      => 'Administrator',
    'moderation'          => 'Moderator',
    'entwickler'          => 'Entwickler',
    'support'             => 'Supporter',
    'testsupport'         => 'Test-Supporter',
    'eventteam'           => 'Eventteam',
    'fraktionsverwaltung' => 'Fraktionsverwaltung',
];

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=60');

$what = ($_GET['what'] ?? '') === 'team' ? 'team' : 'news';
$cacheFile = __DIR__ . "/cache/$what.json";
if (is_file($cacheFile) && time() - filemtime($cacheFile) < CACHE_SECONDS) {
    readfile($cacheFile);
    exit;
}

function get_json(string $path): ?array {
    $url = FORUM . $path;
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 6,
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
            CURLOPT_USERAGENT => 'newhorizonrp.de website',
        ]);
        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($body === false || $code !== 200) return null;
    } else {
        $ctx = stream_context_create(['http' => ['timeout' => 6, 'header' => "Accept: application/json\r\nUser-Agent: newhorizonrp.de website\r\n"]]);
        $body = @file_get_contents($url, false, $ctx);
        if ($body === false) return null;
    }
    $d = json_decode((string) $body, true);
    return is_array($d) ? $d : null;
}

function excerpt(string $html, int $len = 170): string {
    $text = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    return mb_strlen($text) > $len ? rtrim(mb_substr($text, 0, $len)) . ' …' : $text;
}

$out = $what === 'news' ? ['items' => []] : ['members' => []];

if ($what === 'news') {
    $cat = get_json('/c/' . NEWS_CATEGORY . '/show.json');
    $aboutUrl = $cat['category']['topic_url'] ?? '';
    $list = get_json('/c/' . NEWS_CATEGORY . '/l/latest.json?order=created');
    foreach (($list['topic_list']['topics'] ?? []) as $t) {
        if (count($out['items']) >= 3) break;
        $url = '/t/' . $t['slug'] . '/' . $t['id'];
        if ($aboutUrl !== '' && str_ends_with($aboutUrl, '/' . $t['id'])) continue;
        $topic = get_json('/t/' . (int) $t['id'] . '.json');
        $out['items'][] = [
            'title'   => (string) $t['title'],
            'url'     => FORUM . $url,
            'date'    => (string) $t['created_at'],
            'excerpt' => excerpt((string) ($topic['post_stream']['posts'][0]['cooked'] ?? '')),
        ];
    }
} else {
    $seen = [];
    foreach (RANKS as $group => $label) {
        $info = get_json('/groups/' . $group . '.json');
        $color = $info['group']['flair_bg_color'] ?? null;
        $members = get_json('/groups/' . $group . '/members.json?limit=50');
        foreach (($members['members'] ?? []) as $m) {
            $name = (string) $m['username'];
            if (isset($seen[$name])) continue;
            $seen[$name] = true;
            $avatar = str_replace('{size}', '96', (string) ($m['avatar_template'] ?? ''));
            if ($avatar !== '' && str_starts_with($avatar, '/')) $avatar = FORUM . $avatar;
            $out['members'][] = ['name' => $name, 'rank' => $label, 'color' => $color, 'avatar' => $avatar];
        }
    }
}

$json = json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
@mkdir(__DIR__ . '/cache', 0755, true);
@file_put_contents($cacheFile, $json, LOCK_EX);
echo $json;
