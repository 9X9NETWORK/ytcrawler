#!/bin/bash
#
# autosync.sh
#
# to put this script in crontab to do youtube sync job periodically
#
#   ex. (sync 4 times a day)
#
#   * */6 * * * sudo su www-data -c "/var/www/ytcrawler/autosync.sh >> /var/tmp/ytcrawl/autosync.log"
#

ytcrawler_dir=$(dirname "$(readlink -f "$0")")
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
    /usr/bin/php "$ytcrawler_dir/ytcrawler.php" $ch
    sleep 1
done

echo
echo "autosync finished at $(date)"
echo

