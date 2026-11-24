<?php
// Tiny JSON store for names, scenes and runner state.
// Uses ./data when the web server may write there, else the NAS temp dir (survives reboots, but grant
// the "http" user write access on lights/data in File Station to make it permanent).

function store_dir(): string {
    $d = __DIR__ . '/data';
    if (is_writable($d)) return $d;
    $t = sys_get_temp_dir() . '/lights';
    if (!is_dir($t)) mkdir($t, 0777, true);
    return $t;
}

function store_permanent(): bool { return is_writable(__DIR__ . '/data'); }

function store_load(): array {
    $f = store_dir() . '/store.json';
    $st = is_file($f) ? json_decode(file_get_contents($f), true) : null;
    return ($st ?: []) + ['names' => [], 'scenes' => [], 'lanes' => [], 'order' => []];
}

function store_save(array $st): void {
    file_put_contents(store_dir() . '/store.json', json_encode($st, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
}

/** Runner progress shown in the page: phase text + percent. Ignored if the run was replaced meanwhile. */
function run_progress(string $token, string $phase, int $pct): void {
    $r = run_load();
    if ($r && $r['token'] === $token) { $r['phase'] = $phase; $r['pct'] = max(0, min(100, $pct)); run_save($r); }
}

/** Runner state: what pattern is playing. Missing file = nothing playing. */
function run_file(): string { return store_dir() . '/run.json'; }
function run_load(): ?array { return is_file(run_file()) ? (json_decode(file_get_contents(run_file()), true) ?: null) : null; }
function run_save(?array $r): void { $r === null ? @unlink(run_file()) : file_put_contents(run_file(), json_encode($r), LOCK_EX); }

/** Devices of a place in the user's saved order; lights not in the saved list keep the cloud order after them. */
function ordered_devices(array $devices, int $place, array $st): array {
    $order = array_flip(array_map('intval', $st['order'][$place] ?? []));
    usort($devices, function ($a, $b) use ($order) {
        $ka = $order[(int)$a['meshAddress']] ?? PHP_INT_MAX; $kb = $order[(int)$b['meshAddress']] ?? PHP_INT_MAX;
        return $ka <=> $kb ?: (int)$a['meshAddress'] <=> (int)$b['meshAddress'];
    });
    return $devices;
}

/** Logged-in cloud client shared by every request and device (api.php, hb.php): cached on disk for an hour. */
function cloud_cached(array $config, bool $fresh = false): HaoDeng {
    $f = store_dir() . '/cloud.ser';
    if (!$fresh && is_file($f) && filemtime($f) > time() - 3600) {
        $c = @unserialize(file_get_contents($f), ['allowed_classes' => ['HaoDeng']]);
        if ($c instanceof HaoDeng && $c->places) return $c;
    }
    $c = new HaoDeng($config['username'], $config['password'], $config['country'] ?? 'US');
    file_put_contents($f, serialize($c), LOCK_EX);
    return $c;
}

/** Light status for a place, cached a few seconds: HomeKit asks about every light separately. */
function status_cached(HaoDeng $cloud, int $place, int $ttl = 8): array {
    $f = store_dir() . "/status-$place.json";
    if (is_file($f) && filemtime($f) > time() - $ttl) return json_decode(file_get_contents($f), true) ?: [];
    $st = $cloud->status($place);
    if ($st) file_put_contents($f, json_encode($st), LOCK_EX);
    return $st;
}

/** Commands still being delivered: ensure.php deletes its job file when done (a file older than a minute is a crashed job). */
function jobs_pending(): int { return count(array_filter(glob(store_dir() . '/jobs/*.json') ?: [], fn($f) => filemtime($f) > time() - 60)); }

/** Start ensure.php in the background to deliver one UI command to $addrs (see hd_command for names). */
function cmd_job(HaoDeng $cloud, int $place, array $addrs, bool $all, string $cmd, array $in): bool {
    $p = $cloud->places[$place] ?? null;
    if (!$p) return false;
    $ct = 5;
    foreach ($p['devices'] as $d) if (count($addrs) === 1 && (int)$d['meshAddress'] === $addrs[0]) $ct = (int)$d['controlType'];
    if (!hd_command($cmd, $ct, $in)) return false;
    $job = ['id' => bin2hex(random_bytes(4)), 'place' => $place, 'placeData' => $p, 'broker' => $cloud->broker, 'addrs' => $addrs,
        'all' => $all, 'cmd' => $cmd, 'ct' => $ct, 'in' => array_intersect_key($in, array_flip(['bright', 'kelvin', 'color']))];
    $dir = store_dir() . '/jobs'; if (!is_dir($dir)) mkdir($dir);
    file_put_contents("$dir/{$job['id']}.json", json_encode($job));
    foreach ($addrs as $a) file_put_contents("$dir/lock-$place-$a", $job['id']); // newest command per light wins
    exec('nohup /usr/bin/php ' . escapeshellarg(__DIR__ . '/ensure.php') . ' ' . $job['id'] . ' > /dev/null 2>&1 &');
    return true;
}

function runner_start(): void { exec('nohup /usr/bin/php ' . escapeshellarg(__DIR__ . '/run.php') . ' > /dev/null 2>&1 &'); }

function stop_runner(): void {
    $r = run_load();
    run_save(null); // runners watch this file and quit within a second; the blinker restores the light first
    if ($r && !empty($r['pid']) && ($r['mode'] ?? '') !== 'identify') exec('kill ' . (int)$r['pid'] . ' 2>/dev/null');
}

/** Play a saved scene (stops whatever runs). False if unknown. */
function scene_play(HaoDeng $cloud, array $st, string $id, int $place): bool {
    $sc = $st['scenes'][$id] ?? null;
    if (!$sc) return false;
    stop_runner();
    $places = ($sc['scope'] ?? 'place') === 'all' ? array_keys($cloud->places) : [(int)$sc['place']];
    run_save(['id' => $id, 'place' => $place, 'places' => $places, 'name' => $sc['name'], 'token' => bin2hex(random_bytes(4)), 'started' => time(), 'pid' => 0]);
    runner_start();
    return true;
}

/** Stop a scene or blink. A stopped scene hands the slow "back to normal" reset to a background runner. */
function scene_stop(int $place): void {
    $run = run_load();
    stop_runner();
    if ($run && !in_array($run['mode'] ?? '', ['identify', 'reset'])) {
        run_save(['mode' => 'reset', 'place' => $place, 'places' => $run['places'] ?? [(int)$run['place']], 'name' => 'Back to normal',
            'token' => bin2hex(random_bytes(4)), 'started' => time(), 'pid' => 0]);
        runner_start();
    }
}
