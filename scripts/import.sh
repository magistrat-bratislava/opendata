#!/usr/bin/env bash

name="$1"
parse="$2"

cd "$(dirname "$0")"

mkdir ./temp/export
mkdir ./temp/download

rsync -avzh otuuserftp@172.25.5.82:/home/otuuserftp/ftp/files/* ./temp/download

unzip temp/download/"$name" -d temp/export

php parse.php summary temp/export/summary-export-$parse.csv
php parse.php form temp/export/form-data-export-$parse.csv
php parse.php locations temp/export/locations-export-$parse.csv

rm -R ./temp/export/*
