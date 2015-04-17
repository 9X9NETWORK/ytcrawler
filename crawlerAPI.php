<?php

$postdata = file_get_contents('php://input');

$decoded = json_decode($postdata);

if (!isset($decoded->id)) {
  die("FAILED - Invalid Input Data!!!\n");
}

$outJSON = '/mnt/tmp/ytcrawl/ytcrawl_req.' . $decoded->id . '.json';

file_put_contents($outJSON, $postdata);

# run ytcrawler in background
echo "Ack\n";
$command = '/usr/bin/php ytcrawler.php '. $decoded->id . ' >> /mnt/tmp/ytcrawl/ytcrawlerV3.log 2>&1 &';
$ret = shell_exec($command);
#header('Connection: Close');
#die('OK');

