# for android virtual channels

import sys
import urllib, urllib2
import os
from array import *
import MySQLdb
import time, datetime

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

dbcontent = MySQLdb.connect (host = dbhost,
                             user = dbuser,
                             passwd = dbpass,
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

# parsing episode
i = 1           
baseTimestamp = 0;                                
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

  if channelId != cId:
     print "Fatal: channelId not matching"
     sys.exit(0) 

  if timestamp > baseTimestamp:
    baseTimestamp = timestamp 

  cursor = dbcontent.cursor() 
  # make sure no duplication, currently base on video id
  cursor.execute("""
     select id from nnprogram where channelId = %s and fileUrl = %s 
     """, (channelId, fileUrl))
  count = cursor.rowcount
  if count == 0:    
    # write to nnepisode 
    cursor.execute("""
       insert into nnepisode (channelId, name, intro, imageUrl, duration, seq, publishDate)
                      values (%s, %s, %s, %s, %s, %s, from_unixtime(%s))
       """, (cId, name, description, thumbnail, duration, i, timestamp))
    eId = cursor.lastrowid
    print "eId" + str(eId)
    # write to nnprogram
    cursor.execute("""
       insert into nnprogram (channelId, episodeId, name, intro, imageUrl, duration, fileUrl, publishDate, contentType, isPublic, status)
                     values (%s, %s, %s, %s, %s, %s, %s, from_unixtime(%s), 1, true, 0)
       """, (cId, eId, name, description, thumbnail, duration, fileUrl, timestamp))
    i = i + 1
  else:
     print "duplicate"

# ch updateDate check
cursor.execute("""
   select unix_timestamp(updateDate) from nnchannel
    where id = %s
      """, (cId))
ch_row = cursor.fetchone()
ch_updateDate = ch_row[0]
print "original channel time: " + str(ch_updateDate) + "; time from youtube video:" + timestamp
if (ch_updateDate < long(timestamp)):
   print "ch updateDate is older, update with yt video"
   cursor.execute("""
        update nnchannel set updateDate = from_unixtime(%s) 
         where id = %s             
             """, (baseTimestamp, cId))  

dbcontent.commit()  
cursor.close ()

print "record done:" + str(i)

