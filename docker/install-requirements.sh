#!/bin/bash

set -e -x

if [ ${GID} -ne 33 ] ; then
  addgroup --gid "${GID}" "${GROUPNAME}"
fi

if [ ${UID} -ne 33 ] ; then
  adduser --uid "${UID}" --gid "${GID}" --gecos "" --disabled-password "${USERNAME}"
fi

export DEBIAN_FRONTEND=noninteractive

apt-get update

apt-get -y dist-upgrade

apt-get install -y --no-install-recommends \
    apache2 \
    ca-certificates \
    certbot \
    cron \
    curl \
    git \
    graphviz \
    language-pack-fr \
    logrotate \
    php \
    php-bcmath \
    php-curl \
    php-imap \
    php-intl \
    php-ldap \
    php-mbstring \
    php-mysql \
    php-redis \
    php-soap \
    php-uuid \
    php-xml \
    php-zip \
    supervisor \
    unzip \
    wget \
    xmlstarlet

rm -r /var/lib/apt/lists/*

a2enmod \
    headers \
    proxy \
    proxy_http \
    rewrite \
    ssl

echo 'LANG="fr_FR.UTF-8"'>/etc/default/locale
dpkg-reconfigure --frontend=noninteractive locales
update-locale LANG=fr_FR.UTF-8

rm /etc/localtime && ln -s /usr/share/zoneinfo/Europe/Paris /etc/localtime

chmod u+s /usr/sbin/cron