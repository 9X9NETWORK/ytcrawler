ytcrawler
=========

**ytcrawler** is part of 9x9 back-end components, crawling for YouTube data.  
The [design document](https://docs.google.com/document/d/1_NM3ZrVxk3f-6A_yeX53G9xDRknxu1fw3wLjIuHKoeY/edit?usp=sharing) shows how it works.

Prerequisite
------------

* Ubuntu 12.04

      sudo apt-get install python-pycurl

Test
----

````bash

curl -d '{"id":29903,"sourceUrl":"http://www.youtube.com/user/ettvtaiwan1001","contentType":3,"isRealtime":1}' http://channelwatch.9x9.tv/ytcrawler/crawlerAPI.php

curl -d '{"id":29913,"sourceUrl":"http://www.youtube.com/view_play_list?p=bYMfMn1wPGWvDqTaKHh8k64M6ZJRtG6A","contentType":4,"isRealtime":1}' http://channelwatch.9x9.tv/ytcrawler/crawlerAPI.php

````

Installation
------------

1.  prepare directories and ownership  
    (login as account "ubuntu")

        sudo mkdir /var/www/ytcrawler
        sudo mkdir /var/tmp/ytcrawl
        sudo chown ubuntu:ubuntu /var/www/ytcrawler
        sudo chown www-data:www-data /var/tmp/ytcrawl

2.  pull out source

        cd /var/www/
        git clone "git@bitbucket.org:9x9group/ytcrawler.git"

3.  update **config.php** with db access info

4.  api url  
    **http://{{yourwebhost}}/ytcrawler/crawlerAPI.php**

5.  test  
    similar to above test session

