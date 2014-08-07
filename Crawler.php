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
  public $metaPrevious = '';
  public $metaTitle = '';
  public $metaThumbnail = '';
  public $metaDescription = '';
  public $metaUpdateDate = '';
  public $fbAccessToken = '110847978946712|i47zynWykCO0V1zjJz_BXc6EIXY';

  public $metaError = 'OK';

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
    // have to run get_yt_data first as some meta data from get_yt_data
    $meta = Array('title'=>'', 'description'=>'', 'thumbnail'=>'', 'updateDate'=>'');
    $meta['error'] = $this->metaError;

    if ($this->metaError != 'OK') {
      return $meta;
    }

    if ($this->ytType == 'channel') {
      
      # use the data from get_yt_data to avoid duplicated call to youtube
      $meta['updateDate'] = $this->metaUpdateDate;

      # check if there is update to channel
      if ($this->metaPrevious != '') {
        $oldMeta = json_decode($this->metaPrevious);
        if ($oldMeta->updateDate >= $this->metaUpdateDate) {
          # if no update, use previous meta
          $meta['title'] = $oldMeta->title;
          $meta['thumbnail'] = $oldMeta->thumbnail;
          $meta['description'] = $oldMeta->description;
          return $meta;
        }
      } 

      # call youtube to get new channel meta
      $ytAPI = 'http://gdata.youtube.com/feeds/api/users/' . $this->ytId . '?v=2&alt=json&prettyprint=true';
      echo $ytAPI . "\n";
      $ret = file_get_contents($ytAPI);
      $headers = $http_response_header;

      $httpcode = $this->header_code($headers);

      if ($httpcode != '200') {
        echo 'FAILED - get_yt_meta: httpcode: ' . $httpcode . ' data: ' . $ret . "\n";
        if ($httpcode == 404) {
          $meta['error'] = 'NotFound';
        } elseif ($httpcode == 403) {
          $meta['error'] = 'Forbidden';
        } else {
          $meta['error'] = 'Non2xx';
        }
        return $meta;
      }

      if ($ret === false) {
        #timeout or other failure
        echo "WARNING - get_yt_meta: file_get_contents timed out or other failure\n";
        $meta['error'] = 'Timeout';
        return $meta;
      }

      $data = json_decode($ret, true);
      if ($data == null || !isset($data['entry'])) {
          echo "FAILED - get_yt_meta: Invalid youtube channel!\n";
          $meta['error'] = 'Invalid';
      } else {
          $meta['title'] = str_replace("\t", '  ', str_replace("\n", '   ', $data['entry']['title']['$t']));
          $meta['thumbnail'] = $data['entry']['media$thumbnail']['url'];
          $meta['description'] = str_replace("\t", '  ', str_replace("\n", '   ', $data['entry']['summary']['$t']));
      }

    } else if ($this->ytType == 'playlist') {
      /*
      $ytAPI = 'http://gdata.youtube.com/feeds/api/playlists/' . $this->ytId . '?v=2&alt=json&prettyprint=true&max-results=1';
      $data = json_decode(file_get_contents($ytAPI), true);
      if ($data == null || !isset($data['feed'])) {
          echo "Invalid playlist!";
          $meta['error'] = true;
      } else {
          $meta['title'] = str_replace("\t", '  ', str_replace("\n", '   ', $data['feed']['title']['$t']));
          $meta['thumbnail'] = $this->get_yt_playlist_thumbnail($data['feed']['media$group']['media$thumbnail']);
          $meta['description'] = str_replace("\t", '  ', str_replace("\n", '   ', $data['feed']['subtitle']['$t']));
          $meta['updateDate'] = strtotime($data['feed']['updated']['$t']);
      }
      */

      # use the data from get_yt_data to avoid duplicated call to youtube
      $meta['title'] = $this->metaTitle;
      $meta['thumbnail'] = $this->metaThumbnail;
      $meta['description'] = $this->metaDescription;
      $meta['updateDate'] = $this->metaUpdateDate;

    } else if ($this->ytType == 'facebook') {

      # call facebook to get new channel meta
      $fbAPI = 'https://graph.facebook.com/' . $this->ytId;
      echo $fbAPI . "\n";
      $ret = file_get_contents($fbAPI);
      $headers = $http_response_header;
      $httpcode = $this->header_code($headers);

      if ($httpcode != '200') {
        echo 'FAILED - get_fb_meta: httpcode: ' . $httpcode . ' data: ' . $ret . "\n";
        if ($httpcode == 404) {
          $meta['error'] = 'NotFound';
        } elseif ($httpcode == 403) {
          $meta['error'] = 'Forbidden';
        } else {
          $meta['error'] = 'Non2xx';
        }
        return $meta;
      }

      if ($ret === false) {
        #timeout or other failure
        echo "WARNING - get_fb_meta: file_get_contents timed out or other failure\n";
        $meta['error'] = 'Timeout';
        return $meta;
      }

      $data = json_decode($ret, true);
      if ($data == null || !isset($data['id'])) {
          echo "FAILED - get_fb_meta: Invalid facebook feed!\n";
          $meta['error'] = 'Invalid';
      } else {
          $meta['title'] = str_replace("\t", '  ', str_replace("\n", '   ', $data['name']));
          $meta['thumbnail'] = "http://graph.facebook.com/${data['id']}/picture?type=large";
          $meta['description'] = str_replace("\t", '  ', str_replace("\n", '   ', $data['description']));
      }
    }
      #echo $ytAPI . "\n";
    return $meta;
  }

  public function get_yt_playlist_thumbnail($thumbnails, $prefer = 'default') {
  	
    $default = null;
    $mqdefault = null;
    $hqdefault = null;
    $sddefault = null;
    
    foreach ($thumbnails as $thumbnail) {
        if ($thumbnail['height'] == 90 && $thumbnail['width'] == 120)
            $default = $thumbnail['url'];
        if ($thumbnail['height'] == 180 && $thumbnail['width'] == 320)
            $mqdefault = $thumbnail['url'];
        if ($thumbnail['height'] == 360 && $thumbnail['width'] == 480)
            $hqdefault = $thumbnail['url'];
        if ($thumbnail['height'] == 480 && $thumbnail['width'] == 640)
            $sddefault = $thumbnail['url'];
    }
    

    if ($$prefer != null)
        return $$prefer;
    if ($default != null)
        return $default;
    else if ($mqdefault != null)
        return $mqdefault;
    else if ($hqdefault != null)
        return $hqdefault;
    else if ($sddefault != null)
        return $sddefault;
    else {
        return "";
    }
  }

  public function get_yt_data() {
    if ($this->ytType == 'channel') {
      return $this->get_yt_channel_all($this->ytId);
    } else if ($this->ytType == 'playlist') {
      return $this->get_yt_playlist_all($this->ytId);
    } else if ($this->ytType == 'facebook') {
      return $this->get_fb_feed_all($this->ytId);
    } else {
      return null;
    }
  }

  public function get_yt_channel($username=null, $start_index=1) {
    if ($username == null) {
      $username = $this->ytId;
    }

    $ytAPI = 'http://gdata.youtube.com/feeds/api/users/' . $username . '/uploads?v=2&alt=json&max-results=50&prettyprint=true&start-index=' . $start_index;
    $this->ytData = file_get_contents($ytAPI);
    $this->headers = $http_response_header;
    $this->httpcode = $this->header_code($this->headers);
    return $this->ytData;
  }

  public function get_fb_feed($fbId) {
    
    $this->ytData = file_get_contents("https://graph.facebook.com/v2.0/$fbId/feed?access_token=$this->fbAccessToken");
    $this->headers = $http_response_header;
    $this->httpcode = $this->header_code($this->headers);
    return $this->ytData;
  }

  public function get_fb_feed_all($fbId) {

    $lines = array();
    
    $fbData = $this->get_fb_feed($fbId);

    if ($this->httpcode != '200') {
      echo 'FAILED - httpcode: ' . $this->httpcode . ' data: ' . $fbData . "\n";
      if ($lines == array()) {
        echo "FAILED - get_fb_feed_all: non 200 returned\n";
        if ($this->httpcode == 404) {
          $this->metaError = 'NotFound';
        } elseif ($this->httpcode == 403) {
          $this->metaError = 'Forbidden';
        } else {
          $this->metaError = 'Non2xx';
        }
      }
      return $lines;
    }

    if ($fbData === false) {
      # timed out or other failure
      echo "FAILED - get_fb_feed: file_get_contents timed out or other failure\n";
      if ($lines == array()) {
        $this->metaError = 'Timeout';
        echo "FAILED - get_fb_feed_all: file_get_contents timed out or other failure\n";
      }
      return $lines;
    }

    $d = json_decode($fbData, true);

    if (empty($d['data'])) {
      if ($lines == array()) {
        $this->metaError = 'Empty';
        echo "WARNING - get_fb_feed_all: No Video entry\n";
      }
      return $lines;
    }

    $lines = $this->parse_fb_items($d['data']);

    if ($lines == array()) {
      $this->metaError = 'Empty';
      echo "WARNING: Empty feed\n";
    }

    return $lines;
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
        echo 'FAILED - httpcode: ' . $this->httpcode . ' data: ' . $ytData . "\n";
        if ($lines == array()) {
          echo "FAILED - get_yt_channel_all: non 200 returned\n";
          if ($this->httpcode == 404) {
            $this->metaError = 'NotFound';
          } elseif ($this->httpcode == 403) {
            $this->metaError = 'Forbidden';
          } else {
            $this->metaError = 'Non2xx';
          }
        }
        return $lines;
      }

      if ($ytData === false) {
        # timed out or other failure
        echo "FAILED - get_yt_channel: file_get_contents timed out or other failure\n";
        if ($lines == array()) {
          $this->metaError = 'Timeout';
          echo "FAILED - get_yt_channel_all: file_get_contents timed out or other failure\n";
        }
        return $lines;
      }

      $d = json_decode($ytData, true);

      if (!isset($d['feed']['entry'])) {
        if ($lines == array()) {
          $this->metaError = 'Empty';
          echo "WARNING - get_yt_channel_all: No Video entry\n";
        }
        return $lines;
      }

      # save the updateDate for meta
      if ($start_index == 1 ) {
        if (isset($d['feed']['entry'][0]['published']['$t'])) {
          $this->metaError = 'OK';
          $this->metaUpdateDate = strtotime($d['feed']['entry'][0]['published']['$t']);
          if ($this->metaPrevious != '') {
            # compare updateDate with pervious meta data
            $oldMeta = json_decode($this->metaPrevious);
            if ($this->metaUpdateDate != 0 and $oldMeta->updateDate >= $this->metaUpdateDate) {
              # No need to update the feed.  No further call to youtube
              $lines = array();
              echo "No update to channel\n";
              $this->metaError = 'NoUpdate';
              return $lines;
            }
          }
        }
      }

      $totalItems = $d['feed']['openSearch$totalResults']['$t'];

      $lines = array_merge($lines, $this->parse_items($d['feed']['entry']));

      $start_index = $start_index + 50;
      # limit 200 videos per channel
    } while ($start_index < 201 and $totalItems >= $start_index);

    if ($lines == array()) {
      $this->metaError = 'Empty';
      echo "WARNING: Empty channel\n";
    }
    return $lines;
  }

  public function get_yt_playlist($playlistId=null, $start_index=1) {
    if ($playlistId == null) {
      $playlistId = $this->ytId;
    }

    $ytAPI = 'http://gdata.youtube.com/feeds/api/playlists/'. $playlistId . '?v=2&alt=json&max-results=50&prettyprint=true&start-index=' . $start_index;
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
        echo 'FAILED - httpcode: ' . $this->httpcode . ' data: ' . $ytData . "\n";
        if ($lines == array()) {
          echo "FAILED - get_yt_playlist_all: non 200 returned\n";
          if ($this->httpcode == 404) {
            $this->metaError = 'NotFound';
          } elseif ($this->httpcode == 403) {
            $this->metaError = 'Forbidden';
          } else {
            $this->metaError = 'Non2xx';
          }
        }
        return $lines;
      }

      if ($ytData === false) {
        # timed out or other failure
        echo "FAILED - get_yt_playlist: file_get_contents timed out or other failure\n";
        if ($lines == array()) {
          $this->metaError = 'Timeout';
          echo "FAILED - get_yt_playlist_all: file_get_contents timed out or other failure\n";
        }
        return $lines;
      }

      $d = json_decode($ytData, true);

      if (!isset($d['feed']['entry'])) {
        echo "WARNING - No Video entry\n";
        if ($lines == array()) {
          $this->metaError = 'Empty';
          echo "WARNING - get_yt_playlist_all: empty playlist\n";
        }
        return $lines;
      }

      # save the updateDate for meta
      if ($start_index == 1) {
        $this->metaError = 'OK';
        $this->metaTitle = str_replace("\t", '  ', str_replace("\n", '   ', $d['feed']['title']['$t']));
        $this->metaThumbnail = $this->get_yt_playlist_thumbnail($d['feed']['media$group']['media$thumbnail'], 'mqdefault');
        $this->metaDescription = str_replace("\t", '  ', str_replace("\n", '   ', $d['feed']['subtitle']['$t']));
        $this->metaUpdateDate = strtotime($d['feed']['updated']['$t']);
        if ($this->metaPrevious != '') {
          # compare updateDate with pervious meta data
          $oldMeta = json_decode($this->metaPrevious);
          if ($this->metaUpdateDate != 0 and $oldMeta->updateDate >= $this->metaUpdateDate) {
            # No need to update the feed.  No further call to youtube
            $lines = array();
            $this->metaError = 'NoUpdate';
            echo "No update to playlist\n";
            return $lines;
          }
        }
      }

      $totalItems = $d['feed']['openSearch$totalResults']['$t'];

      $lines = array_merge($lines, $this->parse_items($d['feed']['entry']));
      
      $start_index = $start_index + 50;
    } while ($start_index < 201 and $totalItems >= $start_index);

    if ($lines == array()) {
      $this->metaError = 'Empty';
      echo "WARNING - empty playlist\n";
    }
    return $lines;

  }

  public function parse_yt_url($url=null) {
    if ($url == null) {
      $url = $this->ytUrl;
    }

    #http://www.youtube.com/user/angularjs
    #http://www.youtube.com/view_play_list?p=91bbccf65ce3d190
    $pattern = '@.+www.youtube.com(/user/|/view_play_list\?p=)(.+)$@';
    $fbPattern = '@^https?:\/\/graph\.facebook\.com\/(-[[:alnum:]._]+)@';
    if (preg_match($pattern, $url, $matches)) {
      #print_r($matches);
      $this->ytId = $matches[2];
      if ($matches[1] == '/user/') {
        $this->ytType = 'channel';
      } else {
        $this->ytType = 'playlist';
      }
    } else if (preg_match($fbPattern, $url, $matches)) {
      $this->ytType = 'facebook';
      $this->ytId = $matches[1];
    } else {
      $this->ytType = 'unknown';
      $this->ytId = '';
    }

    return array(
      'type' => $this->ytType,
      'id' => $this->ytId,
    );
  }

  public function parse_fb_items($items) {

    $lines = array();
    $regex = '/^https?:\/\/(www\.youtube\.com\/watch\?v=|youtu\.be\/)([-A-Za-z_|0-9]+)/';

    foreach ($items as $i) {

      if ($i['type'] == 'video' && preg_match($regex, $i['link'], $matches)) {

        $data = array (
          'chId'      => $this->chId,
          'uploader'  => $i['from']['name'],
          'crawlTime' => $this->crawlTime,
          'id'        => $matches[2],
          # remove LF and tab
          'title'     => str_replace("\t", '  ', str_replace("\n", '   ', $i['name'])),
          'uploaded'  => strtotime($i['updated_time']),
          'duration'  => 0,
          # use mqDefault as thumbnail, but it is not listed in json, so construct it from sqDefault
          'thumbnail' => $i['picture'],
          'description' => str_replace("\t", '  ', str_replace("\n", '   ', $i['message'])),
          'state' => 'fine',
          'reason' => 'fine'
        );
      }

      $line = implode("\t", $data);
      $lines[] = $line;
    }

    return $lines;
  }

  public function parse_items($items, $chId=null, $type=null) {
    if ($chId == null) {
      $chId = $this->chId;
    }

    if ($type == null) {
      $type = $this->ytType;
    }

    $lines = array();

    foreach ($items as $i) {
      /*** changed to alt=json so there is only one level
      # playlist has one more level 'video'
      if ($type == 'playlist') {
        $i = $item->video;
      } else {
        $i = $item;
      }
      */

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
        'uploader' => $i['media$group']['media$credit'][0]['$t'],
        'crawlTime' => $this->crawlTime,
        'id' => $i['media$group']['yt$videoid']['$t'],
        # remove LF and tab
        'title' => str_replace("\t", '  ', str_replace("\n", '   ', $i['title']['$t'])),
        'uploaded' => strtotime($i['media$group']['yt$uploaded']['$t']),
        'duration' => (isset($i['media$group']['yt$duration']['seconds']) ? $i['media$group']['yt$duration']['seconds'] : 0),
        # use mqDefault as thumbnail, but it is not listed in json, so construct it from sqDefault
        #'thumbnail' => (isset($i->thumbnail->sqDefault) ? str_replace('/default.jpg', '/mqdefault.jpg', $i->thumbnail->sqDefault) : $i->thumbnail->hqDefault),
        'thumbnail' => $this->get_yt_playlist_thumbnail($i['media$group']['media$thumbnail'], 'mqdefault'),
        'description' => str_replace("\t", '  ', str_replace("\n", '   ', (isset($i['media$group']['media$description']['$t'])) ? $i['media$group']['media$description']['$t'] : '')),
        'state' => (isset($i['app$control']['yt$state']['name']) && $i['app$control']['yt$state']['name'] == 'restricted') ? 'restricted' : 'fine',
        'reason' => (isset($i['app$control']['yt$state']['reasonCode'])) ? $i['app$control']['yt$state']['reasonCode'] : 'fine'
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

