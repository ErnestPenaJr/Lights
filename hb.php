<?php
// Homebridge endpoints (plain text, GET), for homebridge-http-lightbulb and homebridge-http-switch.
//   ?light=<place>-<addr>&get=on|bright|hue|sat|ct           -> "1"/"0" or a number (ct in mired)
//   ?light=<place>-<addr>&set=on|off|bright|hue|sat|ct&v=N   -> "ok"
//   ?scene=<id>   ?stop=1   ?config=1 (accessories JSON to paste into Homebridge)
require __DIR__ . '/haodeng.php';
require __DIR__ . '/store.php';
header('Content-Type: text/plain');
$config = @include __DIR__ . '/config.php';
if (!$config) exit('no config.php');

try { $cloud = cloud_cached($config); } catch (Throwable $e) { http_response_code(500); exit('login failed: ' . $e->getMessage()); }
$st = store_load();

if (isset($_GET['config'])) {
    header('Content-Type: application/json');
    $base = 'http://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['SCRIPT_NAME']) . '/hb.php';
    $acc = [];
    foreach ($cloud->places as $pi => $pl) foreach (ordered_devices($pl['devices'], $pi, $st) as $d) {
        $a = (int)$d['meshAddress']; $u = "$base?light=$pi-$a";
        $name = $st['names']["$pi-$a"] ?? $d['displayName'];
        $acc[] = ['accessory' => 'HTTP-LIGHTBULB', 'name' => "{$pl['name']} $name",
            'onUrl' => "$u&set=on", 'offUrl' => "$u&set=off", 'statusUrl' => "$u&get=on", 'pullInterval' => 60000,
            'brightness' => ['setUrl' => "$u&set=bright&v=%s", 'statusUrl' => "$u&get=bright", 'withholdPowerUpdate' => true],
            'hue' => ['setUrl' => "$u&set=hue&v=%s", 'statusUrl' => "$u&get=hue"],
            'saturation' => ['setUrl' => "$u&set=sat&v=%s", 'statusUrl' => "$u&get=sat"],
            'colorTemperature' => ['setUrl' => "$u&set=ct&v=%s", 'statusUrl' => "$u&get=ct", 'unit' => 'mired', 'minValue' => 153, 'maxValue' => 400]];
    }
    foreach ($st['scenes'] as $sc) $acc[] = ['accessory' => 'HTTP-SWITCH', 'name' => "Scene {$sc['name']}", 'switchType' => 'stateless', 'timeout' => 1000, 'onUrl' => "$base?scene={$sc['id']}"];
    $acc[] = ['accessory' => 'HTTP-SWITCH', 'name' => 'Stop scene', 'switchType' => 'stateless', 'timeout' => 1000, 'onUrl' => "$base?stop=1"];
    exit(json_encode($acc, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
}
if (isset($_GET['scene'])) exit(scene_play($cloud, $st, $_GET['scene'], 0) ? 'ok' : 'no such scene');
if (isset($_GET['stop'])) { scene_stop(0); exit('ok'); }

if (!preg_match('/^(\d+)-(\d+)$/', $_GET['light'] ?? '', $m)) exit('bad light');
[$place, $addr] = [(int)$m[1], (int)$m[2]];
$colorFile = store_dir() . '/hb-color.json'; // HomeKit sends hue and saturation as two separate calls; remember both
$colors = is_file($colorFile) ? (json_decode(file_get_contents($colorFile), true) ?: []) : [];
$c = $colors["$place-$addr"] ?? ['hue' => 30, 'sat' => 20];

if (isset($_GET['get'])) {
    try { $x = status_cached($cloud, $place)[$addr] ?? null; } catch (Throwable $e) { $x = null; }
    switch ($_GET['get']) {
        case 'on': exit($x && $x['on'] ? '1' : '0');
        case 'bright': exit((string)($x ? max(0, min(100, (int)$x['bright'])) : 0));
        case 'hue': exit((string)($x && $x['mode'] === 'color' ? (int)$x['hue'] : (int)$c['hue']));
        case 'sat': exit((string)($x && $x['mode'] === 'color' ? (int)$x['sat'] : (int)$c['sat']));
        case 'ct': exit((string)($x && $x['mode'] === 'white' ? (int)round(1e6 / max(2500, $x['kelvin'])) : 400));
    }
    exit('bad get');
}
$v = (float)($_GET['v'] ?? 0);
$bright = fn() => (int)max(1, min(100, (status_cached($cloud, $place)[$addr]['bright'] ?? 100)));
switch ($_GET['set'] ?? '') {
    case 'on': cmd_job($cloud, $place, [$addr], false, 'on', []); break;
    case 'off': cmd_job($cloud, $place, [$addr], false, 'off', []); break;
    case 'bright': cmd_job($cloud, $place, [$addr], false, 'bright', ['bright' => (int)$v]); break;
    case 'ct': cmd_job($cloud, $place, [$addr], false, 'white', ['kelvin' => (int)(1e6 / max(140, $v)), 'bright' => $bright()]); break;
    case 'hue': case 'sat':
        $c[$_GET['set']] = $v; $colors["$place-$addr"] = $c; file_put_contents($colorFile, json_encode($colors), LOCK_EX);
        [$h, $s] = [fmod($c['hue'], 360) / 60, max(0, min(1, $c['sat'] / 100))]; // HSV -> RGB at full value; brightness stays separate
        $x = 1 - abs(fmod($h, 2) - 1); $rgb = [[1, $x, 0], [$x, 1, 0], [0, 1, $x], [0, $x, 1], [$x, 0, 1], [1, 0, $x]][(int)$h % 6];
        $hex = implode('', array_map(fn($ch) => sprintf('%02x', (int)round(255 * (1 - $s + $s * $ch))), $rgb));
        cmd_job($cloud, $place, [$addr], false, 'rgb', ['color' => $hex]); break;
    default: exit('bad set');
}
echo 'ok';
