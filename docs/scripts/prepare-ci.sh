#!/bin/bash

ROOT="${GITHUB_WORKSPACE}"
WEB="${ROOT}"/web

# Install composer packages
COMPOSER=composer.json && composer install -q --prefer-dist --no-progress --ansi --no-interaction --no-suggest

# Create database
sudo systemctl start mysql.service
mysql -e "CREATE DATABASE drupal;" -uroot -proot

# Install PHP modules
php -i
sudo apt-fast install -y --no-install-recommends libapache2-mod-php

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

# Enable apache2
systemctl enable apache2.service
sudo ufw allow in "Apache"

# Debug
# sudo echo /etc/apache2/apache2.conf
# sudo cat /etc/apache2/sites-available/000-default.conf
# sudo cat /etc/apache2/mods-available/dir.conf
# sudo a2ensite 000-default.conf
ls /var/www/html
curl localhost

if ping -c 1 localhost &> /dev/null
then
  echo "Host localhost not found."
  echo "Apache2 service is not active."
else
  echo "Host localhost exists. Apache2 is active."
fi
