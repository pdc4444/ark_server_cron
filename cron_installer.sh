#!/bin/bash

# Only users with $UID 0 have root privileges.
root_uid=0

# Run as root, of course.
if [ "$UID" -ne "$root_uid" ]
then
  echo "Must be root to run this script."
  exit
fi

TZ=US
sudo ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && echo $TZ > /etc/timezone
sudo apt-get update && apt-get install -y software-properties-common && add-apt-repository ppa:ondrej/php
sudo dpkg --add-architecture i386
sudo add-apt-repository multiverse
sudo apt-get update
sudo echo "steam steam/license note '' | debconf-set-selections"
sudo echo 'steam steam/question select "I AGREE" | debconf-set-selections'
sudo apt-get install lib32gcc-s1 steamcmd curl unzip -y
sudo apt-get install php7.3 -y
sudo apt-get install php7.3-xml -y
sudo apt-get install php7.3-readline -y
sudo apt install php7.3-dev -y
sudo apt-get install libzip-dev -y
sudo apt-get install git -y
sudo apt-get install unzip -y
sudo apt install php-pear -y
sudo pecl channel-update pecl.php.net
sudo pecl install zip
sudo echo 'extension=zip.so' >> /etc/php/7.3/cli/php.ini
sudo curl -sS https://getcomposer.org/installer -o composer-setup.php
sudo php composer-setup.php --install-dir=/usr/local/bin --filename=composer