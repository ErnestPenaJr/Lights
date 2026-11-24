<?php
// JSON API for index.php. GET ?a=state&place=N, everything else is POST with a JSON body.
require __DIR__ . '/haodeng.php';
require __DIR__ . '/store.php';
header('Content-Type: application/json');
$config = @include __DIR__ . '/config.php';
if (!$config) exit(json_encode(['error' => 'Copy config.example.php to config.php and fill in your Hao Deng app login.']));

function cloud(array $config, bool $fresh = false): HaoDeng { return cloud_cached($config, $fresh); }

$in = $_SERVER['REQUEST_METHOD'] === 'POST' ? (json_decode(file_get_contents('php://input'), true) ?: []) : $_GET;
$a = $in['a'] ?? '';
$both = ($in['place'] ?? '') === 'all'; // the "Both" tab: every area at once
$place = $both ? 0 : (int)($in['place'] ?? 0);

try {
    $cloud = cloud($config);
    $st = store_load();
    $res = handle($a, $in, $cloud, $st, $place, $both);
} catch (Throwable $e) {
    try { // token may have expired: log in again, retry once
        $cloud = cloud($config, true);
        $res = handle($a, $in, $cloud, $st ?? store_load(), $place, $both);
    } catch (Throwable $e2) {
        http_response_code(500);
        $res = ['error' => $e2->getMessage()];
    }
}
echo json_encode($res);

function handle(string $a, array $in, HaoDeng $cloud, array $st, int $place, bool $both = false): array {
    $p = $cloud->places[$place] ?? null;
    if (!$p) return ['error' => 'no such place'];
    $placeIdxs = $both ? array_keys($cloud->places) : [$place];
    $addr = (int)($in['addr'] ?? 0);
    $ct = 5;
    foreach ($p['devices'] as $d) if ((int)$d['meshAddress'] === $addr) $ct = (int)$d['controlType'];

    switch ($a) {
        case 'state':
            $statusError = null; $lights = [];
            foreach ($placeIdxs as $pi) {
                $status = [];
                try { $status = $cloud->status($pi); } catch (Throwable $e) { $statusError = $e->getMessage(); }
                foreach (ordered_devices($cloud->places[$pi]['devices'], $pi, $st) as $d) {
                    $ad = (int)$d['meshAddress'];
                    $lights[] = ['place' => $pi, 'key' => "$pi-$ad", 'addr' => $ad, 'name' => $st['names']["$pi-$ad"] ?? $d['displayName'],
                        'cloudName' => $d['displayName'], 'status' => $status[$ad] ?? null];
                }
            }
            $run = run_load();
            $allLights = []; // every place's lights (name + key), for scenes that span both areas
            foreach ($cloud->places as $pi => $pl) foreach (ordered_devices($pl['devices'], $pi, $st) as $d) {
                $ad = (int)$d['meshAddress'];
                $allLights[$pi][] = ['key' => "$pi-$ad", 'addr' => $ad, 'name' => $st['names']["$pi-$ad"] ?? $d['displayName']];
            }
            return ['places' => array_column($cloud->places, 'name'), 'place' => $both ? 'all' : $place, 'lights' => $lights, 'allLights' => $allLights,
                'scenes' => array_values(array_filter($st['scenes'], fn($s) => $both || ($s['scope'] ?? 'place') === 'all' || (int)$s['place'] === $place)),
                'playing' => $run && ($both || in_array($place, $run['places'] ?? [(int)$run['place']])) ? $run : null,
                'permanent' => store_permanent(), 'statusError' => $statusError, 'jobs' => jobs_pending()];

        case 'playing': // cheap poll while a scene starts or a reset runs (no status request to the bridges)
            $run = run_load();
            return ['playing' => $run && ($both || in_array($place, $run['places'] ?? [(int)$run['place']])) ? $run : null, 'jobs' => jobs_pending()];

        case 'cmd': // a background job sends it, checks the light(s) answered, and re-sends to any that missed (see ensure.php)
            if ($in['addr'] === 'all') { // every light in the area(s)
                foreach ($placeIdxs as $pi) {
                    $addrs = array_map(fn($d) => (int)$d['meshAddress'], $cloud->places[$pi]['devices']);
                    if (!cmd_job($cloud, $pi, $addrs, true, $in['cmd'] ?? '', $in)) return ['error' => 'unknown command'];
                }
                return ['ok' => true];
            }
            return cmd_job($cloud, $place, [$addr], false, $in['cmd'] ?? '', $in) ? ['ok' => true] : ['error' => 'unknown command'];

        case 'identify': // toggle: blink in the background until 'stop' (or 10 minutes)
            $run = run_load();
            stop_runner();
            if ($run && ($run['mode'] ?? '') === 'identify' && (int)$run['addr'] === $addr && (int)$run['place'] === $place) return ['ok' => true, 'blinking' => false];
            $name = $st['names']["$place-$addr"] ?? $in['name'] ?? "#$addr";
            run_save(['mode' => 'identify', 'addr' => $addr, 'ct' => $ct, 'wasOn' => (bool)($in['wasOn'] ?? true), 'place' => $place,
                'name' => "Finding $name", 'token' => bin2hex(random_bytes(4)), 'started' => time(), 'pid' => 0]);
            runner_start();
            return ['ok' => true, 'blinking' => true];

        case 'rename':
            $name = trim($in['name'] ?? '');
            if ($name === '') unset($st['names']["$place-$addr"]); else $st['names']["$place-$addr"] = mb_substr($name, 0, 40);
            store_save($st);
            return ['ok' => true];

        case 'reorder': // full list of mesh addresses for this place, in display order
            $st['order'][$place] = array_values(array_map('intval', array_filter($in['order'] ?? [], 'is_numeric')));
            store_save($st);
            return ['ok' => true];

        case 'scene_save':
            $sc = $in['scene'] ?? [];
            $id = preg_replace('/[^a-z0-9]/', '', strtolower($sc['id'] ?? '')) ?: substr(bin2hex(random_bytes(4)), 0, 8);
            $lanes = [];
            foreach ($sc['lanes'] ?? [] as $l) {
                $color = preg_match('/^#?[0-9a-f]{6}$/i', $l['color'] ?? '') ? ltrim($l['color'], '#') : 'ffffff';
                $lights = []; // light keys "<place>-<addr>"; a bare number means this place (old scenes)
                foreach ($l['lights'] ?? [] as $k) {
                    if (is_int($k) || ctype_digit((string)$k)) $k = "$place-$k";
                    if (preg_match('/^\d+-\d+$/', (string)$k)) $lights[] = (string)$k;
                }
                $lanes[] = ['color' => strtolower($color), 'lights' => array_values(array_unique($lights))];
            }
            $st['scenes'][$id] = ['id' => $id, 'place' => $place, 'scope' => ($sc['scope'] ?? 'place') === 'all' ? 'all' : 'place',
                'name' => mb_substr(trim($sc['name'] ?? 'Scene'), 0, 40) ?: 'Scene',
                'effect' => in_array($sc['effect'] ?? '', ['static', 'blink', 'breathe', 'rotate', 'twinkle', 'rainbow']) ? $sc['effect'] : 'static',
                'speed' => max(1, min(10, (int)($sc['speed'] ?? 5))), 'lanes' => array_slice($lanes, 0, 8)];
            store_save($st);
            return ['ok' => true, 'id' => $id];

        case 'scene_delete':
            unset($st['scenes'][$in['id'] ?? '']);
            store_save($st);
            return ['ok' => true];

        case 'play':
            return scene_play($cloud, $st, $in['id'] ?? '', $place) ? ['ok' => true] : ['error' => 'no such scene'];

        case 'stop':
            scene_stop($place);
            return ['ok' => true];
    }
    return ['error' => 'unknown action'];
}
