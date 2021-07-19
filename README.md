# ark_server_cron
Actual usage instructions are a WIP.

# Setup for use with a fresh Ubuntu install
```sudo add-apt-repository ppa:ondrej/php
sudo apt-get update
sudo apt-get install lib32gcc1 steamcmd -y
curl -sS https://getcomposer.org/installer -o composer-setup.php
sudo php composer-setup.php --install-dir=/usr/local/bin --filename=composer
sudo apt-get install php7.3 -y
sudo apt-get install php7.3-xml -y
sudo apt install php7.3-dev -y
sudo apt-get install libzip-dev -y
sudo apt install php-pear -y
sudo pecl channel-update pecl.php.net
sudo pecl install zip
sudo su root -c "echo 'extension=zip.so' >> /etc/php/7.3/cli/php.ini"
git clone https://github.com/pdc4444/ark_server_cron.git
composer install
