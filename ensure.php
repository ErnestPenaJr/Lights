<?php
// Background delivery of one UI command (started by api.php 'cmd'): send, check the lights' status,
// re-send to the ones that missed it. Gives up on a light when a newer command has taken it over.
// Usage: php ensure.php <job id>   (job file in <store>/jobs/<id>.json)
require __DIR__ . '/haodeng.php';
require __DIR__ . '/store.php';

$file = store_dir() . '/jobs/' . preg_replace('/[^a-f0-9]/', '', $argv[1] ?? '') . '.json';
$job = json_decode(@file_get_contents($file), true) ?: exit("no job\n");
$cloud = new HaoDeng();
$cloud->broker = $job['broker'];
$cloud->places = [$job['place'] => $job['placeData']];
[$payload, $ok] = hd_command($job['cmd'], (int)$job['ct'], $job['in']);
$mine = fn(int $a) => @file_get_contents(store_dir() . "/jobs/lock-{$job['place']}-$a") === $job['id'];
hd_ensure($cloud, (int)$job['place'], $job['addrs'], $payload, $ok, (bool)$job['all'], $mine);
@unlink($file);
