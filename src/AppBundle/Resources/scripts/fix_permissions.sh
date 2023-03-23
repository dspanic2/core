#!/bin/bash

cd "$(dirname "$0")"
cd ../../ #AppBundle directory
APPBUNDLE_BASE=$(pwd)

cd ../../

BASE=$(pwd)

cd $BASE

printf "\nFixing project permissions..."
sudo chmod 0775 $PWD -R

printf "\nFixing var permissions..."
sudo chmod 0777 $PWD/var -R

printf "\nFixing Documents permissions..."
sudo chmod 0777 $PWD/web/Documents -R

