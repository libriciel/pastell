FROM hubdocker.libriciel.fr/node:24.13-alpine3.23@sha256:4f696fbf39f383c1e486030ba6b289a5d9af541642fc78ab197e584a113b9c03 AS node_modules
WORKDIR /app/
COPY package*.json ./
RUN npm install

FROM hubdocker.libriciel.fr/php:8.4.18-fpm-alpine3.23@sha256:b7bad36533116d6360d00c3b12820be69bf7655af6057f6222b57befa5eee5c4 AS base

ARG UID=10001
ARG GID=10001

ENV PHP_OPCACHE_VALIDATE_TIMESTAMPS="0" \
    PHP_OPCACHE_MAX_ACCELERATED_FILES="10000" \
    PHP_OPCACHE_MEMORY_CONSUMPTION="192" \
    PHP_OPCACHE_MAX_WASTED_PERCENTAGE="10"

WORKDIR /app

EXPOSE 9000

RUN apk add --no-cache \
    bash \
    bash-completion \
    c-client \
    ca-certificates \
    curl \
    git \
    graphviz \
    icu \
    icu-data-full \
    krb5 \
    libxml2 \
    libxslt \
    libzip \
    logrotate \
    musl-locales \
    musl-locales-lang \
    oniguruma \
    openldap \
    supercronic \
    supervisor \
    tzdata \
    wget \
    xmlstarlet

RUN apk add --no-cache --virtual .build-deps \
    $PHPIZE_DEPS \
    curl-dev \
    icu-dev \
    imap-dev \
    krb5-dev \
    libxml2-dev \
    libxslt-dev \
    libzip-dev \
    oniguruma-dev \
    openldap-dev \
  && pecl install imap redis uuid \
  && docker-php-ext-install -j$(nproc) \
    bcmath \
    ftp \
    intl \
    ldap \
    mbstring \
    opcache \
    pcntl \
    pdo_mysql \
    soap \
    xml \
    xsl \
    zip \
  && docker-php-ext-enable \
    bcmath \
    imap \
    ftp \
    imap \
    intl \
    ldap \
    mbstring \
    opcache \
    pcntl \
    pdo_mysql \
    redis \
    soap \
    uuid \
    xml \
    xsl \
    zip \
  && apk del .build-deps

RUN addgroup -S app -g "$GID" && adduser -S app -G app -u "$UID"

COPY ./docker /app/docker
COPY ./docker/app/entrypoint.sh /usr/local/bin/
COPY ./docker/logrotate.d/pastell.conf /etc/logrotate.d/

RUN mkdir /etc/ldap
COPY ./docker/app/ldap/ldap.conf /etc/ldap/

RUN bash -c "mkdir -p /data/{config,html_purifier,log,run,session,upload_chunk,workspace}" \
 && chown "$UID":"$GID" -R /data/

COPY --from=node_modules /app/node_modules/ /app/node_modules/
COPY --from=hubdocker.libriciel.fr/composer:2.9.5@sha256:f0809732b2188154b3faa8e44ab900595acb0b09cd0aa6c34e798efe4ebc9021 /usr/bin/composer /usr/local/bin/composer
COPY ./composer.* /app/
RUN --mount=type=secret,id=composer_auth,dst=/app/auth.json \
    /bin/bash -c 'mkdir -p /app/{web,web-mailsec}' && \
    composer install --no-ansi --no-dev --no-interaction --no-progress --no-scripts --no-autoloader

COPY ./docker/app/php/ /usr/local/etc/php/conf.d/

COPY --chown=app:app ./ /app/
RUN chown app:app /app/

RUN sh /app/docker/app/add-legacy-provider-to-openssl-v3.sh

RUN cp /app/docker/app/supervisord/supervisord.conf /etc/supervisord.conf

RUN composer run-script --no-dev post-install-cmd && composer dump-autoload --no-dev --optimize

RUN rm /usr/local/bin/composer
USER "app"

HEALTHCHECK CMD supervisorctl status | grep -q "php-fpm.*RUNNING" && supervisorctl status | grep -q "supercronic.*RUNNING"

ENTRYPOINT ["entrypoint.sh"]
CMD ["/usr/bin/supervisord"]

FROM base AS dev

USER "root"

COPY --from=hubdocker.libriciel.fr/composer:2.9.5@sha256:f0809732b2188154b3faa8e44ab900595acb0b09cd0aa6c34e798efe4ebc9021 /usr/bin/composer /usr/local/bin/composer

RUN apk add --no-cache nodejs npm

RUN apk add --no-cache --virtual .dev-build-deps \
    $PHPIZE_DEPS \
    linux-headers \
 && pecl install pcov xdebug \
 && docker-php-ext-enable pcov xdebug \
 && apk del .dev-build-deps

RUN composer install

USER "app"

FROM base AS prod
