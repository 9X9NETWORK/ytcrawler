<?php
include 'Crawler.php';

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

$crl = new Crawler($decoded->id, $decoded->sourceUrl);
if ($crl->ytId == '') {
  die('FAILED - Wrong sourceUrl: '. $decoded->sourceUrl . "\n");
}

$crl->get_yt_data();

if ($crl->httpcode != '200') {
  die('FAILED - httpcode: ' . $crl->httpcode . ' data: ' . print_r($crl->ytData,true));
}

$d = json_decode($crl->ytData);

if (!isset($d->data->items)) {
  die('FAILED - No Video entry');
}

$lines = $crl->parse_items($d->data->items);

if ($lines == array()) {
  die('FAILED - No Playable Video');
}

file_put_contents($outFile, implode("\n", $lines));

#run dbwriter.py in background
#echo "Ack\n";
$command = '/usr/bin/python dbwritter.py ' . $decoded->id . ' >> /var/tmp/ytcrawl/dbwritter.log 2>&1 &';
#$command = '/bin/sh test.sh '. $decoded->id . ' >> /var/tmp/ytcrawl/test.log 2>&1 &';
$ret = shell_exec($command);
#echo 'shell output:' . $ret;
#header('Connection: Close');
#die('OK');

