<?php
# crawl Twitter trending
echo 'start crawling - ' . date("Y-m-d H:i:s\n") . ' (twitter ch 32586)';

require_once('config_twitter.php');
require_once('twitter-api-php/TwitterAPIExchange.php');

$t = new Twitter();
$t->run();

echo 'end crawling - ' . date("Y-m-d H:i:s\n");


class Twitter {
  # twitter channel ID = 32586
  public $channelId = '32586';
  public $outFile;
  public $metaFile;
  public $redditName;
  public $crawlTime;
  public $ytIds;
  public $ytVideos;
  private $settings;

  public function __construct() {
    $this->channelId = '32586';
    $this->outFile = '/var/tmp/ytcrawl/whatson.feed.' . $this->channelId . '.txt';
    $this->metaFile = '/var/tmp/ytcrawl/whatson.meta.' . $this->channelId . '.json';
    $this->crawlTime = time();
    $this->ytIds = array();
    $this->ytVideos = array();
    $this->settings = array(
      'oauth_access_token' => ACCESS_TOKEN,
      'oauth_access_token_secret' => ACCESS_TOKEN_SECRET,
      'consumer_key' => CONSUMER_KEY,
      'consumer_secret' => CONSUMER_SECRET
    );
  }

  public function run() {
    $woeids = array(
      1, // worldwide
      23424977, // usa
      23424936, // Russia
      //24865675, // Europe
      //2347563, // California
      12587712, // Santa Clara
      2488042, // San Jose, CA
      2442047, // Los Angeles
      2487956, // San Francisco, CA
      2459115, // New York
      2514815, // Washington
      //2347591, // New York
    );

    foreach ($woeids as $w) {
      $trends = $this->get_trending($w);
      # php 5.4
      # file_put_contents('/var/tmp/trending.json', json_encode(json_decode($trends, true), JSON_PRETTY_PRINT));
      file_put_contents('/var/tmp/trending_' . $w . '.json', $trends);
      $hashtags = $this->get_hashtags($trends);
      foreach ($hashtags as $h) {
        $tweets = $this->get_tweets_by_hashtag($h);
        file_put_contents('/var/tmp/hashtag_' . $h . '.json', $tweets);
        $this->get_yt_videos($tweets);
      }

      if ($this->ytVideos == array()) {
        print("FAILED - No Youtube Video\n");
        return;
      }
    }

    file_put_contents($this->outFile, implode("\n", array_reverse($this->ytVideos)));

    # update time in meta
    $json = file_get_contents($this->metaFile);
    $j = json_decode($json, true);
    $j['updateDate'] = $this->crawlTime;
    file_put_contents($this->metaFile, json_encode($j));

  }

  public function get_trending($woeid) {
    $url = 'https://api.twitter.com/1.1/trends/place.json';
    //$getfield = '?per_page=100&id=' . $woeid;
    $getfield = '?count=100&id=' . $woeid;
    $requestMethod = 'GET';

    $twitter = new TwitterAPIExchange($this->settings);
    return $twitter->setGetfield($getfield)
                   ->buildOauth($url, $requestMethod)
                   ->performRequest();

  }

  public function get_hashtags($trends) {
    $this->hashtags = array();
    $j = json_decode($trends);

    if (isset($j->errors)) {
      print_r($j->errors);
      return $this->hashtags;
    }

    foreach ($j[0]->trends as $t) {
      $this->hashtags[] = $t->query;
    }
    
    return $this->hashtags;
  }

  public function get_tweets_by_hashtag($tag) {
    $url = 'https://api.twitter.com/1.1/search/tweets.json';
    //$getfield = '?result_type=recent&q=#MarchMadness';
    //$getfield = '?per_page=100&result_type=recent&q=' . $tag;
    $getfield = '?count=100&result_type=recent&q=' . $tag;
    $requestMethod = 'GET';

    $twitter = new TwitterAPIExchange($this->settings);
    return $twitter->setGetfield($getfield)
                   ->buildOauth($url, $requestMethod)
                   ->performRequest();
  }

  public function get_yt_videos($json) {
    $pattern1 = '~https?://(?:www.)?youtube\.com/watch\?.*v=([a-zA-Z0-9_-]{11})~';
    $pattern2 = '~https?://youtu\.be/([a-zA-Z0-9_-]{11})~';
    $j = json_decode($json);

    foreach ($j->statuses as $status) {

      if (isset($status->retweeted_status)) {
        $statuses = array($status, $status->retweeted_status);
        echo "with retweet\n";
      } else {
        $statuses = array($status);
        echo "without retweet\n";
      }

      foreach ($statuses as $s) {

        #print_r($s->user);
        #print_r($s->user->entities);
        if (isset($s->user->entities->url->urls[0]->expanded_url)) {
          $expanded_url = $s->user->entities->url->urls[0]->expanded_url;
        } else {
          #die("no url\n");
          continue;
        }

        if(preg_match($pattern1, $expanded_url, $matches) || preg_match($pattern2, $expanded_url, $matches)) {
          print_r($matches); 
          if (in_array($matches[1], $this->ytIds)) {
            continue;
          }

          $this->ytIds[] = $matches[1];

          $data = array(
            'chId' => $this->channelId,
            'uploader' => $s->user->screen_name,
            'crawlTime' => $this->crawlTime,
            'id' => $matches[1],
            # remove LF and tab
            'title' => str_replace("\t", '  ', str_replace("\n", '   ', $s->text)),
            'uploaded' => strtotime($s->created_at),
            'duration' =>  0,
            # use mqDefault as thumbnail, but it is not listed in json, so construct it from sqDefault
            #'thumbnail' => $c->data->thumbnail,
            'thumbnail' => 'http://i.ytimg.com/vi/'. $matches[1] . '/mqdefault.jpg',
            'description' => str_replace("\t", '  ', str_replace("\n", '   ', $s->user->description)),
          );

          print_r($data);
          $line = implode("\t", $data);
          $this->ytVideos[] = $line;
        }
      }
    }
  }

}

