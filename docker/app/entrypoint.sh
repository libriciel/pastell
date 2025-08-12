#!/bin/bash

set -e

/bin/bash /app/docker/app/docker-pastell-init > /data/config/DockerSettings.php

CRONTAB_FILE=/data/config/crontab
> "$CRONTAB_FILE"
for file in "/app/docker/app/cron.d"/*; do
  cat "$file" >> "$CRONTAB_FILE"
  echo "" >> "$CRONTAB_FILE"
done

/bin/bash /app/docker/app/wait-for-cacert.sh

if [ -z "$DONT_INIT_DATABASE" ] ; then
  php /app/docker/app/init-docker.php
  /app/bin/console app:bootstrap -vv
fi

exec "$@"
