#!/bin/bash

# Only users with $UID 0 have root privileges.
root_uid=0

# Run as root, of course.
if [ "$UID" -ne "$root_uid" ]
then
  echo "Must be root to run this script."
  exit
fi

#Check if PHP is installed if not, install it
php_check=$(which php)

if [ "$php_check" != "/usr/bin/php" ]
then
	echo 'Installing PHP'
	apt-get update
	apt-get install php -y
fi

#Check if Steam user exists if not, create it and switch to that user
steam_check=$(ls /home | grep steam)
if [ "$steam_check" != "steam" ]
then
	echo 'steam user does not exist, creating the user with the default password steam'.
	sudo su -c "useradd steam -s /bin/bash -m" && echo steam:steam | sudo chpasswd
fi

#Check if zip is installed if not, install it
zip_check=$(which zip)
if [ "$zip_check" != "/usr/bin/zip" ]
then
	echo 'Installing Zip'
	apt-get update
	apt install zip -y
fi

#Check if steamcmd is installed if not, install it
steamcmd_check=$(sudo su steam -c 'which steamcmd')
if [ "$steamcmd_check" != "/usr/games/steamcmd" ]
then
	echo 'Installing Steamcmd'
	add-apt-repository multiverse 
	dpkg --add-architecture i386
	apt-get update
	apt-get install lib32gcc1 steamcmd -y
fi

#Copy the cron and it's dependencies to the steam user home directory
sudo -H -u steam bash -c 'cp ark_server_cron.php /home/steam/ark_server_cron.php'
sudo -H -u steam bash -c 'cp ark_configuration_file_settings.php /home/steam/ark_configuration_file_settings.php'

#Adjust permissions and copy the Configuration Template to the steam user home directory
sudo cp -r Saved/ /home/steam/
sudo chown -R steam:steam /home/steam/Saved/

#Run the cron as the steam user
sudo su steam -c '/usr/bin/php /home/steam/ark_server_cron.php'
