<?php
// Offline check of payload builders and MQTT packet bytes. Open /lights/test.php or run: php test.php
header('Content-Type: text/plain');
require __DIR__ . '/haodeng.php';

function check(bool $ok): void { static $n = 0; $n++; if (!$ok) exit("FAIL check #$n\n"); }

check(hd_payload_on(3, 5)['data'] === '0501FF000000000300');
check(hd_payload_off(3, 5)['data'] === '050100000000000300');
check(hd_payload_rgb(3, 5, 'ff0000')['data'] === '0560FF000000000200');
check(hd_payload_rgb(3, 5, 'ffffff')['data'] === '0560D2D2D200000200'); // clamped so r+g+b <= 630
check(hd_payload_white(3, 5, 5000, 100)['data'] === '05623D640000000200');
check(hd_payload_white(3, 5, 9999, 50)['data'] === '056264320000000200');
check(strlen(hd_checkcode('1700000000000')) === 32 && hd_checkcode('1') === hd_checkcode('1'));

// MQTT CONNECT for client "c", user "u", pass "p": 0x10, len 19, "MQTT", lvl 4, flags C2, keepalive 60, then strings
check(bin2hex(mqtt_connect_packet('c', 'u', 'p')) === '1013' . '00044d515454' . '04' . 'c2' . '003c' . '000163' . '000175' . '000170');
// PUBLISH qos1 to "t" payload "x" id 1: 0x32, len 6, topic, id, payload
check(bin2hex(mqtt_publish_packet('t', 'x', 1)) === '3206' . '000174' . '0001' . '78');
check(bin2hex(mqtt_packet(0x00, str_repeat('a', 200))) === '00c801' . bin2hex(str_repeat('a', 200))); // 2-byte remaining length

// status decode: byte0 nonzero = on, byte1 = brightness %, byte2 > 0x3F = white mode, byte3 = temp % (or hue)
check(hd_decode_status('71284000') == ['on' => true, 'link' => true, 'bright' => 40, 'mode' => 'white', 'kelvin' => 2500, 'raw' => '71284000']);
check(hd_decode_status('002d4000')['link'] === false && hd_decode_status('002d4000')['on'] === false); // byte 0 = 00: light out of the bridge's reach
check(hd_decode_status('71004000')['on'] === false); // 0% = off
check(hd_payload_group(1, 0x80F1, true)['data'] === '01F1800000000000');
check(hd_payload_group(1, 0x80F1, false)['data'] === '00F1800000000000');
check(hd_payload_rgb_dim(1, 5, 'ff0000', 50)['data'] === '056080000000000200');
check(hd_payload_brightness(1, 5, 250)['data'] === '050264060000000300');
check(hd_decode_status('01643F80') == ['on' => true, 'link' => true, 'bright' => 100, 'mode' => 'color', 'hue' => 180, 'sat' => 100, 'raw' => '01643F80']);

check(hd_hex_hs('ff0000') == [0, 100] && hd_hex_hs('00c000') == [120, 100] && hd_hex_hs('0040ff') == [225, 100] && hd_hex_hs('d2d2d2') == [0, 0]);

// effects: breathe curve, color fade round the wheel
$lv = hd_breathe_levels(10);
check(count($lv) === 10 && $lv[0] === 100 && $lv[5] === 5 && $lv[1] === $lv[9] && min($lv) === 5);
check(hd_mix_hex('ff0000', '00ff00', 0) === 'ff0000' && hd_mix_hex('ff0000', '00ff00', 1) === '00ff00');
check(hd_mix_hex('ff0000', '00ff00', 0.5) === 'ffff00'); // via yellow, not brown
check(hd_mix_hex('ffffff', '000000', 0.5) === '808080'); // gray end: plain RGB mix
check(hd_mix_hex('ff0000', '0000ff', 0.5) === 'ff00ff'); // shortest way: through magenta

// pattern timing (index.php fxTiming mirrors it) and the twinkle hash (same bits as index.php mix32)
check(hd_fx_timing('rotate', 0.5, 2, 1) == [0.5, 1] && hd_fx_timing('rotate', 0.5, 4, 2) == [4 * 2 * HD_GAP + 0.1, 1]);
check(hd_fx_timing('fade', 3.65, 2, 1)[1] === 6 && hd_fx_timing('fade', 0.5, 4, 2) == [4 * 2 * HD_GAP + 0.15, 1]);
check(hd_fx_timing('breathe', 3.65, 1, 1) == [2.5 * 3.65 / 24, 24]);
check(array_map('hd_mix32', [0, 1, -1, 12345, 99999]) === [493009611, 1059954845, 4288662295, 472827831, 3815738572]);
for ($c = 1; $c < 200; $c++) check(hd_twinkle_pick($c, 5) >= 0 && hd_twinkle_pick($c, 5) < 5);

// packet splitter: whole packets only, the rest waits in the buffer
$buf = mqtt_publish_packet('t', 'x', 1) . "\xD0\x00" . "\x30\x05";
check(mqtt_take_packet($buf) === [0x32, "\x00\x01t\x00\x01x"] && mqtt_take_packet($buf) === [0xD0, ''] && mqtt_take_packet($buf) === null && $buf === "\x30\x05");
$buf = mqtt_packet(0x30, str_repeat('a', 200)); $one = mqtt_take_packet($buf);
check($one[0] === 0x30 && strlen($one[1]) === 200 && $buf === '');

echo "ok\n";
