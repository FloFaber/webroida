#!/bin/bash

if [[ $EUID -ne 0 ]]; then
  echo "This script must be run as root" 
  exit 1
fi


tempdir="/etc/webroida/temp"
update(){

  echo $tempdir

  wget https://dl.webroida.com/latest.zip -O "$tempdir/latest.zip"
  unzip -o -q "$tempdir/latest.zip" -d "$tempdir"
  
  ### APACHE ###

  # copy to www-root
  mkdir -p /var/www/webroida/
  cp -R "$tempdir/www-root/"* /var/www/webroida/
  cp "$tempdir/.htaccess" /var/www/webroida/.htaccess
  chown -R www-data:www-data /var/www/webroida/

  # copy apache config
  cp "$tempdir/apache.conf" /etc/apache2/sites-available/webroida.conf
  a2ensite webroida.conf
  /etc/init.d/apache2 restart



  ### SERVICE ###

  # copy service script
  cp "$tempdir/webroida.service" /etc/init.d/webroida
  chmod +x /etc/init.d/webroida
  update-rc.d webroida defaults
  update-rc.d webroida enable

}



if [ "$1" == "update" ]; then
  echo "OKAY, starting an update now"
  update
else
  echo "OKAY, gonna install this now"
  
  # install all required packages
  apt install apache2 php php-common php-curl php-mysql php-json php-readline libapache2-mod-php -y
  apt install mariadb-server mariadb-client -y
  apt install mpd mpc -y
  apt install ffmpeg -y
  apt install sudo unzip screen bc -y

  # generate random password for DB
  db_user="'webroida'@'localhost'"
  db_name="webroida"
  db_pass=$(head /dev/urandom | tr -dc A-Za-z0-9 | head -c 16 ; echo '')

  mkdir -p $tempdir

  echo "$db_user" > /etc/webroida/db.user
  echo "$db_name" > /etc/webroida/db.name
  echo "$db_pass" > /etc/webroida/db.pass

  # lets just create a backup of the webroida-DB
  mysqldump -u 

  # create DB and table
  mysql -u root -e "drop database if exists $db_name; create database $db_name; DROP user if exists $db_user; create user $db_user identified by '$db_pass'; grant all privileges on $db_name.* to $db_user;"

  # insert table structure
  mysql -u webroida -p$db_pass $db_name < "$tempdir/mysql-struct.sql"


  update

  ### MPD ###

  # copy mpd.conf
  mv /etc/mpd.conf /etc/mpd.conf.orig
  cp "$tempdir/mpd.conf" /etc/mpd.conf


  # copy mpd.proxy.conf
  cp "$tempdir/mpd.proxy.conf" /etc/mpd.proxy.conf

  # change permissions on config files
  chown mpd:www-data /etc/mpd.proxy.conf
  chmod 770 /etc/mpd.proxy.conf

  chown mpd /etc/mpd.conf
  chmod 700 /etc/mpd.conf

  # create new mpd folders
  mkdir -p /mpd/music/
  mkdir -p /mpd/playlists/
  mkdir -p /mpd/tag_cache/

  # touch new playlist files
  touch /mpd/playlists/senders.m3u
  touch /mpd/playlists/songs.m3u
  touch /mpd/playlists/temp.m3u

  # permissions for /mpd/
  chown -R mpd:www-data /mpd/
  chmod 770 -R /mpd/


  ### YOUTUBE-DL ###
  sudo wget https://yt-dl.org/downloads/latest/youtube-dl -O /usr/local/bin/youtube-dl
  sudo chmod a+rx /usr/local/bin/youtube-dl

  # sudoers file so www-data can execute some little pretty restart commands
  if grep -Fxq "www-data ALL=(root) NOPASSWD: /etc/init.d/webroida *, /etc/init.d/mpd restart, /usr/bin/amixer cset numid=3 *, /usr/bin/screen *" /etc/sudoers; then
    sudoers="www-data ALL=(root) NOPASSWD: /etc/init.d/webroida *, /etc/init.d/mpd restart, /usr/bin/amixer cset numid=3 *, /usr/bin/screen *"
    echo $sudoers >> /etc/sudoers
  fi

  /etc/init.d/webroida restart
  /etc/init.d/mpd restart
  mpc update

  # print IP
  ip=$(ip -4 addr show eth0 | grep -oP '(?<=inet\s)\d+(\.\d+){3}')

  echo ""
  echo "Everything is installed!"
  echo "Go to http://$ip:81"
  echo "user: admin"
  echo "pass: admin"
fi
