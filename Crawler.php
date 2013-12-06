<?php

class Crawler {
  public $ytData;
  public $headers;
  public $httpcode;
  public $crawlTime;
  public $chId;
  public $ytUrl;
  public $ytType;
  public $ytId;

  public function __construct($chId, $url) {
    $this->crawlTime = time();
    $this->chId = $chId;
    $this->ytUrl = $url;
    $this->parse_yt_url();
  }

  public function get_yt_type() {
    return $this->ytType;
  }

  public function get_yt_id() {
    return $this->ytId;
  }

  public function get_yt_data() {
    if ($this->ytType  == 'channel') {
      return $this->get_yt_channel($this->ytId);
    } elseif ($this->ytType  == 'playlist') {
      return $this->get_yt_playlist($this->ytId);
    } else {
      return null;
    }
  }

  public function get_yt_channel($username=null) {
    if ($username == null) {
      $username = $this->ytId;
    }

    $ytAPI = 'http://gdata.youtube.com/feeds/api/users/' . $username . '/uploads?v=2&alt=jsonc&prettyprint=true';
    $this->ytData = file_get_contents($ytAPI);
    #$ytData = file_get_contents('testdata.json');
    $this->headers = $http_response_header;
    $this->httpcode = $this->header_code($this->headers);
    return $this->ytData;
  }

  public function get_yt_playlist($playlistId=null) {
    if ($playlistId == null) {
      $playlistId = $this->ytId;
    }

    $ytAPI = 'http://gdata.youtube.com/feeds/api/playlists/'. $playlistId . '?v=2&alt=jsonc&prettyprint=true';
    $this->ytData = file_get_contents($ytAPI);
    #$ytData = file_get_contents('testdata.json');
    $this->headers = $http_response_header;
    $this->httpcode = $this->header_code($this->headers);
    return $this->ytData;
  }

  public function parse_yt_url($url=null) {
    if ($url == null) {
      $url = $this->ytUrl;
    }

    #http://www.youtube.com/user/angularjs
    #http://www.youtube.com/view_play_list?p=91bbccf65ce3d190
    $pattern = '@.+www.youtube.com(/user/|/view_play_list\?p=)(.+)$@';
    if (preg_match($pattern, $url, $matches)) {
      #print_r($matches);
      $this->ytId = $matches[2];
      if ($matches[1] == '/user/') {
        $this->ytType = 'channel';
      } else {
        $this->ytType = 'playlist';
      }
    } else {
      $this->ytType = 'unknown';
      $this->ytId = '';
    }

    return array(
      'type' => $this->ytType,
      'id' => $this->ytId,
    );
  }

  public function parse_items($items, $chId=null, $type=null) {
    if ($chId == null) {
      $chId = $this->chId;
    }

    if ($type == null) {
      $type = $this->ytType;
    }

    $lines = array();

    foreach ($items as $item) {
      # playlist has one more level 'video'
      if ($type == 'playlist') {
        $i = $item->video;
      } else {
        $i = $item;
      }

      $item = array(
        'chId' => $chId,
        'uploader' => $i->uploader,
        'crawlTime' => $this->crawlTime,
        'id' => $i->id,
        'title' => $i->title,
        'uploaded' => strtotime($i->uploaded),
        'duration' => $i->duration,
        'thumbnail' => $i->thumbnail->sqDefault,
        'description' => $i->description,
      );

      $line = implode("\t", $item);
      $lines[] = $line;
    }

    return $lines;
  }

  public function header_code($headers) {
    $line0 = $headers[0];
    $split = explode(' ', $line0);
    #print_r($split);
    return $split[1];
  }

}

