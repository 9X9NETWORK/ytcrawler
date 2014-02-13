# for android virtual channels

import sys
import urllib, urllib2
import os
from array import *
import MySQLdb
import time, datetime
import json

apiserver = 'localhost:8080'
dbhost = 'localhost'
dbuser = 'root'
dbpass = ''

# get db info from config.php
fh = open(os.path.dirname(__file__) + '/config.php', 'r')
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

dbcontent = MySQLdb.connect (host = dbhost,
                             user = dbuser,
                             passwd = dbpass,
                             charset = "utf8",
                             use_unicode = True,
                             db = "nncloudtv_content")

# get channel id
cId = sys.argv[1]

fileName = '/var/tmp/ytcrawl/ponderosa.feed.' + cId + '.txt'
response = open(fileName, 'r')
feed = response.readlines()                  
response.close()

fileName = '/var/tmp/ytcrawl/ponderosa.meta.' + cId + '.json'
response = open(fileName, 'r')
meta = json.load(response)
response.close()

cursor = dbcontent.cursor()

chTitle = meta['title']
chDescription = meta['description']
chThumbnail = meta['thumbnail']
chUpdateDate = meta['updateDate']
if 'error' in meta:
    chError = meta['error']
else:
    chError = None

if (chError is not None):
    cursor.execute("""
                   update nnchannel_pref set value = 'failed'
                   where channelId = %s and item = 'auto-sync'
                   """, (cId))
    cursor.execute("""
                   update nnchannel set readonly = false where id = %s
                   """, (cId))
    dbcontent.commit()  
    cursor.close ()
    print "Warning: invalid playlist! (" + str(cId) + ")"
    sys.exit(0) 

# ch updateDate check
# for YouTube-channel follow newest video time, for YouTube-playlist follow playlist's update time
cursor.execute("""
   select unix_timestamp(updateDate), imageUrl from nnchannel
    where id = %s
      """, (cId))
chOriginThumbnail = ""
ch_row = cursor.fetchone()
if ch_row is not None:
    ch_updateDate = ch_row[0]
    chOriginThumbnail = ch_row[1]
    #hold the latest episode thumbnails, format is "ch thumbnail|ep1 thumbnail|ep2 thumbnail|ep3 thumbnail"
    if chOriginThumbnail is not None:
      start = chOriginThumbnail.find("|");
      if start > 0:
         chOriginThumbnail = chOriginThumbnail[start:]
    else:
       chOriginThumbnail = ""
    print "-- check update time --"
    print "original channel time: " + str(ch_updateDate) + "; time from youtube video:" + str(baseTimestamp)
    if (chUpdateDate != ''): # YouTube-playlist follow playlist's update time
       baseTimestamp = chUpdateDate
    if (baseTimestamp != 0):
       cursor.execute("""
            update nnchannel set updateDate = from_unixtime(%s) 
             where id = %s             
                 """, (baseTimestamp, cId))
else:
    print "Fatal: invalid channelId"
    sys.exit(0) 

# read things to dic
textDic = {}
dbDic = {}
for line in feed:
  data = line.split('\t')
  videoid = data[3]
  fileUrl = "http://www.youtube.com/watch?v=" + videoid
  textDic[fileUrl] = fileUrl

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
  print "--------------------------------"
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
  if timestamp == "0":
     # workaround
     print "timestamp is zero (maybe a private video)"
     timestamp = "1"
  
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
   
# ch readonly set back when done all sync job
# update ch cntEpisode

chTitle = chTitle.encode('utf8')
chTitle = chTitle[:498] + (chTitle[498:] and '..')
chDescription = chDescription.encode('utf8')
chDescription = chDescription[:498] + (chDescription[498:] and '..')
if chOriginThumbnail.find("|") < 0:
   chThumbnail = chThumbnail
else:
   chThumbnail = chThumbnail + chOriginThumbnail       
# transcodingUpdateDate stores the timestamp of synchronization time
cursor.execute("""
        update nnchannel set readonly = false , cntEpisode = %s ,
                             name = %s , intro = %s , imageUrl = %s,
                             transcodingUpdateDate = %s
         where id = %s             
             """, (cntEpisode, chTitle, chDescription, chThumbnail, int(time.time()), cId))

dbcontent.commit()  
cursor.close ()

print "-- record done --" + str(i)

print "-- call api --" + apiserver

url = "http://" + apiserver + "/wd/programCache?channel=" + str(cId)
urllib2.urlopen(url).read()

for eId in eIds:
   url = "http://" + apiserver + "/api/episodes/" + str(eId) + "/scheduledAutosharing/facebook"
   urllib2.urlopen(url).read()
   print "autosharing episode ID : " + str(eId)

