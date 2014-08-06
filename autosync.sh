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

sql="SELECT id FROM nnchannel WHERE status = 0 AND contentType in (16,17)"
chlist=$(echo "$sql" | mysql -u "$dbuser" --password="$dbpass" -h "$dbhost" nncloudtv_content | tail -n +2)
chcnt=$(echo $chlist | wc -w)
echo "$chcnt vertual channels"

echo "cleaning virtual channels cache ..."

for ch in $chlist; do
    echo "clean up ch$ch ... "`curl -s http://www.flipr.tv/wd/programCache?channel=$ch`
    curl -s "http://www.flipr.tv/playerAPI/channelLineup?v=40&channel=$ch"
done

echo
echo "autosync finished at $(date)"
echo

