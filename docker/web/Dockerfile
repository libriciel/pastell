FROM hubdocker.libriciel.fr/node:22-alpine3.22 AS node_modules
WORKDIR /app/
COPY package*.json ./
RUN npm install

FROM hubdocker.libriciel.fr/httpd:2.4.65-alpine

RUN apk add --no-cache \
    certbot \
    coreutils \
    curl \
    logrotate \
    openssl \
    supercronic \
    supervisor

COPY ./docker/web/entrypoint.sh /usr/local/bin/
COPY ./docker/web/scripts/ /usr/local/bin/

COPY ./docker/web/vhost/ /usr/local/apache2/conf/vhosts/
COPY ./docker/web/security.conf /usr/local/apache2/conf/extra/

COPY ./docker/logrotate.d/pastell.conf /etc/logrotate.d/
COPY ./docker/web/crontab /data/crontab
COPY ./docker/web/supervisord.conf /etc/supervisord.conf

RUN mkdir -p /data/certificates && mkdir -p /data/log && chown www-data:www-data -R /data/

RUN echo "PidFile /tmp/httpd.pid" >> /usr/local/apache2/conf/httpd.conf && \
    echo "IncludeOptional conf/vhosts/*.conf" >> /usr/local/apache2/conf/httpd.conf && \
    echo "Include conf/extra/security.conf" >> /usr/local/apache2/conf/httpd.conf

RUN sed -i '/^Listen 80$/a Listen 443' /usr/local/apache2/conf/httpd.conf

RUN sed -i \
    -e 's/#LoadModule http2_module/LoadModule http2_module/' \
    -e 's/#LoadModule headers_module/LoadModule headers_module/' \
    -e 's/#LoadModule proxy_module/LoadModule proxy_module/' \
    -e 's/#LoadModule proxy_http_module/LoadModule proxy_http_module/' \
    -e 's/#LoadModule proxy_fcgi_module/LoadModule proxy_fcgi_module/' \
    -e 's/#LoadModule rewrite_module/LoadModule rewrite_module/' \
    -e 's/#LoadModule socache_shmcb_module/LoadModule socache_shmcb_module/' \
    -e 's/#LoadModule ssl_module/LoadModule ssl_module/' \
    /usr/local/apache2/conf/httpd.conf

COPY ../../web /app/web
COPY ../../web-mailsec /app/web-mailsec
COPY --from=node_modules /app/node_modules/ /app/node_modules/

RUN ln -sfn /app/node_modules /app/web/node_modules && ln -sfn /app/node_modules /app/web-mailsec/node_modules

USER www-data

EXPOSE 80 443

HEALTHCHECK CMD curl --fail -k https://localhost/ --noproxy '*' || exit 1

ENTRYPOINT ["entrypoint.sh"]
CMD ["/usr/bin/supervisord"]
