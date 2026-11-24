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
// hear the lights: each one reports by itself when its color changes. $changedAt[p][addr] = when it last reported a change.
$heard = []; $changedAt = []; $changes = [];
foreach ($link as $p => $l) $l->listen(function (string $msg) use ($p, &$heard, &$changedAt, &$changes) {
    foreach (json_decode($msg, true) ?: [] as $d) {
        if (!isset($d['a'], $d['d'])) continue;
        $a = (int)$d['a']; $x = hd_decode_status($d['d']); $old = $heard[$p][$a] ?? null;
        if (!$old || [$old['mode'], $old['hue'] ?? 0, $old['bright']] !== [$x['mode'], $x['hue'] ?? 0, $x['bright']]) { $changedAt[$p][$a] = microtime(true); $changes[$p][$a] = ($changes[$p][$a] ?? 0) + 1; }
        $heard[$p][$a] = $x;
    }
});
/** Does a light's reported look match an RRGGBB color? (hue within 20 degrees; gray-ish colors match any hue) */
$matches = function (?array $x, string $hex): bool {
    [$h, $sat] = hd_hex_hs($hex);
    return $x && $x['on'] && $x['mode'] === 'color' && ($sat < 10 || $x['sat'] < 10 || abs((($x['hue'] - $h + 540) % 360) - 180) <= 20);
};

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
/** Put a light (back) into its lane's group and out of the other lanes' groups. In two lanes' groups at once, a light
 *  gets both colors every step and looks stuck on one; in none, it never changes. */
$joinLane = function (int $p, int $a, int $i) use ($laneAddr, $pub, $K) {
    if ($laneAddr($p, $i) !== HD_LANE_GROUP + $i) return; // this lane is not sent by group
    for ($j = 0; $j < $K; $j++) if ($j !== $i) $pub($p, hd_payload_group($a, HD_LANE_GROUP + $j, false));
    $pub($p, hd_payload_group($a, HD_LANE_GROUP + $i, true));
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
        foreach ($laneAddrs as $i => $addrs) foreach ($addrs as $a) {
            if ($matches($stat[$a] ?? null, $colors[$i])) continue;
            $missing++;
            if ($round === 0) $joinLane($p, $a, $i); // missed the group paint: maybe not in the group at all (a dropped D7)
            $pub($p, hd_payload_rgb($a, $ct, $colors[$i])); $pub($p, hd_payload_rgb($a, $ct, $colors[$i]));
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
/** Self-repair while a changing scene runs: a light whose lane changed color twice or more, but that has reported no
 *  change for 3 s + 2 steps, is stuck. Send it its color directly and fix its group (the first 2 times). One light per
 *  step at most, and a light that does not answer 3 tries is tried again only every 2 minutes, so the bridge is never flooded. */
$wantAt = []; $prevWant = null; $fixed = []; $fixes = []; $steps = 0; $dumped = 0;
$repair = function (array $want) use (&$wantAt, &$prevWant, &$fixed, &$fixes, &$changedAt, &$changes, &$steps, &$dumped, $byPlace, $pub, $ct, $dt, $t0, $joinLane) {
    $now = microtime(true); $steps++;
    if ($now - $dumped > 30) { // health report: color changes each light reported vs steps sent (data/heard.json)
        $dumped = $now; $rows = [];
        foreach ($byPlace as $p => $laneAddrs) foreach ($laneAddrs as $addrs) foreach ($addrs as $a) $rows["$p-$a"] = ['changes' => $changes[$p][$a] ?? 0, 'fixes' => $fixes[$p][$a] ?? 0];
        @file_put_contents(store_dir() . '/heard.json', json_encode(['steps' => $steps, 'at' => date('c'), 'lights' => $rows]));
    }
    foreach ($want as $i => $hex) if (!$prevWant || $prevWant[$i] !== $hex) $wantAt[$i][] = $now;
    $prevWant = $want;
    foreach ($byPlace as $p => $laneAddrs) foreach ($laneAddrs as $i => $addrs) foreach ($addrs as $a) {
        $since = max($changedAt[$p][$a] ?? 0, $fixed[$p][$a] ?? 0, $t0);
        if (($changedAt[$p][$a] ?? 0) > ($fixed[$p][$a] ?? INF)) $fixes[$p][$a] = 0; // it answered: it is reachable
        if ($now - $since < 3 + 2 * $dt) continue;
        if (count(array_filter($wantAt[$i] ?? [], fn($t) => $t > $since)) < 2) continue; // its lane did not really change
        if (($fixes[$p][$a] ?? 0) >= 3 && $now - ($fixed[$p][$a] ?? 0) < 120) continue; // 3 tries got no answer: out of reach, try every 2 min
        $n = $fixes[$p][$a] = ($fixes[$p][$a] ?? 0) + 1;
        if ($n <= 2) $joinLane($p, $a, $i);
        $pub($p, hd_payload_rgb($a, $ct, $want[$i])); $pub($p, hd_payload_rgb($a, $ct, $want[$i]));
        $fixed[$p][$a] = $now;
        $log = store_dir() . '/repairs.log';
        if (@filesize($log) < 200000) @file_put_contents($log, date('c') . " place $p light $a: stuck, re-sent (fix $n)\n", FILE_APPEND);
        return; // one light per step
    }
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
        for ($k = 1; $at($k * $dt); $k++) { $w = array_map(fn($i) => $colors[($i + $k) % $K], array_keys($lanes)); $paint($w); $repair($w); }
        break;
    case 'fade': // like rotate, but each lane glides to the next color in m in-between steps round the color wheel
        for ($k = 1; $at($k * $dt); $k++) {
            [$tt, $s] = [intdiv($k, $m), $k % $m];
            $w = array_map(fn($i) => hd_mix_hex($colors[($i + $tt) % $K], $colors[($i + $tt + 1) % $K], $s / $m), array_keys($lanes));
            $paint($w); $repair($w);
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
