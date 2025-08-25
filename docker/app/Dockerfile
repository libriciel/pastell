FROM hubdocker.libriciel.fr/node:22-alpine3.22 AS node_modules
WORKDIR /app/
COPY package*.json ./
RUN npm install

FROM hubdocker.libriciel.fr/php:8.4.11-fpm-alpine3.22 AS base

ARG UID=10001
ARG GID=10001

ENV PHP_OPCACHE_VALIDATE_TIMESTAMPS="0" \
    PHP_OPCACHE_MAX_ACCELERATED_FILES="10000" \
    PHP_OPCACHE_MEMORY_CONSUMPTION="192" \
    PHP_OPCACHE_MAX_WASTED_PERCENTAGE="10"

WORKDIR /app

EXPOSE 9000

RUN apk add --no-cache $PHPIZE_DEPS \
    bash \
    bash-completion \
    ca-certificates \
    curl \
    curl-dev \
    git \
    graphviz \
    icu-data-full \
    icu-dev \
    imap-dev \
    krb5-dev \
    libxml2-dev \
    libxslt \
    libxslt-dev \
    libzip-dev \
    logrotate \
    musl-locales \
    musl-locales-lang \
    oniguruma-dev \
    openldap-dev \
    supercronic \
    supervisor \
    tzdata \
    wget \
    xmlstarlet

RUN pecl install imap redis uuid

RUN docker-php-ext-install \
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
    zip

RUN docker-php-ext-enable \
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
    zip

RUN addgroup -S app -g "$GID" && adduser -S app -G app -u "$UID"

COPY ./docker /app/docker
COPY ./docker/app/entrypoint.sh /usr/local/bin/
COPY ./docker/logrotate.d/pastell.conf /etc/logrotate.d/

RUN mkdir /etc/ldap
COPY ./docker/app/ldap/ldap.conf /etc/ldap/

RUN bash -c "mkdir -p /data/{config,html_purifier,log,run,session,upload_chunk,workspace}" \
 && chown "$UID":"$GID" -R /data/

COPY --from=node_modules /app/node_modules/ /app/node_modules/
COPY --from=hubdocker.libriciel.fr/composer:2.8.11 /usr/bin/composer /usr/local/bin/composer
COPY ./composer.* /app/
RUN --mount=type=secret,id=composer_auth,dst=/app/auth.json \
    /bin/bash -c 'mkdir -p /app/{web,web-mailsec}' && \
    composer install --no-ansi --no-dev --no-interaction --no-progress --no-scripts --no-autoloader

COPY ./docker/app/php/ /usr/local/etc/php/conf.d/

COPY --chown=app:app ./ /app/
RUN chown app:app /app/

RUN cp /app/docker/app/supervisord/supervisord.conf /etc/supervisord.conf

RUN composer run-script --no-dev post-install-cmd && composer dump-autoload --no-dev --optimize

RUN rm /usr/local/bin/composer
USER "app"

HEALTHCHECK CMD supervisorctl status | grep -q "php-fpm.*RUNNING" && supervisorctl status | grep -q "supercronic.*RUNNING"

ENTRYPOINT ["entrypoint.sh"]
CMD ["/usr/bin/supervisord"]

FROM base AS dev

USER "root"

COPY --from=hubdocker.libriciel.fr/composer:2.8.11 /usr/bin/composer /usr/local/bin/composer

RUN apk add --no-cache $PHPIZE_DEPS \
    linux-headers \
    nodejs \
    npm

RUN pecl install pcov xdebug
RUN docker-php-ext-enable pcov xdebug
RUN composer install

USER "app"

FROM base AS prod
