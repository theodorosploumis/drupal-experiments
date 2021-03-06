#!/bin/bash

ROOT="${GITHUB_WORKSPACE}"
WEB="${ROOT}"/web

# Install composer packages
COMPOSER=composer.json && composer install -q --prefer-dist --no-progress --ansi --no-interaction --no-suggest

# Create database
sudo systemctl start mysql.service
mysql -e "CREATE DATABASE drupal;" -uroot -proot

# Install PHP modules
sudo apt-add-repository ppa:ondrej/php -y
sudo apt-get update
sudo apt-get -y install libapache2-mod-php8.1

# Firewall updates
sudo ufw allow 80
sudo ufw allow 443
sudo ufw allow 22
sudo ufw allow in "Apache"
sudo ufw enable

# Debug
# dpkg --get-selections | grep -i php
# php -m
# sudo cat /etc/apache2/apache2.conf
# sudo cat /etc/apache2/sites-available/000-default.conf
# sudo cat /etc/apache2/mods-available/dir.conf
# sudo a2ensite 000-default.conf

# sudo apt-get install php libapache2-mod-php php-cli php-common \
#      php-gd php-json php-mbstring php-xdebug php-mysql php-opcache php-curl \
#      php-readline php-xml php-memcached php-oauth php-bcmath

sudo a2enmod rewrite
sudo ufw allow in "Apache"

# Copy files
cp .github/config/settings.local.php web/sites/default/settings.local.php
cp .github/config/ci.env .env

# Create Drupal required folders and permissions
mkdir "${WEB}"/sites/default/files
chmod -R 777 "${WEB}"/sites/default/files
chmod 777 "${WEB}"/sites/default/settings.php

sudo rm -rf  /var/www/html
sudo ln -sf "${WEB}" /var/www/html
sudo chown -R www-data:www-data /var/www/html

cp .github/config/info.php /var/www/html/info.php

# Debug
# echo -e "/etc/hosts"
# sudo cat /etc/hosts

echo -e "a2query -m"
a2query -m

sudo service apache2 start

echo -e "curl -v localhost"
curl -v localhost

if ping -c 1 localhost &> /dev/null
then
  echo "Host localhost not found."
  echo "Apache2 service is not active."
else
  echo "Host localhost exists. Apache2 is active."
fi
