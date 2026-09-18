FROM hubdocker.libriciel.fr/php:7.4-cli
WORKDIR /app

COPY --from=hubdocker.libriciel.fr/composer:2 /usr/bin/composer /usr/bin/composer

RUN curl \
    --location \
    --output /usr/bin/php-scoper \
    --url https://github.com/humbug/php-scoper/releases/download/0.17.0/php-scoper.phar \
  && chmod +x /usr/bin/php-scoper
