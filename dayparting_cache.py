# it's to clean the dayparting channel. not very related to ytcrawler project. deployed in the same machine though.
import urllib, urllib2
import time

host = "api.flipr.tv"

url = "http://" + host + "/wd/daypartCache?mso=9x9&lang=en"
print "url:" + url
stream = urllib.urlopen(url)
stream.close()

for i in range(0, 24):
   url = "http://" + host + "/playerAPI/portal?type=whatson&v=40&time=" + str(i)
   print url
   stream = urllib.urlopen(url)
   stream.close()
   time.sleep(10)

url = "http://" + host + "/wd/channelCache?channel=32580"
print url
stream = urllib.urlopen(url)
stream.close()
