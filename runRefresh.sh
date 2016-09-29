#!/bin/bash
NOW=$(date +"%Y-%m-%d")
LOGFILE="log-$NOW.log"
/usr/bin/php /home/comadmin/refresh.php >> /home/comadmin/logs/$LOGFILE
