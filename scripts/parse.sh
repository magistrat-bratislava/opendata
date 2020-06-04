#!/usr/bin/env bash

cd "$(dirname "$0")"

if [[ "$OSTYPE" == "linux-gnu" ]];
then
    date="$(date -d yesterday '+%d-%m-%Y')"
else
    date="$(date -v -1d '+%d-%m-%Y')"
fi

mkdir ./temp/export
mkdir ./temp/download

rsync -avzh otuuserftp@172.25.5.82:/home/otuuserftp/ftp/files/* ./temp/download

### ONE ###

FILE=./temp/download/export-$date.zip

if [ -f "$FILE" ]; then

    unzip "./temp/download/export-$date.zip" -d ./temp/export

    php parse.php summary temp/export/summary-export-$date.csv
    php parse.php form temp/export/form-data-export-$date.csv
    php parse.php locations temp/export/locations-export-$date.csv

    rm -R ./temp/export/*

else

    TELEGRAM_BOT_TOKEN="1134560769:AAE6dnxTR8mrqfvJK5mq4clKqVmEWIBslUQ"

    curl -X POST \
         -d chat_id="-490904938" \
         -d text="File export-$date.zip was not found" \
         https://api.telegram.org/bot$TELEGRAM_BOT_TOKEN/sendMessage

fi
