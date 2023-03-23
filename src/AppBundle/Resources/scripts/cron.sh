#!/usr/bin/env bash

cd "$(dirname "$0")"

if [ -z "$1" ] && [ -f "$1" ]
then
  php_loc=php
else
  php_loc=$1
fi

COMMANDS=$($php_loc ../../../../bin/console cron:run scheduled)
IFS='||' read -r -a ARRAY <<< "$COMMANDS"
for COMMAND in "${ARRAY[@]}"
do
  if [ ! -z "$COMMAND" ]
  then
    #echo "$php_loc ../../../../bin/console cron:run command '$COMMAND'"
    eval "$php_loc ../../../../bin/console cron:run command '$COMMAND'"
  fi
done