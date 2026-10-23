<?php
// New Horizon RP – Live-Serverstatus für die Webseite.
// Fragt den FiveM-Server serverseitig ab (die IP der Besucher geht nicht an den Spielserver)
// und speichert das Ergebnis 30 Sekunden zwischen.
declare(strict_types=1);

const SERVER = '136.243.19.52:30120';
const CACHE_SECONDS = 30;

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$cacheFile = __DIR__ . '/cache/status.json';
if (is_file($cacheFile) && time() - filemtime($cacheFile) < CACHE_SECONDS) {
    readfile($cacheFile);
    exit;
}

function fetch_url(string $url, int $timeout = 4): ?string {
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => $timeout,
            CURLOPT_USERAGENT => 'newhorizonrp.de status',
        ]);
        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return ($body !== false && $code === 200) ? (string) $body : null;
    }
    $ctx = stream_context_create(['http' => ['timeout' => $timeout, 'header' => "User-Agent: newhorizonrp.de status\r\n"]]);
    $body = @file_get_contents($url, false, $ctx);
    return $body === false ? null : $body;
}

$result = ['online' => false, 'players' => 0, 'max' => 0, 'ts' => time()];
$raw = fetch_url('http://' . SERVER . '/dynamic.json');
if ($raw !== null) {
    $d = json_decode($raw, true);
    if (is_array($d)) {
        $result['online'] = true;
        $result['players'] = (int) ($d['clients'] ?? 0);
        $result['max'] = (int) ($d['sv_maxclients'] ?? 0);
    }
}

$json = json_encode($result);
@mkdir(__DIR__ . '/cache', 0755, true);
@file_put_contents($cacheFile, $json, LOCK_EX);
echo $json;
