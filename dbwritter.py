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

#read meta and episode data
try:
   fileName = '/mnt/tmp/ytcrawl/ponderosa.meta.' + cId + '.json'
   response = open(fileName, 'r')
   meta = json.load(response)
   response.close()
   chUpdateDate = meta['updateDate']
   chError = meta['error']
except IOError, e:
   chError = "NoFile"
except KeyError, e:
   chError = "OK"

try:
   fileName = '/mnt/tmp/ytcrawl/ponderosa.feed.' + cId + '.txt'
   response = open(fileName, 'r')
   feed = response.readlines()
   response.close()
except IOError, e:
   chError == "NoFile"

if chError == True:
   chError = "Error"
if chError == None:
   chError = "OK"
print "Info: chError," + str(chError)

if chError == "NoUpdate":
   print "Info: no update, exit"
   exit()
if chError == "Timeout":
   print "Info: timeout, exit"
   exit()
if chError == "Non2xx":
   print "Info: non2xx, exit"
   exit()

cursor = dbcontent.cursor()
if (chError != "OK" and chError != "Empty"):
    cursor.execute("""
                   update nnchannel_pref set value = 'failed'
                   where channelId = %s and item = 'auto-sync'
                   """, (cId))
    cursor.execute("""
                   update nnchannel set readonly = false where id = %s
                   """, (cId))
    dbcontent.commit()  
    cursor.close()
    print "Warning: invalid playlist! (" + str(cId) + ")"
    sys.exit(0) 
else:
    # bring it back to live
    print "Info: back to live"
    cursor.execute("""
                   update nnchannel_pref set value = 'off'
                   where channelId = %s and item = 'auto-sync' and value = 'failed'
                   """, (cId))

# ch updateDate check
# for YouTube-channel follow newest video time, for YouTube-playlist follow playlist's update time
baseTimestamp = 0;                                
cursor.execute("""
   select unix_timestamp(updateDate) from nnchannel
    where id = %s
      """, (cId))
ch_row = cursor.fetchone()
if ch_row is not None:
    ch_updateDate = ch_row[0]
    print "Info: -- check update time --"
    if (chUpdateDate != ''): # YouTube-playlist follow playlist's update time
       baseTimestamp = chUpdateDate
    print "Info: original channel time: " + str(ch_updateDate) + "; time from youtube video: " + str(baseTimestamp)
    if (baseTimestamp != 0):
       cursor.execute("""
            update nnchannel set updateDate = from_unixtime(%s) 
             where id = %s             
                 """, (baseTimestamp, cId))
else:
    print "Fatal: invalid channelId"
    sys.exit(0) 

if chError == "Empty":
   cursor.execute("""delete from nnepisode where channelId = %s
        """, (cId))
   cursor.execute("""delete from nnprogram where channelId = %s
        """, (cId))
   print "Warning: empty, deleting all the nnepisodes and nnprograms and exit"
   dbcontent.commit()
   cursor.close()
   exit()

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
     print "unattach nnepisode from nnchannel: " + str(eId)
     cursor.execute("""update nnepisode set channelId = 0, adId = %s where id = %s
        """, (cId, eId)) 
     cursor.execute("""update nnprogram set channelId = 0 where episodeId = %s
        """, (eId)) 
     
# parsing episode
print "-- parsing text --"
i = 1 #seq
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
  description = description[:1498] + (description[1498:] and '..')
  state = data[9].strip()
  reason = data[10].strip()
  fileUrl = "http://www.youtube.com/watch?v=" + videoid
  # debug output
  print "-------------------"
  print "cid:" + channelId
  print "username:" + username
  print "crawdate:" + crawldate
  print "name:" + name 
  print "timestamp:" + timestamp 
  print "duration:" + duration 
  print "thumbnail:" + thumbnail
  print "description:" + description
  print "state:" + state
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
  if state == "restricted" and reason == "private":
     isPublic = '\x00';
  else:
     isPublic = '\x01';
     cntEpisode = cntEpisode + 1
  
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
                       values (%s, %s, %s, %s, %s, %s, from_unixtime(%s), %s)
        """, (cId, name, description, thumbnail, duration, i, timestamp, isPublic))
     eId = cursor.lastrowid
     eIds.append(eId)
     print "eId" + str(eId)
     # write to nnprogram
     cursor.execute("""
        insert into nnprogram (channelId, episodeId, name, intro, imageUrl, duration, fileUrl, publishDate, contentType, isPublic, status)
                      values (%s, %s, %s, %s, %s, %s, %s, from_unixtime(%s), 1, %s, 0)
        """, (cId, eId, name, description, thumbnail, duration, fileUrl, timestamp, isPublic))
  else:
     # existing data, update the db
     eId = data[1]
     cursor.execute("""
        update nnepisode set seq = %s , name = %s , intro = %s , imageUrl = %s , duration = %s ,
         publishDate = from_unixtime(%s) , isPublic = %s where id = %s
        """, (i, name, description, thumbnail, duration, timestamp, isPublic, eId))
     cursor.execute("""
        update nnprogram set name = %s , intro = %s , imageUrl = %s , duration = %s ,
         publishDate = from_unixtime(%s) , isPublic = %s where channelId = %s and episodeId = %s 
        """, (name, description, thumbnail, duration, timestamp, isPublic, cId, eId))
     print "duplicate, update seq and all meta"
  i = i + 1
   
# ch readonly set back when done all sync job
# update ch cntEpisode
# transcodingUpdateDate stores the timestamp of synchronization time
cursor.execute("""
        update nnchannel set readonly = false , cntEpisode = %s ,
                             transcodingUpdateDate = %s
         where id = %s             
             """, (cntEpisode, int(time.time()), cId))
dbcontent.commit()  
cursor.close ()

print "-- record done --"
print "cntEpisode = " + str(cntEpisode) + ", i = " + str(i)

print "-- call api --"
url = "http://" + apiserver + "/wd/programCache?channel=" + str(cId) + "&t=" + str(int(time.time()))
print url;
urllib2.urlopen(url).read()

for eId in eIds:
   url = "http://" + apiserver + "/api/episodes/" + str(eId) + "/scheduledAutosharing/facebook"
   urllib2.urlopen(url).read()
   time.sleep(0.5)

if len(eIds) is not 0:
    print "new published episodes: " + ", ".join(str(eId) for eId in eIds)

print "==== " + time.strftime("%r") + " ===="

