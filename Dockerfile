# syntax=docker/dockerfile:1.7

ARG PHP_VERSION=8.4
ARG NODE_VERSION=22

FROM node:${NODE_VERSION}-bookworm-slim AS node

FROM serversideup/php:${PHP_VERSION}-fpm-nginx AS base

USER root

RUN install-php-extensions intl bcmath

ENV PHP_OPCACHE_ENABLE=1 \
    PHP_OPCACHE_VALIDATE_TIMESTAMPS=0 \
    PHP_OPCACHE_MEMORY_CONSUMPTION=256 \
    PHP_OPCACHE_JIT=tracing \
    PHP_OPCACHE_JIT_BUFFER_SIZE=64M \
    PHP_FPM_PM_CONTROL=static \
    PHP_FPM_PM_MAX_CHILDREN=16 \
    PHP_FPM_PM_MAX_REQUESTS=1000 \
    PHP_MEMORY_LIMIT=256M \
    PHP_MAX_EXECUTION_TIME=60 \
    PHP_UPLOAD_MAX_FILE_SIZE=50M \
    PHP_POST_MAX_SIZE=55M \
    NGINX_CLIENT_MAX_BODY_SIZE=55M \
    LOG_CHANNEL=stderr

COPY --chmod=755 docker/entrypoint.d/ /etc/entrypoint.d/

WORKDIR /var/www/html

FROM base AS dev

COPY --from=node /usr/local/bin/node /usr/local/bin/node
COPY --from=node /usr/local/lib/node_modules /usr/local/lib/node_modules
RUN ln -s /usr/local/lib/node_modules/npm/bin/npm-cli.js /usr/local/bin/npm \
    && ln -s /usr/local/lib/node_modules/npm/bin/npx-cli.js /usr/local/bin/npx

ENV PHP_OPCACHE_VALIDATE_TIMESTAMPS=1 \
    PHP_OPCACHE_JIT=off \
    PHP_OPCACHE_JIT_BUFFER_SIZE=0 \
    PHP_FPM_PM_CONTROL=ondemand \
    PHP_DISPLAY_ERRORS=On

COPY --chown=www-data:www-data composer.json composer.lock ./
RUN composer install --no-interaction --no-progress --prefer-dist --no-scripts --no-autoloader

COPY --chown=www-data:www-data package.json package-lock.json .npmrc ./
RUN npm ci --no-audit --no-fund

COPY --chown=www-data:www-data . .
RUN composer dump-autoload --no-interaction \
    && chown -R www-data:www-data /var/www/html

USER www-data

FROM dev AS assets

RUN npm run build

FROM base AS prod

COPY --chown=www-data:www-data composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-progress --prefer-dist --no-scripts --no-autoloader

COPY --chown=www-data:www-data . .
COPY --from=assets --chown=www-data:www-data /var/www/html/public/build ./public/build

RUN composer dump-autoload --no-dev --optimize --classmap-authoritative --no-interaction \
    && rm -rf tests node_modules resources/js resources/css \
    && chown -R www-data:www-data /var/www/html

ENV PHP_OPCACHE_ENABLE_CLI=0 \
    PHP_OPCACHE_PRELOAD=/var/www/html/docker/php/preload.php \
    PHP_OPCACHE_PRELOAD_USER=www-data

USER www-data

HEALTHCHECK --interval=10s --timeout=5s --start-period=60s --retries=12 \
    CMD curl -fsS http://localhost:8080/up || exit 1
