#!/usr/bin/env bash

set -e -x

export DEBIAN_FRONTEND=noninteractive

apt-get update

apt-get install -y --no-install-recommends \
    bash-completion \
    php-pcov \
    php-xdebug

rm -r /var/lib/apt/lists/*

phpenmod xdebug pcov

COMPOSER_ALLOW_SUPERUSER=1 composer install

cp /var/www/pastell/docker/bash_completion.d/* /etc/bash_completion.d/
