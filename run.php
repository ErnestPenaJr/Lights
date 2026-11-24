<?php
// Pattern runner. Started in the background by api.php "play"; reads run.json for what to play,
// stops when run.json disappears or changes (api.php "stop"/"play"), or after 12 hours.
// Usage for debugging on the NAS: php run.php   (with a run.json in place)
require __DIR__ . '/haodeng.php';
require __DIR__ . '/store.php';

$run = run_load();
if (!$run) exit("nothing to run\n");
$run['pid'] = getmypid();
run_save($run);
$token = $run['token'];
$st = store_load();
$config = include __DIR__ . '/config.php';
$cloud = new HaoDeng($config['username'], $config['password'], $config['country'] ?? 'US');

if (($run['mode'] ?? '') === 'identify') { // blink one light until stopped, then leave it as it was
    run_progress($token, 'running', 100);
    [$s, $topic] = $cloud->open((int)$run['place']);
    $addr = (int)$run['addr']; $ct = (int)$run['ct']; $id = 1; $on = false;
    $alive = fn() => ($r = run_load()) && $r['token'] === $token && time() - $run['started'] < 600;
    while ($alive()) {
        $on = !$on;
        mqtt_pub($s, $topic, json_encode($on ? hd_payload_on($addr, $ct) : hd_payload_off($addr, $ct)), $id++);
        usleep(700000);
    }
    mqtt_pub($s, $topic, json_encode($run['wasOn'] ? hd_payload_on($addr, $ct) : hd_payload_off($addr, $ct)), $id++);
    fwrite($s, "\xE0\x00"); fclose($s);
    $r = run_load(); if ($r && $r['token'] === $token) run_save(null); // timed out on our own: clear the flag
    exit;
}

if (($run['mode'] ?? '') === 'reset') { // after a stopped scene: every light in those places back to the everyday look
    $n = count($run['places']); $log = [];
    foreach (array_values($run['places']) as $i => $p) {
        $name = $cloud->places[(int)$p]['name'];
        $left = hd_reset_place($cloud, (int)$p, $config, function ($round, $rounds) use ($token, $name, $i, $n) {
            run_progress($token, "Back to normal: $name, check " . ($round + 1) . " of $rounds", (int)(($i + $round / $rounds) / $n * 100));
        });
        $log[] = date('c') . " $name: " . ($left ? 'still wrong: #' . implode(', #', $left) : 'all lights ok');
    }
    file_put_contents(store_dir() . '/last-reset.log', implode("\n", $log) . "\n");
    $r = run_load(); if ($r && $r['token'] === $token) run_save(null);
    exit;
}

$scene = $st['scenes'][$run['id']] ?? exit("no scene\n");
$ct = 5;
$placeIdxs = ($scene['scope'] ?? 'place') === 'all' ? array_keys($cloud->places) : [(int)$scene['place']];
$alive = fn() => ($r = run_load()) && $r['token'] === $token && time() - $run['started'] < 12 * 3600;

// lanes -> per place: $byPlace[place][lane] = [mesh addr, ...]   (light keys are "<place>-<addr>"; bare ints = scene's place)
$lanes = array_values(array_filter($scene['lanes'], fn($l) => count($l['lights']) > 0));
$byPlace = [];
foreach ($lanes as $i => $l) foreach ($l['lights'] as $key) {
    [$p, $a] = is_int($key) || ctype_digit((string)$key) ? [(int)$scene['place'], (int)$key] : array_map('intval', explode('-', $key));
    if (in_array($p, $placeIdxs)) $byPlace[$p][$i][] = $a;
}
if (!$byPlace) exit("empty scene\n");
$K = count($lanes);
run_progress($token, 'Connecting', 2);
$allAddrs = [];
foreach ($byPlace as $p => $_) $allAddrs[$p] = array_map(fn($d) => (int)$d['meshAddress'], $cloud->places[$p]['devices']);

// one paced, long-lived link per place (see HdLink: no waiting for broker replies, steady spacing, reconnects)
$link = [];
foreach (array_keys($byPlace) as $p) $link[$p] = new HdLink($cloud, $p);
$pub = function (int $p, array $payload) use ($link) { $link[$p]->send($payload); };
$broadcast = function (array $payload) use ($byPlace, $pub) { foreach (array_keys($byPlace) as $p) $pub($p, $payload); };

// ---- setup: put every light of lane i into mesh group HD_LANE_GROUP+i (only when the layout changed; the bridge chokes on long bursts)
$changed = false;
foreach ($byPlace as $p => $laneAddrs) {
    $prev = $st['lanes'][$p] ?? []; $now = [];
    foreach ($laneAddrs as $i => $addrs) foreach ($addrs as $a) $now[$a] = HD_LANE_GROUP + $i;
    if ($now == $prev) continue;
    $total = 2 * count($now); $done = 0;
    for ($pass = 0; $pass < 2; $pass++) { // twice: the cloud drops ~15% of a burst
        foreach ($now as $a => $g) {
            if (isset($prev[$a]) && $prev[$a] !== $g) $pub($p, hd_payload_group($a, $prev[$a], false));
            $pub($p, hd_payload_group($a, $g, true));
            if (++$done % 5 === 0) run_progress($token, "Grouping lights in {$cloud->places[$p]['name']}", 5 + (int)(45 * $done / $total));
        }
    }
    $st = store_load(); $st['lanes'][$p] = $now; store_save($st); $changed = true;
}
if ($changed) sleep(1);

/** Address to use for lane i in place p: the light itself, broadcast, or the lane group. */
$laneAddr = function (int $p, int $i) use ($byPlace, $allAddrs): int {
    $n = count($byPlace[$p][$i]);
    if ($n === 1) return $byPlace[$p][$i][0];
    if ($n === count($allAddrs[$p])) return HD_ALL;
    return HD_LANE_GROUP + $i;
};
$paint = function (array $colors) use ($byPlace, $laneAddr, $pub, $ct) { // one color per lane, in every place
    foreach ($byPlace as $p => $laneAddrs) foreach (array_keys($laneAddrs) as $i) $pub($p, hd_payload_rgb($laneAddr($p, $i), $ct, $colors[$i]));
};
$colors = array_column($lanes, 'color');
run_progress($token, 'Sending colors', 55);
for ($i = 0; $i < 3; $i++) { $paint($colors); usleep(500000); } // initial colors, 3x for reliability
// then check every light and re-send its color one by one to the ones that missed the group message (the mesh drops some)
for ($round = 0; $round < 3; $round++) {
    run_progress($token, 'Checking every light, round ' . ($round + 1) . ' of 3', 65 + 10 * $round);
    sleep(2); $missing = 0;
    foreach ($byPlace as $p => $laneAddrs) {
        try { $stat = $cloud->status($p); } catch (Throwable $e) { continue; }
        if (!$stat) continue;
        foreach ($laneAddrs as $i => $addrs) {
            [$h, $sat] = hd_hex_hs($colors[$i]);
            foreach ($addrs as $a) {
                $x = $stat[$a] ?? null;
                $ok = $x && $x['on'] && $x['mode'] === 'color' && ($sat < 10 || $x['sat'] < 10 || abs((($x['hue'] - $h + 540) % 360) - 180) <= 20);
                if (!$ok) { $missing++; $pub($p, hd_payload_rgb($a, $ct, $colors[$i])); $pub($p, hd_payload_rgb($a, $ct, $colors[$i])); }
            }
        }
    }
    if (!$missing) break;
}
$period = 0.5 + (10 - $scene['speed']) * 0.35; // seconds per step: 0.5 (fast) .. 3.65 (slow)
[$dt, $m] = hd_fx_timing($scene['effect'], $period, $K, count($byPlace));
$t0 = microtime(true) + 0.3;
run_progress($token, 'running', 100, ['t0' => round($t0, 3)]); // the page runs its preview on this same clock
/** Wait until $t seconds after t0, in small slices: stop stays quick and the links stay alive. Steps never drift:
 *  if sending ran late, the next step simply starts on time (or at once). */
$at = function (float $t) use ($alive, $t0, $link): bool {
    while (($left = $t0 + $t - microtime(true)) > 0.001) {
        if (!$alive()) return false;
        foreach ($link as $l) $l->idle();
        usleep((int)(min(0.25, $left) * 1e6));
    }
    return $alive();
};
$sceneLights = []; // in lane order, the same order index.php uses, so the page shows the same twinkle
foreach ($lanes as $i => $l) foreach ($l['lights'] as $key) {
    [$p, $a] = is_int($key) || ctype_digit((string)$key) ? [(int)$scene['place'], (int)$key] : array_map('intval', explode('-', $key));
    if (isset($byPlace[$p])) $sceneLights[] = [$p, $a];
}

switch ($scene['effect']) {
    case 'static':
        break;
    case 'blink': // step k: odd = off, even = on
        for ($k = 1; $at($k * $dt); $k++) $broadcast($k % 2 ? hd_payload_off(HD_ALL, $ct) : hd_payload_on(HD_ALL, $ct));
        break;
    case 'breathe': // one breath = m steps along a cosine curve
        $levels = hd_breathe_levels($m);
        for ($k = 1; $at($k * $dt); $k++) $broadcast(hd_payload_brightness(HD_ALL, $ct, $levels[$k % $m]));
        break;
    case 'rotate': // each lane takes the next lane's color every step (alternating for 2 lanes, chase for more)
        for ($k = 1; $at($k * $dt); $k++) $paint(array_map(fn($i) => $colors[($i + $k) % $K], array_keys($lanes)));
        break;
    case 'fade': // like rotate, but each lane glides to the next color in m in-between steps round the color wheel
        for ($k = 1; $at($k * $dt); $k++) {
            [$tt, $s] = [intdiv($k, $m), $k % $m];
            $paint(array_map(fn($i) => hd_mix_hex($colors[($i + $tt) % $K], $colors[($i + $tt + 1) % $K], $s / $m), array_keys($lanes)));
        }
        break;
    case 'twinkle': // twinkle c: at c*dt + P/2 one light dips; at (c+1)*dt it comes back (twice: a far light drops 1 in 3)
        $N = count($sceneLights); $prev = null;
        for ($c = 0; $at($c * $dt + $period / 2); $c++) {
            [$p, $a] = $sceneLights[hd_twinkle_pick($c, $N)];
            $pub($p, hd_payload_brightness($a, $ct, 5));
            if (!$at(($c + 1) * $dt)) break;
            $pub($p, hd_payload_brightness($a, $ct, 100)); $pub($p, hd_payload_brightness($a, $ct, 100));
        }
        break;
    case 'rainbow': // every light steps 10 degrees round the color wheel each step
        for ($k = 1; $at($k * $dt); $k++) {
            $h = ($k * 10) % 360; $x = 1 - abs(fmod($h / 60, 2) - 1); $c = 255; $mm = (int)round($c * $x);
            [$r, $g, $b] = [[$c, $mm, 0], [$mm, $c, 0], [0, $c, $mm], [0, $mm, $c], [$mm, 0, $c], [$c, 0, $mm]][intdiv($h, 60) % 6];
            $broadcast(hd_payload_rgb(HD_ALL, $ct, sprintf('%02x%02x%02x', $r, $g, $b)));
        }
        break;
}
foreach ($link as $l) $l->close();
$r = run_load();
if ($scene['effect'] === 'static') { if ($r && $r['token'] === $token) run_save(null); } // still scene: painted once, stays that way
elseif ($r && $r['token'] === $token) { // ran out the 12 h clock on our own (a user Stop is handled by api.php): back to the everyday look
    run_save(null);
    foreach (array_keys($byPlace) as $p) hd_reset_place($cloud, $p, $config);
}
