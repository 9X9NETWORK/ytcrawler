<?php
include 'Crawler.php';

echo 'start crawling - ' . date("Y-m-d H:i:s\n") . ' (ch' . $argv[1] . ')';

if (!isset($argv[1])) {
  die('FAILED - Require nnChannelId in input!!!');
}

$inJSON = '/var/tmp/ytcrawl/ytcrawl_req.' . $argv[1] . '.json';

if (!file_exists($inJSON)) {
  die('FAILED - Input json file does not exist!!!');
}

$postdata = file_get_contents($inJSON);
#$postdata = '{"id":29923,"sourceUrl":"http://www.youtube.com/view_play_list?p=gZx3ncZz696cfPMYpNBekWBV5BvK8t2h","contentType":4, "isRealtime":1}';

$decoded = json_decode($postdata);

if (!isset($decoded->id)) {
  die("FAILED - Invalid Input Data!!!\n");
}

$outFile = '/var/tmp/ytcrawl/ponderosa.feed.' . $decoded->id . '.txt';
$metaFile = '/var/tmp/ytcrawl/ponderosa.meta.' . $decoded->id . '.json';

$crl = new Crawler($decoded->id, $decoded->sourceUrl);
if ($crl->ytId == '') {
  die('FAILED - Wrong sourceUrl: '. $decoded->sourceUrl . "\n");
}

echo 'ytcrawl for ' . $crl->ytId . "\n";

$lines = $crl->get_yt_data();

if ($lines == array()) {
  print('FAILED - No Playable Video');
}

file_put_contents($outFile, implode("\n", $lines));

$meta = $crl->get_yt_meta();

file_put_contents($metaFile, json_encode($meta));

echo 'end crawling - ' . date("Y-m-d H:i:s\n");
$log = '/var/tmp/ytcrawl/dbwritter-' . date("Ymd") . '.log';
#run dbwriter.py in background
file_put_contents($log, date("Y-m-d H:i:s\n"), FILE_APPEND);
$command = '/usr/bin/python ' . __DIR__ . '/dbwritter.py ' . $decoded->id . ' >> ' . $log . ' 2>&1 &';
$ret = shell_exec($command);
#header('Connection: Close');
#die('OK');

