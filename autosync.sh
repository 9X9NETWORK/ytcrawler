#!/bin/bash
#
# autosync.sh
#
# to put this script in crontab to do youtube sync job periodically
#
#   ex. (sync 4 times a day)
#
#   0 1,6,12 * * * sudo su www-data -c "/var/www/ytcrawler/autosync.sh >> /mnt/tmp/ytcrawl/autosync.log"
#

ytcrawler_dir=$(dirname "$(readlink -f "$0")")
ytcrawler_tmp="/mnt/tmp/ytcrawl"
apiserver=$(php -r "include '${ytcrawler_dir}/config.php'; echo \$apiserver;")
dbhost=$(php -r "include '${ytcrawler_dir}/config.php'; echo \$dbhost;")
dbuser=$(php -r "include '${ytcrawler_dir}/config.php'; echo \$dbuser;")
dbpass=$(php -r "include '${ytcrawler_dir}/config.php'; echo \$dbpass;")

sql="SELECT id FROM nnchannel AS ch
              INNER JOIN ( SELECT channelId FROM nnchannel_pref
                                           WHERE item = 'auto-sync' AND
                                                value = 'on' ) pref
                    ON ch.id = pref.channelId
              WHERE status in (0,3) AND
                    contentType = 6 AND
                    userIdStr IS NOT NULL AND
                    sourceUrl IS NOT NULL;"

chlist=$(echo "$sql" | mysql -u "$dbuser" --password="$dbpass" -h "$dbhost" nncloudtv_content | tail -n +2)
chcnt=$(echo $chlist | wc -w)

echo "===================="
echo
echo "start to autosync at $(date)"
echo "totally $chcnt channels to sync"
echo

for ch in $chlist; do
    echo "--------------------"
    req_file="$ytcrawler_tmp/ytcrawl_req.${ch}.json"
    if test ! -f "$req_file"; then
        echo "create request file for ${ch}"
        sql_req="select sourceUrl from nnchannel where id=${ch};"
        sourceUrl=$(echo "$sql_req" | mysql -u "$dbuser" --password="$dbpass" -h "$dbhost" nncloudtv_content | tail -1)
        echo "{ \"id\":\"${ch}\", \"isRealtime\":\"false\", \"contentType\":\"6\", \"sourceUrl\":\"${sourceUrl}\" }" > "$req_file"
    fi
    /usr/bin/php "$ytcrawler_dir/ytcrawler.php" $ch
    sleep 1
done

echo "cleaning virtual channels ..."

curl -s "http://www.flipr.tv/wd/programCache?channel=35551"
curl -s "http://www.flipr.tv/wd/programCache?channel=35552"
curl -s "http://www.flipr.tv/wd/programCache?channel=35553"
curl -s "http://www.flipr.tv/wd/programCache?channel=35554"
curl -s "http://www.flipr.tv/wd/programCache?channel=35555"
curl -s "http://www.flipr.tv/wd/programCache?channel=35556"
curl -s "http://www.flipr.tv/wd/programCache?channel=35557"
curl -s "http://www.flipr.tv/wd/programCache?channel=35558"
curl -s "http://www.flipr.tv/wd/programCache?channel=35559"
curl -s "http://www.flipr.tv/wd/programCache?channel=35560"
curl -s "http://www.flipr.tv/wd/programCache?channel=35561"
curl -s "http://www.flipr.tv/wd/programCache?channel=35562"

curl -s "http://www.flipr.tv/playerAPI/channelLineup?v=40&channel=35551" && echo
curl -s "http://www.flipr.tv/playerAPI/channelLineup?v=40&channel=35552" && echo
curl -s "http://www.flipr.tv/playerAPI/channelLineup?v=40&channel=35553" && echo
curl -s "http://www.flipr.tv/playerAPI/channelLineup?v=40&channel=35554" && echo
curl -s "http://www.flipr.tv/playerAPI/channelLineup?v=40&channel=35555" && echo
curl -s "http://www.flipr.tv/playerAPI/channelLineup?v=40&channel=35556" && echo
curl -s "http://www.flipr.tv/playerAPI/channelLineup?v=40&channel=35557" && echo
curl -s "http://www.flipr.tv/playerAPI/channelLineup?v=40&channel=35558" && echo
curl -s "http://www.flipr.tv/playerAPI/channelLineup?v=40&channel=35559" && echo
curl -s "http://www.flipr.tv/playerAPI/channelLineup?v=40&channel=35560" && echo
curl -s "http://www.flipr.tv/playerAPI/channelLineup?v=40&channel=35561" && echo
curl -s "http://www.flipr.tv/playerAPI/channelLineup?v=40&channel=35562" && echo

curl -s "http://www.flipr.tv/wd/programCache?channel=35789"
curl -s "http://www.flipr.tv/wd/programCache?channel=35785"
curl -s "http://www.flipr.tv/wd/programCache?channel=35815"
curl -s "http://www.flipr.tv/wd/programCache?channel=35817"
curl -s "http://www.flipr.tv/wd/programCache?channel=35784"
curl -s "http://www.flipr.tv/wd/programCache?channel=35794"
curl -s "http://www.flipr.tv/wd/programCache?channel=35797"
curl -s "http://www.flipr.tv/wd/programCache?channel=35801"
curl -s "http://www.flipr.tv/wd/programCache?channel=35804"
curl -s "http://www.flipr.tv/wd/programCache?channel=35813"
curl -s "http://www.flipr.tv/wd/programCache?channel=35802"
curl -s "http://www.flipr.tv/wd/programCache?channel=35806"
curl -s "http://www.flipr.tv/wd/programCache?channel=35808"
curl -s "http://www.flipr.tv/wd/programCache?channel=35811"
curl -s "http://www.flipr.tv/wd/programCache?channel=35781"
curl -s "http://www.flipr.tv/wd/programCache?channel=35782"
curl -s "http://www.flipr.tv/wd/programCache?channel=35783"
curl -s "http://www.flipr.tv/wd/programCache?channel=35787"
curl -s "http://www.flipr.tv/wd/programCache?channel=35788"
curl -s "http://www.flipr.tv/wd/programCache?channel=35790"
curl -s "http://www.flipr.tv/wd/programCache?channel=35791"
curl -s "http://www.flipr.tv/wd/programCache?channel=35786"
curl -s "http://www.flipr.tv/wd/programCache?channel=35793"
curl -s "http://www.flipr.tv/wd/programCache?channel=35800"
curl -s "http://www.flipr.tv/wd/programCache?channel=35803"
curl -s "http://www.flipr.tv/wd/programCache?channel=35805"
curl -s "http://www.flipr.tv/wd/programCache?channel=35807"
curl -s "http://www.flipr.tv/wd/programCache?channel=35809"
curl -s "http://www.flipr.tv/wd/programCache?channel=35812"
curl -s "http://www.flipr.tv/wd/programCache?channel=35814"
curl -s "http://www.flipr.tv/wd/programCache?channel=35816"
curl -s "http://www.flipr.tv/wd/programCache?channel=35818"
curl -s "http://www.flipr.tv/wd/programCache?channel=35819"
curl -s "http://www.flipr.tv/wd/programCache?channel=35820"
curl -s "http://www.flipr.tv/wd/programCache?channel=35821"
curl -s "http://www.flipr.tv/wd/programCache?channel=35822"
curl -s "http://www.flipr.tv/wd/programCache?channel=35792"
curl -s "http://www.flipr.tv/wd/programCache?channel=35795"
curl -s "http://www.flipr.tv/wd/programCache?channel=35799"
curl -s "http://www.flipr.tv/wd/programCache?channel=35810"
curl -s "http://www.flipr.tv/wd/programCache?channel=25796"
curl -s "http://www.flipr.tv/wd/programCache?channel=35798"
curl -s "http://www.flipr.tv/wd/programCache?channel=35823"

curl -s "http://www.flipr.tv/playerAPI/channelLineup?v=40&channel=35789" && echo
curl -s "http://www.flipr.tv/playerAPI/channelLineup?v=40&channel=35785" && echo
curl -s "http://www.flipr.tv/playerAPI/channelLineup?v=40&channel=35815" && echo
curl -s "http://www.flipr.tv/playerAPI/channelLineup?v=40&channel=35817" && echo
curl -s "http://www.flipr.tv/playerAPI/channelLineup?v=40&channel=35784" && echo
curl -s "http://www.flipr.tv/playerAPI/channelLineup?v=40&channel=35794" && echo
curl -s "http://www.flipr.tv/playerAPI/channelLineup?v=40&channel=35797" && echo
curl -s "http://www.flipr.tv/playerAPI/channelLineup?v=40&channel=35801" && echo
curl -s "http://www.flipr.tv/playerAPI/channelLineup?v=40&channel=35804" && echo
curl -s "http://www.flipr.tv/playerAPI/channelLineup?v=40&channel=35813" && echo
curl -s "http://www.flipr.tv/playerAPI/channelLineup?v=40&channel=35802" && echo
curl -s "http://www.flipr.tv/playerAPI/channelLineup?v=40&channel=35806" && echo
curl -s "http://www.flipr.tv/playerAPI/channelLineup?v=40&channel=35808" && echo
curl -s "http://www.flipr.tv/playerAPI/channelLineup?v=40&channel=35811" && echo
curl -s "http://www.flipr.tv/playerAPI/channelLineup?v=40&channel=35781" && echo
curl -s "http://www.flipr.tv/playerAPI/channelLineup?v=40&channel=35782" && echo
curl -s "http://www.flipr.tv/playerAPI/channelLineup?v=40&channel=35783" && echo
curl -s "http://www.flipr.tv/playerAPI/channelLineup?v=40&channel=35787" && echo
curl -s "http://www.flipr.tv/playerAPI/channelLineup?v=40&channel=35788" && echo
curl -s "http://www.flipr.tv/playerAPI/channelLineup?v=40&channel=35790" && echo
curl -s "http://www.flipr.tv/playerAPI/channelLineup?v=40&channel=35791" && echo
curl -s "http://www.flipr.tv/playerAPI/channelLineup?v=40&channel=35786" && echo
curl -s "http://www.flipr.tv/playerAPI/channelLineup?v=40&channel=35793" && echo
curl -s "http://www.flipr.tv/playerAPI/channelLineup?v=40&channel=35800" && echo
curl -s "http://www.flipr.tv/playerAPI/channelLineup?v=40&channel=35803" && echo
curl -s "http://www.flipr.tv/playerAPI/channelLineup?v=40&channel=35805" && echo
curl -s "http://www.flipr.tv/playerAPI/channelLineup?v=40&channel=35807" && echo
curl -s "http://www.flipr.tv/playerAPI/channelLineup?v=40&channel=35809" && echo
curl -s "http://www.flipr.tv/playerAPI/channelLineup?v=40&channel=35812" && echo
curl -s "http://www.flipr.tv/playerAPI/channelLineup?v=40&channel=35814" && echo
curl -s "http://www.flipr.tv/playerAPI/channelLineup?v=40&channel=35816" && echo
curl -s "http://www.flipr.tv/playerAPI/channelLineup?v=40&channel=35818" && echo
curl -s "http://www.flipr.tv/playerAPI/channelLineup?v=40&channel=35819" && echo
curl -s "http://www.flipr.tv/playerAPI/channelLineup?v=40&channel=35820" && echo
curl -s "http://www.flipr.tv/playerAPI/channelLineup?v=40&channel=35821" && echo
curl -s "http://www.flipr.tv/playerAPI/channelLineup?v=40&channel=35822" && echo
curl -s "http://www.flipr.tv/playerAPI/channelLineup?v=40&channel=35792" && echo
curl -s "http://www.flipr.tv/playerAPI/channelLineup?v=40&channel=35795" && echo
curl -s "http://www.flipr.tv/playerAPI/channelLineup?v=40&channel=35799" && echo
curl -s "http://www.flipr.tv/playerAPI/channelLineup?v=40&channel=35810" && echo
curl -s "http://www.flipr.tv/playerAPI/channelLineup?v=40&channel=25796" && echo
curl -s "http://www.flipr.tv/playerAPI/channelLineup?v=40&channel=35798" && echo
curl -s "http://www.flipr.tv/playerAPI/channelLineup?v=40&channel=35823" && echo

echo
echo "autosync finished at $(date)"
echo

