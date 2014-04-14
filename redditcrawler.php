<?php
# crawl Reddit front page

echo 'start crawling - ' . date("Y-m-d H:i:s\n") . ' (reddit ch 32585)';

$r = new Reddit();
$r-> run();

echo 'end crawling - ' . date("Y-m-d H:i:s\n");

$log = '/var/tmp/ytcrawl/ytwritter-' . date("Ymd") . '.log';
#run dbwriter.py in background
file_put_contents($log, date("Y-m-d H:i:s\n"), FILE_APPEND);
$command = '/usr/bin/python ' . __DIR__ . '/ytwritter.py 32585 >> ' . $log . ' 2>&1 &';
$ret = shell_exec($command);


class Reddit {
  # reddit channel ID = 32585
  public $channelId = '32585';
  public $outFile;
  public $metaFile;
  public $redditName;
  public $crawlTime;
  public $ytIds;
  public $ytVideos;

  public function __construct() {
    $this->channelId = '32585';
    $this->outFile = '/var/tmp/ytcrawl/whatson.feed.' . $this->channelId . '.txt';
    $this->metaFile = '/var/tmp/ytcrawl/whatson.meta.' . $this->channelId . '.json';
    $this->redditName = '';
    $this->crawlTime = time();
    $this->ytIds = array();
    $this->ytVideos = array();

  }


  public function run() {
  
    for ($i=1;$i<20;$i++) {
      $json = $this->get_reddit();
      file_put_contents('/tmp/reddit' . $i . '.json', $json);
      $this->get_yt_videos($json);
      $j = json_decode($json); if ($j->data->after == Null) {
        echo 'after is null at loop: ' . $i . "\n";
        break;
      }

    }

    if ($this->ytVideos == array()) {
      print("FAILED - No Youtube Video\n");
      return;
    }


    file_put_contents($this->outFile, implode("\n", array_reverse($this->ytVideos)));

    # update time in meta
    $json = file_get_contents($this->metaFile);
    $j = json_decode($json, true);
    $j['updateDate'] = $this->crawlTime;
    file_put_contents($this->metaFile, json_encode($j));

    #$log = '/var/tmp/ytcrawl/dbwritter-' . date("Ymd") . '.log';
    ##run dbwriter.py in background
    #file_put_contents($log, date("Y-m-d H:i:s\n"), FILE_APPEND);
    #$command = '/usr/bin/python ' . __DIR__ . '/dbwritter.py ' . $decoded->id . ' >> ' . $log . ' 2>&1 &';
    #$ret = shell_exec($command);
    #header('Connection: Close');
    #die('OK');
  }

  public function get_reddit() {
    if ($this->redditName == '') {
      $json = file_get_contents('http://www.reddit.com/.json?limit=100');
    } else {
      $json = file_get_contents('http://www.reddit.com/.json?limit=100&after=' . $this->redditName);
    }

    $j = json_decode($json);
    if ($j->data->after != Null) {
      $this->redditName = $j->data->after;
      echo 'after: ' . $this->redditName . "\n";
    }
    return $json;
  }

  public function get_yt_videos($json) {
    $pattern1 = '~https?://(?:www.)?youtube\.com/watch\?.*v=([a-zA-Z0-9_-]{11})~';
    $pattern2 = '~https?://youtu\.be/([a-zA-Z0-9_-]{11})~';
    $j = json_decode($json);
    
    
    foreach ($j->data->children as $c) {
      if (preg_match($pattern1, $c->data->url, $matches) || preg_match($pattern2, $c->data->url, $matches)) {
        print_r($matches);

        if (in_array($matches[1], $this->ytIds)) {
          continue;
        }

        $this->ytIds[] = $matches[1];

        $data = array(
          'chId' => $this->channelId,
          'uploader' => $c->data->author,
          'crawlTime' => $this->crawlTime,
          'id' => $matches[1],
          # remove LF and tab
          'title' => str_replace("\t", '  ', str_replace("\n", '   ', $c->data->title)),
          'uploaded' => substr($c->data->created_utc,0,10),
          'duration' =>  0,
          # use mqDefault as thumbnail, but it is not listed in json, so construct it from sqDefault
          #'thumbnail' => $c->data->thumbnail,
          'thumbnail' => 'http://i.ytimg.com/vi/'. $matches[1] . '/mqdefault.jpg',
          'description' => '',
        );

        print_r($data);
        $line = implode("\t", $data);
        $this->ytVideos[] = $line;

      }
    }
  }

}





