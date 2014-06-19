# for whats on twitter and reddit channels

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

#!!!!!!
fileName = '/mnt/tmp/ytcrawl/whatson.feed.' + cId + '.txt'
response = open(fileName, 'r')
feed = response.readlines()                  
response.close()

cursor = dbcontent.cursor()

# read db video id to dic
dbDic = {}
updateDic = {}
recycleId = []

cursor.execute("""
   select id, ytVideoId from ytprogram where channelId = %s
    """, (cId))
rows = cursor.fetchall()
for r in rows:
  ytId = r[0]
  videoId = r[1]
  dbDic[videoId] = ytId
  updateDic[ytId] = ytId 

for line in feed:
  data = line.split('\t')
  print "data size:" + str(len(data))
  videoId = data[3]
  try: 
     ytId = dbDic[videoId]
     updateDic.pop(ytId)
  except KeyError:
     print "not exist"

for key in updateDic:
   # can be used for id update
   print "add to recycle:" + str(key)
   recycleId.append(key)    

# parsing episode
print "-- parsing text --"
i = 1
recycleLen = len(recycleId)
chImageUrl = ""
for line in feed:
  data = line.split('\t')
  channelId = data[0] #supposedly the same as argument
  username = data[1]
  crawldate = data[2]
  videoId = data[3]
  name = data[4]        
  timestamp = data[5]
  duration = data[6]
  thumbnail = data[7]
  description = data[8]
  description = description[:253] + (description[253:] and '..')
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
  print "videoId:" + videoId

  if channelId != cId:
     print "Fatal: channelId not matching"
     sys.exit(0) 

  if i < 4:
     chImageUrl = chImageUrl + "|" + thumbnail
     print "i=" + str(i) + " ch thumbnail:" + chImageUrl

  if i == 4:
     chImageUrl = chImageUrl[1:]
     cursor.execute("""update nnchannel set imageUrl = %s where id=%s
             """, (chImageUrl, cId))
     print ("update ch thumbnail:" + chImageUrl)

  if timestamp == "0":
     # workaround
     print "timestamp is zero (maybe a private video)"
     timestamp = "1"
  if crawldate == "0":
     crawldate = "1"  
  try:
     print "existing video:" + str(dbDic[videoId]) 
  except KeyError:
    try: 
       if i > 200:
          break
       if i < recycleLen:
          print "use recycle id: " + str(recycleId[i]) 
          cursor.execute("""
             update ytprogram set name = %s, intro = %s, imageUrl = %s, duration = %s, ytVideoId = %s, updateDate = from_unixtime(%s), crawlDate = from_unixtime(%s)
              where id = %s
             """, (name, description, thumbnail, duration, videoId, timestamp, crawldate, recycleId[i])) 
       else:
          # new entry from youtube, write to nnepisode
          print "new entry, video:" + videoId 
          cursor.execute("""
             insert into ytprogram (channelId, name, intro, imageUrl, duration, ytVideoId, updateDate, crawlDate)
                            values (%s, %s, %s, %s, %s, %s, from_unixtime(%s), from_unixtime(%s))
             """, (cId, name, description, thumbnail, duration, videoId, timestamp, crawldate))
    except MySQLdb.IntegrityError as e:
      print "--->SQL Error: %s" % e
  i = i + 1
   
dbcontent.commit()  
cursor.close ()

print "-- record done --"

print "-- call api --"
url = "http://" + apiserver + "/wd/programCache?channel=" + str(cId)
print url;
urllib2.urlopen(url).read()


print "==== " + time.strftime("%r") + " ===="

