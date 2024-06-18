FROM node:14-slim AS node_modules
WORKDIR /var/www/pastell/
COPY package*.json ./
RUN npm install

# TODO il faudra passer en PHP 8.1 une fois que scoper suportera cette version
FROM php:7.4-cli AS extensions_builder
WORKDIR /app

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN apt-get update && apt-get install -y --no-install-recommends \
    git \
    unzip \
    zip \
    && rm -rf /var/lib/apt/lists/*

RUN curl \
    --location \
    --output /usr/bin/php-scoper \
    --url https://github.com/humbug/php-scoper/releases/download/0.17.0/php-scoper.phar \
    && chmod +x /usr/bin/php-scoper

COPY ./extensions/pastell-depot-cmis/ /app/
RUN composer install --ignore-platform-reqs \
    && php-scoper add-prefix --force \
    && composer dump-autoload --working-dir=build

FROM ubuntu:22.04 AS pastell_base

ARG UID=33
ARG GID=33
ARG USERNAME=www-data
ARG GROUPNAME=www-data

ENV PHP_OPCACHE_VALIDATE_TIMESTAMPS="0" \
    PHP_OPCACHE_MAX_ACCELERATED_FILES="10000" \
    PHP_OPCACHE_MEMORY_CONSUMPTION="192" \
    PHP_OPCACHE_MAX_WASTED_PERCENTAGE="10"

EXPOSE 443 80

WORKDIR /var/www/pastell/
ENV PATH="${PATH}:/var/www/pastell/vendor/bin/"

# Install requirements
COPY ./docker/install-requirements.sh /var/www/pastell/docker/
RUN /bin/bash /var/www/pastell/docker/install-requirements.sh

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Create Pastell needs
COPY ./docker /var/www/pastell/docker

RUN /bin/bash /var/www/pastell/docker/docker-construction.sh

COPY --chown=${USERNAME}:${GROUPNAME} --from=node_modules /var/www/pastell/node_modules /var/www/pastell/node_modules

# Composer stuff
COPY ./composer.* /var/www/pastell/
RUN --mount=type=secret,id=composer_auth,dst=/var/www/pastell/auth.json \
    /bin/bash -c 'mkdir -p /var/www/pastell/{web,web-mailsec}' && \
    COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --no-autoloader

# Pastell sources
COPY --chown=${USERNAME}:${GROUPNAME} ./ /var/www/pastell/
COPY --chown=${USERNAME}:${GROUPNAME} --from=extensions_builder /app/build /var/www/pastell/extensions/pastell-depot-cmis/build

RUN chown ${USERNAME}:${GROUPNAME} /var/www/pastell/

RUN COMPOSER_ALLOW_SUPERUSER=1 composer dump-autoload --no-dev --optimize

USER "${USERNAME}"

HEALTHCHECK CMD curl --fail -k https://localhost/ --noproxy '*' || exit 1

ENTRYPOINT ["docker-pastell-entrypoint"]
CMD ["/usr/bin/supervisord"]

FROM pastell_base AS pastell_dev

ARG UID=33
ARG GID=33
ARG USERNAME=www-data
ARG GROUPNAME=www-data

USER root
RUN /bin/bash /var/www/pastell/docker/install-dev-requirements.sh
USER "${USERNAME}"
FROM pastell_base AS pastell_prod
