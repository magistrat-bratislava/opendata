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

### ALL ###

for file in temp/download/*
do
    unzip "$file" -d temp/export

    len=${#file}
    f=${file:14:len-18}

    php parse.php summary temp/export/summary-$f.csv
    php parse.php form temp/export/form-data-$f.csv
    php parse.php locations temp/export/locations-$f.csv

    rm -R ./temp/export/*
done
