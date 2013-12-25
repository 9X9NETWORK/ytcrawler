# for android virtual channels

import sys
import urllib, urllib2
import os
from array import *
import MySQLdb
import time, datetime
import pycurl

apiserver = 'localhost:8080'
dbhost = 'localhost'
dbuser = 'root'
dbpass = ''

# get db info from config.php
fh = open('config.php', 'r')
config = fh.readlines()
fh.close()

for line in config:
  data = line.split('=', 1)
  if len(data) != 2:
    continue

  key = data[0].strip()
  value = data[1].strip().strip(';').strip("'")

  if key == '$dbhost':
    dbhost = value
  elif key == '$dbuser':
    dbuser = value
  elif key == '$dbpass':
    dbpass = value
  elif key == '$apiserver':
    apiserver = value

dbcontent = MySQLdb.connect (host = 'localhost',
                             user = 'root',
                             passwd = '',
                             charset = "utf8",
                             use_unicode = True,
                             db = "nncloudtv_content")

# get channel id
cId = sys.argv[1]

# read corresponding filei !!! file name might need to be changed
#url = 'http://channelwatch.9x9.tv/dan/ponderosa.feed.' + cId + 'txt'
#url = 'http://localhost:8080/images/test.txt'  #!!! testing file
#user_agent = 'Mozilla/4.0 (compatible; MSIE 5.5; Windows NT)'
#values = {'language' : 'Python' }
#headers = { 'User-Agent' : user_agent }
#data = urllib.urlencode(values)            
#req = urllib2.Request(url, data, headers)
#response = urllib2.urlopen(req)
fileName = '/var/tmp/ytcrawl/ponderosa.feed.' + cId + '.txt'
response = open(fileName, 'r')
feed = response.readlines()                  
response.close()

# read things to dic
textDic = {}
dbDic = {}
for line in feed:
  data = line.split('\t')
  videoid = data[3]
  fileUrl = "http://www.youtube.com/watch?v=" + videoid
  textDic[fileUrl] = fileUrl

cursor = dbcontent.cursor()
cursor.execute("""
   select id, episodeId, fileUrl from nnprogram where channelId = %s 
      """, (cId))
data = cursor.fetchall ()

# remove unwanted
print "-- compare existing --"
for d in data:
  # if not in text file, remove nnepisode related
  pId = d[0]
  eId = d[1]
  fileUrl = d[2]
  dbDic[fileUrl] = fileUrl
  obj = textDic.get(fileUrl, 'empty')
  if obj == 'empty':
     print "delete nnepisode and its programs:(eId)" + str(eId)
     cursor.execute("""delete from nnepisode where id = %s
        """, (eId)) 
     cursor.execute("""delete from nnprogram where episodeId  = %s
        """, (eId))
     cursor.execute("""delete from poi where pointId in (select id from poi_point where targetId = %s);
        """, (pId))
     cursor.execute("""delete from poi_point where targetId = %s
        """, (pId))
     
# parsing episode
print "-- parsing text --"
i = 1           
baseTimestamp = 0;                                
i = 1
cntEpisode = 0
eIds = []
for line in feed:
  data = line.split('\t')
  channelId = data[0] #supposedly the same as argument
  username = data[1]
  crawldate = data[2]
  videoid = data[3]
  name = data[4]        
  timestamp = data[5]
  duration = data[6]
  thumbnail = data[7]
  description = data[8]
  description = description[:253] + (description[253:] and '..')
  fileUrl = "http://www.youtube.com/watch?v=" + videoid
  # debug output
  print "cid:" + channelId
  print "username:" + username
  print "crawdate:" + crawldate
  print "name:" + name 
  print "timestamp:" + timestamp 
  print "duration:" + duration 
  print "thumbnail:" + thumbnail
  print "description:" + description
  print "fileUrl:" + fileUrl

  if channelId != cId:
     print "Fatal: channelId not matching"
     sys.exit(0) 

  if timestamp > baseTimestamp:
     baseTimestamp = timestamp 
  
  cursor = dbcontent.cursor() 
  cursor.execute("""
     select id, episodeId from nnprogram where channelId = %s and fileUrl = %s 
     """, (channelId, fileUrl))
  data = cursor.fetchone()
  if data is None:    
     # new entry from youtube, write to nnepisode
     print "new entry, video:" + fileUrl 
     cursor.execute("""
        insert into nnepisode (channelId, name, intro, imageUrl, duration, seq, publishDate, isPublic)
                       values (%s, %s, %s, %s, %s, %s, from_unixtime(%s), true)
        """, (cId, name, description, thumbnail, duration, i, timestamp))
     eId = cursor.lastrowid
     eIds.append(eId)
     print "eId" + str(eId)
     # write to nnprogram
     cursor.execute("""
        insert into nnprogram (channelId, episodeId, name, intro, imageUrl, duration, fileUrl, publishDate, contentType, isPublic, status)
                      values (%s, %s, %s, %s, %s, %s, %s, from_unixtime(%s), 1, true, 0)
        """, (cId, eId, name, description, thumbnail, duration, fileUrl, timestamp))
  else:
     # existing data, update the db
     eId = data[1]
     cursor.execute("""
        update nnepisode set seq = %s where id = %s
        """, (i, eId))
     print "duplicate, update seq"
  i = i + 1
  cntEpisode = cntEpisode + 1
   
# ch updateDate check
# for YouTube-channel follow newest video time, for YouTube-playlist follow playlist's update time
cursor.execute("""
   select unix_timestamp(updateDate) from nnchannel
    where id = %s
      """, (cId))
ch_row = cursor.fetchone()
ch_updateDate = ch_row[0]
print "-- check update time --"
print "original channel time: " + str(ch_updateDate) + "; time from youtube video:" + timestamp
if (ch_updateDate < long(timestamp)):
   print "ch updateDate is older, update with yt video"
   cursor.execute("""
        update nnchannel set updateDate = from_unixtime(%s) 
         where id = %s             
             """, (baseTimestamp, cId))

# ch readonly set back when done all sync job
# update ch cntEpisode
cursor.execute("""
        update nnchannel set readonly = false , cntEpisode = %s
         where id = %s             
             """, (cntEpisode, cId))

dbcontent.commit()  
cursor.close ()

print "-- record done --" + str(i)

print "-- call api --" + apiserver

url = "http://" + apiserver + "/wd/programCache?channel=" + str(cId)
urllib2.urlopen(url).read()

class GetPage:
    def __init__ (self, url):
        self.contents = ''
        self.url = url

    def read_page (self, buf):
        self.contents = self.contents + buf

    def show_page (self):
        print self.contents

autoshareCurl = pycurl.Curl()
for eId in eIds:
   resultPage = GetPage("http://" + nnApiDomain + "/api/episodes/" + str(eId) + "/scheduledAutosharing/facebook")
   autoshareCurl.setopt(autoshareCurl.URL, resultPage.url)
   autoshareCurl.setopt(autoshareCurl.WRITEFUNCTION, resultPage.read_page)
   autoshareCurl.perform()
   print "autosharing episode ID : " + str(eId)
   resultPage.show_page()
autoshareCurl.close()

