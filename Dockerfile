FROM composer:2 AS vendor

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-scripts \
    --prefer-dist \
    --no-interaction \
    --no-progress \
    --optimize-autoloader \
    --ignore-platform-req=ext-zip \
    --ignore-platform-req=ext-intl

FROM node:22-bookworm-slim AS node-runtime

FROM php:8.4-cli-bookworm AS php-base

WORKDIR /var/www/html

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        ca-certificates \
        curl \
        gnupg \
    && install -d /etc/apt/keyrings \
    && curl -fsSL https://ngrok-agent.s3.amazonaws.com/ngrok.asc | gpg --dearmor -o /etc/apt/keyrings/ngrok.gpg \
    && echo "deb [signed-by=/etc/apt/keyrings/ngrok.gpg] https://ngrok-agent.s3.amazonaws.com buster main" > /etc/apt/sources.list.d/ngrok.list \
    && apt-get update \
    && apt-get install -y --no-install-recommends \
        libicu-dev \
        libpq-dev \
        libsqlite3-dev \
        libzip-dev \
        ngrok \
        postgresql-client \
        rsync \
        unzip \
    && docker-php-ext-install intl pdo_pgsql pdo_sqlite zip \
    && { \
        echo 'memory_limit=512M'; \
        echo 'realpath_cache_size=64M'; \
        echo 'realpath_cache_ttl=600'; \
    } > /usr/local/etc/php/conf.d/zz-creditsoft-runtime.ini \
    && rm -rf /var/lib/apt/lists/*

FROM php-base AS frontend

WORKDIR /app

ENV APP_ENV=production \
    APP_DEBUG=false \
    APP_KEY=base64:MkRvdTQyaE1qazZIVGZQaWNCb2QwRGpSYlJUTmRtTlE= \
    DB_CONNECTION=sqlite \
    DB_DATABASE=/tmp/creditsoft-build.sqlite \
    CACHE_STORE=array \
    SESSION_DRIVER=array \
    QUEUE_CONNECTION=sync

COPY --from=node-runtime /usr/local/bin/node /usr/local/bin/node
COPY --from=node-runtime /usr/local/lib/node_modules /usr/local/lib/node_modules
RUN ln -sf /usr/local/lib/node_modules/npm/bin/npm-cli.js /usr/local/bin/npm \
    && ln -sf /usr/local/lib/node_modules/npm/bin/npx-cli.js /usr/local/bin/npx

COPY package.json package-lock.json ./
RUN npm ci

COPY --from=vendor /app/vendor ./vendor
COPY . .
RUN touch /tmp/creditsoft-build.sqlite
RUN rm -f bootstrap/cache/*.php
RUN php artisan wayfinder:generate --with-form -vvv
RUN npm run build

FROM php-base

WORKDIR /var/www/html

COPY --from=vendor /app/vendor ./vendor
COPY --from=frontend /app/public/build ./public/build
COPY . .
RUN rm -f bootstrap/cache/*.php

RUN APP_ENV=production \
    APP_DEBUG=false \
    APP_KEY=base64:MkRvdTQyaE1qazZIVGZQaWNCb2QwRGpSYlJUTmRtTlE= \
    DB_CONNECTION=sqlite \
    DB_DATABASE=/tmp/creditsoft-build.sqlite \
    CACHE_STORE=array \
    SESSION_DRIVER=array \
    QUEUE_CONNECTION=sync \
    php artisan package:discover --ansi

RUN mkdir -p \
        bootstrap/cache \
        database \
        storage/app/private \
        storage/app/public \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
    && chown -R www-data:www-data storage bootstrap/cache database

COPY docker/entrypoint.sh /usr/local/bin/creditsoft-entrypoint
RUN chmod +x /usr/local/bin/creditsoft-entrypoint

EXPOSE 8001

ENTRYPOINT ["creditsoft-entrypoint"]
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8001"]
