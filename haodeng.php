<?php
// Hao Deng mesh lights via the Hao Deng WiFi bridge (Magic Hue cloud).
// Protocol reverse-engineered by https://github.com/Pharylon/hao_deng_cloud
// No dependencies: openssl for the login checkcode, file_get_contents for REST, fsockopen for MQTT.

const HD_SERVERS = ['US' => 'us', 'AU' => 'oa', 'CN' => 'cn', 'JP' => 'dy',
    'GB' => 'eu', 'ES' => 'eu', 'FR' => 'eu', 'DE' => 'eu', 'IT' => 'eu', 'RU' => 'eu'];
const HD_CHECKCODE_KEY = '0FC154F9C01DFA9656524A0EFABC994F';

// ---- pure payload builders (checked in test.php) ----

function hd_checkcode(string $ts): string {
    return bin2hex(openssl_encrypt('ZG' . $ts, 'aes-256-ecb', HD_CHECKCODE_KEY, OPENSSL_RAW_DATA));
}

function hd_payload_on(int $addr, int $ct): array {
    return ['dstAdr' => $addr, 'opCode' => 'D0', 'data' => sprintf('%02X01FF000000000300', $ct)];
}

function hd_payload_off(int $addr, int $ct): array {
    return ['dstAdr' => $addr, 'opCode' => 'D0', 'data' => sprintf('%02X0100000000000300', $ct)];
}

function hd_payload_rgb(int $addr, int $ct, string $rrggbb): array {
    [$r, $g, $b] = array_map('hexdec', str_split(substr($rrggbb, 0, 6), 2));
    while ($r + $g + $b > 630) { $r--; $g--; $b--; } // cloud rejects near-white RGB; app clamps too
    return ['dstAdr' => $addr, 'opCode' => 'E2', 'data' => sprintf('%02X60%02X%02X%02X00000200', $ct, $r, $g, $b)];
}

function hd_payload_white(int $addr, int $ct, int $kelvin, int $brightness = 100): array {
    $kelvin = max(2500, min(6535, $kelvin));
    $temp = (int)(($kelvin - 2500) / (6535 - 2500) * 100);
    return ['dstAdr' => $addr, 'opCode' => 'E2', 'data' => sprintf('%02X62%02X%02X0000000200', $ct, $temp, $brightness)];
}

const HD_ALL = 65535;        // mesh broadcast address: every light in the place
const HD_LANE_GROUP = 0x80F0; // our own mesh groups for pattern lanes: 0x80F0 + lane index

/** Add (or remove) a light to a mesh group. Note: no control-type prefix byte on this opcode. */
function hd_payload_group(int $addr, int $gid, bool $add): array {
    return ['dstAdr' => $addr, 'opCode' => 'D7', 'data' => sprintf('%02X%02X%02X0000000000', $add ? 1 : 0, $gid & 0xFF, $gid >> 8)];
}

/** Brightness only (1-100), keeps the current color. */
function hd_payload_brightness(int $addr, int $ct, int $percent): array {
    return ['dstAdr' => $addr, 'opCode' => 'D0', 'data' => sprintf('%02X02%02X060000000300', $ct, max(1, min(100, $percent)))];
}

/** RGB scaled by brightness percent, so a lane can dim without losing its color. */
function hd_payload_rgb_dim(int $addr, int $ct, string $rrggbb, int $percent): array {
    [$r, $g, $b] = array_map('hexdec', str_split(substr($rrggbb, 0, 6), 2));
    $k = max(0, min(100, $percent)) / 100;
    return hd_payload_rgb($addr, $ct, sprintf('%02X%02X%02X', (int)round($r * $k), (int)round($g * $k), (int)round($b * $k)));
}

/** Hue (0-360) and saturation (0-100) of an RRGGBB color, to compare with what a light reports. */
function hd_hex_hs(string $rrggbb): array {
    [$r, $g, $b] = array_map(fn($h) => hexdec($h) / 255, str_split(substr($rrggbb, 0, 6), 2));
    $max = max($r, $g, $b); $min = min($r, $g, $b); $d = $max - $min;
    if ($d == 0) return [0, 0];
    $h = $max == $r ? fmod(($g - $b) / $d, 6) : ($max == $g ? ($b - $r) / $d + 2 : ($r - $g) / $d + 4);
    return [(int)round(fmod($h * 60 + 360, 360)), (int)round($d / $max * 100)];
}

/** Brightness levels for one breathe cycle of $n steps: a smooth cosine from 100% down to 5% and back. */
function hd_breathe_levels(int $n): array {
    $n = max(2, $n);
    return array_map(fn($i) => (int)round(5 + 95 * (1 + cos(2 * M_PI * $i / $n)) / 2), range(0, $n - 1));
}

/** Color $t (0..1) of the way from $a to $b. Saturated colors travel round the color wheel (red to green passes
 *  yellow, not a dim brown); a white or gray end mixes plain RGB. The page's preview uses the same math. */
function hd_mix_hex(string $a, string $b, float $t): string {
    $t = max(0.0, min(1.0, $t));
    $rgb = fn(string $h) => array_map(fn($x) => hexdec($x) / 255, str_split(substr(ltrim($h, '#'), 0, 6), 2));
    [$ca, $cb] = [$rgb($a), $rgb($b)];
    $hsv = function (array $c): array {
        [$r, $g, $b] = $c; $max = max($c); $d = $max - min($c);
        $h = $d == 0 ? 0 : ($max == $r ? fmod(($g - $b) / $d + 6, 6) : ($max == $g ? ($b - $r) / $d + 2 : ($r - $g) / $d + 4)) * 60;
        return [$h, $max ? $d / $max : 0, $max];
    };
    [$ha, $sa, $va] = $hsv($ca); [$hb, $sb, $vb] = $hsv($cb);
    if ($sa < 0.15 || $sb < 0.15) $out = array_map(fn($x, $y) => $x + ($y - $x) * $t, $ca, $cb);
    else {
        $dh = fmod($hb - $ha + 540, 360) - 180; // shortest way round
        $h = fmod($ha + $dh * $t + 360, 360) / 60; $s = $sa + ($sb - $sa) * $t; $v = $va + ($vb - $va) * $t;
        $f = $h - floor($h); [$p, $q, $u] = [$v * (1 - $s), $v * (1 - $s * $f), $v * (1 - $s * (1 - $f))];
        $out = [[$v, $u, $p], [$q, $v, $p], [$p, $v, $u], [$p, $q, $v], [$u, $p, $v], [$v, $p, $q]][(int)floor($h) % 6];
    }
    return implode('', array_map(fn($x) => sprintf('%02x', (int)round($x * 255)), $out));
}

// ---- minimal MQTT 3.1.1: CONNECT, one PUBLISH (qos 1), DISCONNECT ----

function mqtt_str(string $s): string { return pack('n', strlen($s)) . $s; }

function mqtt_packet(int $type, string $body): string {
    $len = strlen($body); $enc = '';
    do { $d = $len % 128; $len = intdiv($len, 128); $enc .= chr($len ? $d | 0x80 : $d); } while ($len);
    return chr($type) . $enc . $body;
}

function mqtt_connect_packet(string $clientId, string $user, string $pass): string {
    return mqtt_packet(0x10, mqtt_str('MQTT') . chr(4) . chr(0xC2) . pack('n', 60)
        . mqtt_str($clientId) . mqtt_str($user) . mqtt_str($pass));
}

function mqtt_publish_packet(string $topic, string $payload, int $id = 1): string {
    return mqtt_packet(0x32, mqtt_str($topic) . pack('n', $id) . $payload);
}

function mqtt_open(string $host, string $user, string $pass) {
    $s = fsockopen($host, 1883, $errno, $errstr, 10);
    if (!$s) throw new RuntimeException("mqtt connect: $errstr");
    stream_set_timeout($s, 10);
    fwrite($s, mqtt_connect_packet(bin2hex(random_bytes(8)), $user, $pass));
    $ack = fread($s, 4);
    if (strlen($ack) < 4 || ord($ack[3]) !== 0) throw new RuntimeException('mqtt refused login');
    return $s;
}

/** Read one packet: [type byte, body] or null on timeout/close. */
function mqtt_read_packet($s): ?array {
    $h = fread($s, 1);
    if ($h === false || $h === '') return null;
    $len = 0; $mult = 1;
    do {
        $b = fread($s, 1);
        if ($b === false || $b === '') return null;
        $len += (ord($b) & 0x7F) * $mult; $mult *= 128;
    } while (ord($b) & 0x80);
    $body = '';
    while (strlen($body) < $len) {
        $c = fread($s, $len - strlen($body));
        if ($c === false || $c === '') return null;
        $body .= $c;
    }
    return [ord($h), $body];
}

function mqtt_publish_once(string $host, string $user, string $pass, string $topic, string $payload): void {
    $s = mqtt_open($host, $user, $pass);
    fwrite($s, mqtt_publish_packet($topic, $payload));
    $puback = fread($s, 4); // 0x40 0x02 id id
    fwrite($s, "\xE0\x00");
    fclose($s);
    if (strlen($puback) < 4 || ord($puback[0]) !== 0x40) throw new RuntimeException('mqtt: no PUBACK');
}

/** Publish on an open socket; waits for the PUBACK. */
function mqtt_pub($s, string $topic, string $payload, int $id): void {
    fwrite($s, mqtt_publish_packet($topic, $payload, $id));
    mqtt_read_packet($s); // PUBACK (or whatever arrives first; good enough for fire-and-forget)
}

/** Subscribe to $subTopic, publish $request to $reqTopic, hand each received payload to $onMessage.
 *  Stops after $seconds, or earlier when $onMessage returns true. */
function mqtt_collect(string $host, string $user, string $pass, string $subTopic, string $reqTopic, string $request, float $seconds, callable $onMessage): void {
    $s = mqtt_open($host, $user, $pass);
    fwrite($s, mqtt_packet(0x82, pack('n', 1) . mqtt_str($subTopic) . chr(1)));
    mqtt_read_packet($s); // SUBACK
    fwrite($s, mqtt_packet(0x30, mqtt_str($reqTopic) . $request));
    $deadline = microtime(true) + $seconds;
    stream_set_timeout($s, 1);
    while (microtime(true) < $deadline && ($pkt = mqtt_read_packet($s))) {
        [$type, $body] = $pkt;
        if ($type >> 4 !== 3) continue;
        $pos = 2 + unpack('n', $body)[1];
        if (($type >> 1) & 3) { fwrite($s, "\x40\x02" . substr($body, $pos, 2)); $pos += 2; } // PUBACK qos1
        if ($onMessage(substr($body, $pos))) break;
    }
    fwrite($s, "\xE0\x00");
    fclose($s);
}

// ---- paced link: the way patterns talk to a bridge ----

const HD_GAP = 0.15; // seconds between two commands to one bridge, send to send (~6.7/s; the cloud drops more near 10/s)

/** One long-lived MQTT connection to a place's bridge. send() never waits for the broker's reply (that cost a 62 ms round
 *  trip per command before), it keeps commands exactly $gap apart on a steady clock, reopens a dead socket, and pings an idle link. */
class HdLink {
    private $s = null;
    private string $topic = '';
    private int $id = 0;
    private float $next = 0, $last = 0;
    public int $sent = 0, $reconnects = 0;

    public function __construct(private HaoDeng $cloud, private int $place, private float $gap = HD_GAP) { $this->connect(); }

    private function connect(): void {
        if (is_resource($this->s)) @fclose($this->s);
        for ($try = 0; ; $try++) {
            try {
                [$this->s, $this->topic] = $this->cloud->open($this->place);
                stream_set_blocking($this->s, false);
                $this->last = microtime(true);
                return;
            } catch (Throwable $e) {
                if ($try >= 4) throw $e;
                usleep((int)(500000 * 2 ** $try)); // 0.5, 1, 2, 4 s
            }
        }
    }

    private function write(string $pkt): bool {
        if (!is_resource($this->s)) return false;
        for ($tries = 0; $pkt !== '' && $tries < 50; $tries++) {
            $n = @fwrite($this->s, $pkt);
            if ($n === false || feof($this->s)) return false;
            $pkt = substr($pkt, $n);
            if ($pkt !== '') usleep(2000);
        }
        return $pkt === '';
    }

    /** Read and drop what the broker sent (PUBACKs, ping replies). Returns false when the socket is closed. */
    private function drain(): bool {
        if (!is_resource($this->s)) return false;
        while (($b = @fread($this->s, 8192)) !== false && $b !== '') {}
        return !feof($this->s);
    }

    /** When the next command may go out (microtime). */
    public function readyAt(): float { return $this->next; }

    public function send(array $payload): void {
        $wait = $this->next - microtime(true);
        if ($wait > 0) usleep((int)($wait * 1e6));
        $t = microtime(true);
        $this->id = $this->id % 65535 + 1;
        $pkt = mqtt_publish_packet($this->topic, json_encode($payload), $this->id);
        if (!$this->drain() || !$this->write($pkt)) { $this->reconnects++; $this->connect(); $this->write($pkt); }
        $this->sent++;
        $this->last = $t;
        $this->next = $t + $this->gap;
    }

    /** Call while waiting: drops replies, keeps the connection alive (keepalive is 60 s). */
    public function idle(): void {
        if (!$this->drain()) { $this->reconnects++; $this->connect(); return; }
        if (microtime(true) - $this->last > 25) { $this->write("\xC0\x00"); $this->last = microtime(true); }
    }

    public function close(): void {
        if (!is_resource($this->s)) return;
        @fwrite($this->s, "\xE0\x00"); @fclose($this->s); $this->s = null;
    }
}

// ---- pattern timing (pure; index.php simAt mirrors it, test.php checks it) ----

/** Step length for an effect, in seconds. Steps run on a fixed clock: step k starts at k * dt after "running".
 *  $P = the speed's period, $K = lanes, $n = places. Each step leaves room for its commands plus a 0.1 s margin.
 *  Returns [dt, m] where m = in-between colors per change (fade) or levels per breath (breathe). */
function hd_fx_timing(string $effect, float $P, int $K, int $n): array {
    $burst = fn(int $cmds) => $cmds * HD_GAP + 0.1;
    switch ($effect) {
        case 'blink': return [max($P, $burst($n)), 1];
        case 'breathe': $cyc = 2.5 * $P; $m = max(10, min(24, (int)round($cyc / 0.3))); return [max($cyc / $m, $burst($n)), $m];
        case 'rotate': return [max($P, $burst($K * $n)), 1];
        case 'fade': $c = $K * $n * HD_GAP + 0.15; $m = max(1, min(6, (int)floor($P / $c))); return [max($P / $m, $c), $m];
        case 'twinkle': return [$P / 2 + max(0.35, $P / 3), 1]; // one twinkle; the dip starts at P/2 into it
        case 'rainbow': return [max($P / 6, $burst($n), 0.35), 1]; // 10 degrees of hue per step
    }
    return [$P, 1];
}

/** 32-bit hash (same bits as index.php mix32), used to pick twinkle lights so the page shows the same light as the yard. */
function hd_mix32(int $i): int {
    $imul = fn(int $a, int $b): int => (($a * ($b & 0xFFFF)) + ((($a * ($b >> 16)) & 0xFFFF) << 16)) & 0xFFFFFFFF;
    $x = $imul(($i & 0xFFFFFFFF) ^ 0x9E3779B9, 0x85EBCA6B);
    $x ^= $x >> 13;
    $x = $imul($x, 0xC2B2AE35);
    return ($x ^ ($x >> 16)) & 0xFFFFFFFF;
}

/** Which of $N lights dips in twinkle number $c (never the same as the one picked for $c - 1 by this rule). */
function hd_twinkle_pick(int $c, int $N): int {
    $a = hd_mix32($c) % $N;
    if ($N > 1 && $a === hd_mix32($c - 1) % $N) $a = ($a + 1) % $N;
    return $a;
}

// ---- cloud ----

class HaoDeng {
    /** Each place: name, id, devices, groups, software (MQTT creds), hardware (bridge). */
    public array $places = [];
    public string $broker = '';
    private string $base = '', $token = '';

    /** With no arguments: an empty client (set ->broker and ->places yourself, e.g. from a job file) that skips the cloud login. */
    public function __construct(?string $user = null, string $pass = '', string $country = 'US') {
        if ($user === null) return;
        $r = HD_SERVERS[$country] ?? 'us';
        $this->base = "http://{$r}meshcloud.magichue.net:8081/MeshClouds/";
        $this->broker = "$r.meshbroker.magichue.net";
        $ts = (string)(int)(microtime(true) * 1000);
        $login = $this->call('apixp/User001/LoginForUser/ZG', [
            'userID' => $user, 'password' => md5($pass), 'appSys' => 'Android',
            'timestamp' => $ts, 'appVer' => '', 'checkcode' => hd_checkcode($ts)]);
        $this->token = $login['auth_token'];
        $uid = urlencode($login['userId']);
        foreach ($this->call("apixp/MeshData/GetMyMeshPlaceItems/ZG?userId=$uid") as $p) {
            $id = $p['placeUniID'];
            $place = ['name' => $p['displayName'] ?? $id, 'id' => $id,
                'devices' => $this->call("apixp/MeshData/GetMyMeshDeviceItems/ZG?placeUniID=$id&userId=$uid"),
                'groups' => $this->call("apixp/MeshData/GetMyMeshGroupInfoItems/ZG?placeUniID=$id&userId=$uid")];
            foreach ($this->call("apixp/Mqtt/getMasterControlData/ZG?placeUniID=$id") as $m) {
                if ($m['deviceType'] === 'SOFTWARE') $place['software'] = $m;
                if ($m['deviceType'] === 'HARDWARE') $place['hardware'] = $m;
            }
            $this->places[] = $place;
        }
    }

    private function call(string $path, ?array $body = null): array {
        $ctx = stream_context_create(['http' => [
            'method' => $body ? 'POST' : 'GET', 'timeout' => 30, 'ignore_errors' => true,
            'header' => "User-Agent: HaoDeng/1.5.7(ANDROID,10,en-US)\r\nAccept: application/json\r\n"
                . "Content-Type: application/json\r\ntoken: {$this->token}\r\n",
            'content' => $body ? json_encode($body) : '']]);
        $raw = file_get_contents($this->base . $path, false, $ctx);
        $json = json_decode($raw ?: '', true);
        if (!isset($json['result'])) throw new RuntimeException("cloud $path: " . ($json['error'] ?? $raw ?: 'no response'));
        return $json['result'];
    }

    /** Target key: "<place index>-<mesh address>" for a light, "<place index>-g<group id>" for a group. */
    public static function key(int $place, array $deviceOrGroup): string {
        return isset($deviceOrGroup['groupID']) ? "$place-g{$deviceOrGroup['groupID']}" : "$place-{$deviceOrGroup['meshAddress']}";
    }

    /** [place index, mesh address, control type] for a key, or null. */
    public function target(string $key): ?array {
        if (!preg_match('/^(\d+)-(g?)(\d+)$/', $key, $m) || !isset($this->places[$m[1]])) return null;
        [$pi, $isGroup, $addr] = [(int)$m[1], $m[2] === 'g', (int)$m[3]];
        foreach ($this->places[$pi]['devices'] as $d) {
            if (!$isGroup && (int)$d['meshAddress'] === $addr) return [$pi, $addr, (int)$d['controlType']];
            if ($isGroup) for ($i = 1; $i <= 8; $i++) {
                if ((int)($d["group{$i}ID"] ?? 0) === $addr) return [$pi, $addr, (int)$d['controlType']]; // group borrows first member's type
            }
        }
        return $isGroup ? [$pi, $addr, 5] : null;
    }

    public function send(int $place, array $payload): void {
        $this->sendMany($place, [$payload]);
    }

    /** Open one MQTT connection for a place: returns [socket, control topic]. Caller closes with fclose. */
    public function open(int $place): array {
        $sw = $this->places[$place]['software'];
        $s = mqtt_open($this->broker, "{$sw['deviceName']}&{$sw['productKey']}", $sw['devicePwd']);
        return [$s, "/{$sw['productKey']}/{$sw['deviceName']}/control"];
    }

    /** Send several payloads on one connection, $gap seconds apart (send to send). ~7/s is the cloud's comfortable limit. */
    public function sendMany(int $place, array $payloads, float $gap = HD_GAP): void {
        $link = new HdLink($this, $place, $gap);
        foreach ($payloads as $p) $link->send($p);
        $link->close();
    }

    public function identify(int $place, int $addr, int $ct, bool $wasOn = true): void {
        [$s, $topic] = $this->open($place);
        $seq = [];
        for ($i = 0; $i < 3; $i++) { $seq[] = hd_payload_off($addr, $ct); $seq[] = hd_payload_on($addr, $ct); }
        if (!$wasOn) $seq[] = hd_payload_off($addr, $ct);
        foreach ($seq as $i => $p) { mqtt_pub($s, $topic, json_encode($p), $i + 1); usleep(600000); }
        fwrite($s, "\xE0\x00");
        fclose($s);
    }

    /** Ask a place's bridge for every light's state. Returns mesh address => decoded state. */
    public function status(int $place, float $seconds = 2.5): array {
        $p = $this->places[$place]; $sw = $p['software']; $hw = $p['hardware'];
        $want = count($p['devices']); $out = [];
        mqtt_collect($this->broker, "{$sw['deviceName']}&{$sw['productKey']}", $sw['devicePwd'],
            "/{$hw['productKey']}/{$hw['deviceName']}/subStatus",
            "/{$sw['productKey']}/{$sw['deviceName']}/request", json_encode(['type' => 'immediateNOW', 'ver' => 1]), $seconds,
            function (string $m) use (&$out, $want): bool {
                foreach (json_decode($m, true) ?: [] as $d) {
                    if (isset($d['a'], $d['d'])) $out[(int)$d['a']] = hd_decode_status($d['d']);
                }
                return count($out) >= $want;
            });
        return $out;
    }
}

/** Decode a status hex string from /subStatus (format from the HA integration). */
function hd_decode_status(string $d): array {
    // byte 0 varies (0x6F-0x75, 0xED...) while the light is in reach; it is 00 when the bridge cannot reach the light and
    // answers from memory: the other bytes are then stale and commands do not take (seen 2026-09-16 on every light of both areas)
    $link = hexdec(substr($d, 0, 2)) > 0;
    $bright = hexdec(substr($d, 2, 2));
    $on = $link && $bright > 0; // an "off" light reports 0%
    $sat = hexdec(substr($d, 4, 2));
    if ($sat > 63) { // white mode: byte 3 is color temp percent
        $kelvin = (int)(hexdec(substr($d, 6, 2)) / 100 * (6535 - 2500) + 2500);
        return ['on' => $on, 'link' => $link, 'bright' => $bright, 'mode' => 'white', 'kelvin' => $kelvin, 'raw' => $d];
    }
    $hue = (int)(hexdec(substr($d, 6, 2)) / 255 * 360);
    return ['on' => $on, 'link' => $link, 'bright' => $bright, 'mode' => 'color', 'hue' => $hue, 'sat' => (int)($sat / 63 * 100), 'raw' => $d];
}

/** Send $payload(addr) to lights and make sure it took: check status, re-send to the ones that missed it,
 *  up to 3 rounds. With $broadcast the first send goes to HD_ALL (one mesh packet for the whole place).
 *  Returns the addresses still not satisfying $ok. Slow on purpose: the mesh drops ~1 in 6 commands and
 *  the bridge goes silent for a while after a burst, so never flood it. */
function hd_ensure(HaoDeng $cloud, int $place, array $addrs, callable $payload, callable $ok, bool $broadcast = false, ?callable $mine = null, ?callable $onRound = null): array {
    $twice = fn(array $to) => array_merge(...array_map(fn($a) => [$payload($a), $payload($a)], $to)); // far lights lose 1 in 3
    $cloud->sendMany($place, $broadcast ? [$payload(HD_ALL), $payload(HD_ALL)] : $twice($addrs), $broadcast ? 0.7 : 0.3);
    $bad = $addrs;
    for ($try = 0; $try < 6; $try++) {
        if ($onRound) $onRound($try, 6);
        sleep(2);
        if ($mine) $addrs = array_values(array_filter($addrs, $mine)); // a newer command took over some lights: leave those alone
        if (!$addrs) return [];
        try { $st = $cloud->status($place); } catch (Throwable $e) { return $bad; }
        if (!$st) continue; // bridge not answering yet: wait, do not pile on more commands
        $bad = array_values(array_filter($addrs, fn($a) => !isset($st[$a]) || !$ok($st[$a])));
        if (!$bad) return [];
        $resend = array_values(array_filter($bad, fn($a) => !isset($st[$a]) || $st[$a]['link'])); // out-of-reach lights: more sends are wasted
        if (!$resend) return $bad;
        $cloud->sendMany($place, $twice($resend), 0.3);
    }
    return $bad;
}

/** The UI commands: name -> [payload builder for one address, check that a status satisfies it]. Null if unknown. */
function hd_command(string $cmd, int $ct, array $in): ?array {
    $b = (int)($in['bright'] ?? 100); $k = (int)($in['kelvin'] ?? 3000); $color = ltrim($in['color'] ?? 'ffffff', '#');
    return match ($cmd) {
        'on' => [fn($a) => hd_payload_on($a, $ct), fn($x) => $x['on']],
        'off' => [fn($a) => hd_payload_off($a, $ct), fn($x) => !$x['on']],
        'rgb' => [fn($a) => hd_payload_rgb($a, $ct, $color), fn($x) => $x['on'] && $x['mode'] === 'color'],
        'white' => [fn($a) => hd_payload_white($a, $ct, $k, $b), fn($x) => $x['on'] && $x['mode'] === 'white' && abs($x['bright'] - $b) <= 2],
        'bright' => [fn($a) => hd_payload_brightness($a, $ct, $b), fn($x) => $x['on']],
        default => null,
    };
}

/** Every light in a place back to the everyday look (config 'default', else warm white 40%). Takes 5-30 s. */
function hd_reset_place(HaoDeng $cloud, int $place, array $config, ?callable $onRound = null): array {
    $d = ($config['default'] ?? []) + ['kelvin' => 2500, 'bright' => 40];
    $addrs = array_map(fn($dev) => (int)$dev['meshAddress'], $cloud->places[$place]['devices']);
    return hd_ensure($cloud, $place, $addrs, fn(int $a) => hd_payload_white($a, 5, (int)$d['kelvin'], (int)$d['bright']),
        fn(array $x) => $x['on'] && $x['mode'] === 'white' && abs($x['bright'] - (int)$d['bright']) <= 2, true, null, $onRound);
}
