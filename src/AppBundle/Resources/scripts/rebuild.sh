#!/usr/bin/env bash

cd "$(dirname "$0")"
cd ../../ #AppBundle directory
APPBUNDLE_BASE=$(pwd)

cd ../../

BASE=$(pwd)

printf "\nGenerating JS symlinks:"

if [ ! -d "web/backend" ]; then
  mkdir "web/backend"
fi

cd "web/backend"

for i in $(ls -d ../../src/*); do
  BUNDLE=$(echo ${i%%/} | rev | cut -d/ -f1 | rev)
  if [ -d "${i%%/}/Resources/public" ]; then
    if [ -L $BUNDLE ]; then
      if [ -e $BUNDLE ]; then
        printf "\n$BUNDLE symlink already exists"
      else
        rm $BUNDLE
        ln -s "${i%%/}/Resources/public" $BUNDLE
        printf "\nGenerated $BUNDLE symlink"
      fi
    elif [ -e $BUNDLE ]; then
      printf "\nNot a link"
    else
      ln -s "${i%%/}/Resources/public" $BUNDLE
      printf "\nGenerated $BUNDLE symlink"
    fi
  fi
done

printf "\nRebuilding assets..."

cd $BASE

TIMESTAMP=$(date +%s)

sed -i '/^ASSETS_VERSION/d' .env
sed -i "2s|^|ASSETS_VERSION=$TIMESTAMP\n|" .env
