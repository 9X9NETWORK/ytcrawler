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

  public function get_yt_meta() {
    $meta = Array('title'=>'', 'description'=>'', 'thumbnail'=>'', 'updateDate'=>'');
    if ($this->ytType == 'channel') {
        $ytAPI = 'http://gdata.youtube.com/feeds/api/users/' . $this->ytId . '?v=2&alt=json&prettyprint=true';
        $data = json_decode(file_get_contents($ytAPI), true);
        $meta['title'] = str_replace("\t", '  ', str_replace("\n", '   ', $data['entry']['title']['$t']));
        $meta['thumbnail'] = $data['entry']['media$thumbnail']['url'];
        $meta['description'] = str_replace("\t", '  ', str_replace("\n", '   ', $data['entry']['summary']['$t']));
    } else if ($this->ytType == 'playlist') {
        $ytAPI = 'http://gdata.youtube.com/feeds/api/playlists/' . $this->ytId . '?v=2&alt=json&prettyprint=true&max-results=1';
        $data = json_decode(file_get_contents($ytAPI), true);
        $meta['title'] = str_replace("\t", '  ', str_replace("\n", '   ', $data['feed']['title']['$t']));
        $thumbnailCount = count($data['feed']['media$group']['media$thumbnail']);
        if ($thumbnailCount > 0) {
            $meta['thumbnail'] = $data['feed']['media$group']['media$thumbnail'][$thumbnailCount-1]['url'];
        }
        $meta['description'] = str_replace("\t", '  ', str_replace("\n", '   ', $data['feed']['subtitle']['$t']));
        $meta['updateDate'] = strtotime($data['feed']['updated']['$t']);
    }
    echo $ytAPI . "\n";
    return $meta;
  }

  public function get_yt_data() {
    if ($this->ytType  == 'channel') {
      return $this->get_yt_channel_all($this->ytId);
    } elseif ($this->ytType  == 'playlist') {
      return $this->get_yt_playlist_all($this->ytId);
    } else {
      return null;
    }
  }

  public function get_yt_channel($username=null, $start_index=1) {
    if ($username == null) {
      $username = $this->ytId;
    }

    $ytAPI = 'http://gdata.youtube.com/feeds/api/users/' . $username . '/uploads?v=2&alt=jsonc&max-results=50&prettyprint=true&start-index=' . $start_index;
    $this->ytData = file_get_contents($ytAPI);
    $this->headers = $http_response_header;
    $this->httpcode = $this->header_code($this->headers);
    return $this->ytData;
  }

  public function get_yt_channel_all($username=null) {
    if ($username == null) {
      $username = $this->ytId;
    }

    $lines = array();
    $start_index = 1;

    do {
      $ytData = $this->get_yt_channel($username, $start_index);
      if ($this->httpcode != '200') {
        print_r('FAILED - httpcode: ' . $this->httpcode . ' data: ' . print_r($ytData,true));
        return $lines;
      }

      $d = json_decode($ytData);

      if (!isset($d->data->items)) {
        print_r("FAILED - No Video entry\n");
        return $lines;
      }

      $totalItems = $d->data->totalItems;

      $lines = array_merge($lines, $this->parse_items($d->data->items));

      $start_index = $start_index + 50;
      # limit 200 videos per channel
    } while ($start_index < 201 and $totalItems >= $start_index);

    return $lines;
  }

  public function get_yt_playlist($playlistId=null, $start_index=1) {
    if ($playlistId == null) {
      $playlistId = $this->ytId;
    }

    $ytAPI = 'http://gdata.youtube.com/feeds/api/playlists/'. $playlistId . '?v=2&alt=jsonc&max-results=50&prettyprint=true&start-index=' . $start_index;
    $this->ytData = file_get_contents($ytAPI);
    $this->headers = $http_response_header;
    $this->httpcode = $this->header_code($this->headers);
    return $this->ytData;
  }

  public function get_yt_playlist_all($playlistId=null) {
    if ($playlistId == null) {
      $playlistId = $this->ytId;
    }

    $lines = array();
    $start_index = 1;

    do {
      $ytData = $this->get_yt_playlist($playlistId, $start_index);
      if ($this->httpcode != '200') {
        print_r('FAILED - httpcode: ' . $this->httpcode . ' data: ' . print_r($ytData,true));
        return $lines;
      }

      $d = json_decode($ytData);

      if (!isset($d->data->items)) {
        print_r("FAILED - No Video entry\n");
        return $lines;
      }

      $totalItems = $d->data->totalItems;

      $lines = array_merge($lines, $this->parse_items($d->data->items));
      
      $start_index = $start_index + 50;
    } while ($totalItems >= $start_index);

    return $lines;

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

/********* Preserving the unplayable video, let them show up in CMS

      # filter out unplayable video
      # https://kb.teltel.com/kb/index.php/Filter_Invalid_Videos_in_YouTube_Channel_and_Playlist
      if (isset($i->accessControl) and 
            ($i->accessControl->embed == 'denied' or $i->accessControl->syndicate == 'denied') or
            (isset($i->status) and isset($i->value) and !(isset($i->status->reason) and $i->status->reason == 'limitedSyndication'))
         ) {
        # video is unplayable
        echo 'WARNING - Unplayable Video: ' . $i->id . ' in ytId: ' . $this->ytId . "\n";
        continue;
      } else if (isset($i->status) and isset($i->status->value) and 

      		(!isset($i->status->reason) or $i->status->reason != 'limitedSyndication')

         ) {
      	# video is unplayable
      	# test url http://gdata.youtube.com/feeds/api/playlists/PL1439A6A6F1D266D3?v=2&alt=jsonc&max-results=50&prettyprint=true&start-index=1
      	echo 'WARNING - Unplayable Video: ' . $i->id . ' in ytId: ' . $this->ytId . "\n";
      	continue;
      }

*********/

      $data = array(
        'chId' => $chId,
        'uploader' => $i->uploader,
        'crawlTime' => $this->crawlTime,
        'id' => $i->id,
        # remove LF and tab
        'title' => str_replace("\t", '  ', str_replace("\n", '   ', $i->title)),
        'uploaded' => strtotime($i->uploaded),
        'duration' => $i->duration,
        'thumbnail' => $i->thumbnail->sqDefault,
        'description' => str_replace("\t", '  ', str_replace("\n", '   ', $i->description)),
      );

      $line = implode("\t", $data);
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

