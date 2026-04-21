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

FROM php:8.5-cli-bookworm AS php-base

WORKDIR /var/www/html

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        ca-certificates \
        curl \
        gnupg \
    && install -d /etc/apt/keyrings \
    && curl -fsSL https://www.postgresql.org/media/keys/ACCC4CF8.asc | gpg --dearmor -o /etc/apt/keyrings/postgresql.gpg \
    && echo "deb [signed-by=/etc/apt/keyrings/postgresql.gpg] http://apt.postgresql.org/pub/repos/apt bookworm-pgdg main" > /etc/apt/sources.list.d/pgdg.list \
    && curl -fsSL https://ngrok-agent.s3.amazonaws.com/ngrok.asc | gpg --dearmor -o /etc/apt/keyrings/ngrok.gpg \
    && echo "deb [signed-by=/etc/apt/keyrings/ngrok.gpg] https://ngrok-agent.s3.amazonaws.com buster main" > /etc/apt/sources.list.d/ngrok.list \
    && curl -fsSL https://pkgs.tailscale.com/stable/debian/bookworm.noarmor.gpg -o /etc/apt/keyrings/tailscale.gpg \
    && echo "deb [signed-by=/etc/apt/keyrings/tailscale.gpg] https://pkgs.tailscale.com/stable/debian bookworm main" > /etc/apt/sources.list.d/tailscale.list \
    && apt-get update \
    && apt-get install -y --no-install-recommends \
        $PHPIZE_DEPS \
        libargon2-dev \
        libc-client2007e-dev \
        libcurl4-openssl-dev \
        libicu-dev \
        libkrb5-dev \
        libonig-dev \
        libpq-dev \
        libreadline-dev \
        libsodium-dev \
        libssl-dev \
        libxml2-dev \
        libzip-dev \
        msmtp \
        msmtp-mta \
        ngrok \
        postgresql-client-16 \
        rsync \
        tailscale \
        unzip \
        zlib1g-dev \
    && docker-php-source extract \
    && cd /usr/src/php \
    && ./configure \
        --build="$(dpkg-architecture --query DEB_BUILD_GNU_TYPE)" \
        --sysconfdir=/usr/local/etc \
        --with-config-file-path=/usr/local/etc/php \
        --with-config-file-scan-dir=/usr/local/etc/php/conf.d \
        --enable-option-checking=fatal \
        --with-mhash \
        --with-pic \
        --enable-mbstring \
        --enable-mysqlnd \
        --with-password-argon2 \
        --with-sodium=shared \
        --without-pdo-sqlite \
        --without-sqlite3 \
        --with-curl \
        --with-iconv \
        --with-openssl \
        --with-readline \
        --with-zlib \
        --enable-phpdbg \
        --enable-phpdbg-readline \
        --with-pear \
        --with-libdir="lib/$(dpkg-architecture --query DEB_HOST_MULTIARCH)" \
        --enable-embed \
        PHP_UNAME="Linux - Docker" \
        PHP_BUILD_PROVIDER=https://github.com/docker-library/php \
    && make -j "$(nproc)" \
    && make install \
    && make clean

RUN docker-php-ext-install intl pdo_pgsql zip \
    && cd /var/www/html \
    && docker-php-source delete \
    && yes '' | pecl install imap \
    && yes '' | pecl install apcu redis \
    && docker-php-ext-enable imap apcu redis \
    && { \
        echo 'opcache.enable=1'; \
        echo 'opcache.enable_cli=1'; \
        echo 'opcache.memory_consumption=192'; \
        echo 'opcache.max_accelerated_files=20000'; \
        echo 'opcache.validate_timestamps=0'; \
        echo 'apc.enable_cli=1'; \
    } > /usr/local/etc/php/conf.d/zz-creditsoft-cache.ini \
    && rm -rf /var/lib/apt/lists/*

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        bzip2 \
        pigz \
        tar \
        xz-utils \
        zip \
        zstd \
    && rm -rf /var/lib/apt/lists/*

FROM php-base AS frontend

WORKDIR /app

ENV APP_ENV=production \
    APP_DEBUG=false \
    APP_KEY=base64:MkRvdTQyaE1qazZIVGZQaWNCb2QwRGpSYlJUTmRtTlE= \
    DB_CONNECTION=pgsql \
    DB_HOST=office-db \
    DB_PORT=5432 \
    DB_DATABASE=creditsoft \
    DB_USERNAME=creditsoft \
    DB_PASSWORD=creditsoft \
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
RUN mkdir -p \
        bootstrap/cache \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
    && rm -f bootstrap/cache/*.php
RUN php artisan wayfinder:generate --with-form -vvv
RUN npm run build

FROM php-base

WORKDIR /var/www/html

COPY --from=vendor /app/vendor ./vendor
COPY --from=frontend /app/public/build ./public/build
COPY . .
RUN mkdir -p \
        bootstrap/cache \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
    && rm -f bootstrap/cache/*.php

RUN APP_ENV=production \
    APP_DEBUG=false \
    APP_KEY=base64:MkRvdTQyaE1qazZIVGZQaWNCb2QwRGpSYlJUTmRtTlE= \
    DB_CONNECTION=pgsql \
    DB_HOST=office-db \
    DB_PORT=5432 \
    DB_DATABASE=creditsoft \
    DB_USERNAME=creditsoft \
    DB_PASSWORD=creditsoft \
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
