# syntax=docker/dockerfile:1

# ==============================================================================
# PokéLink — shared PHP image
#
# One image serves the `app`, `queue`, and `reverb` services; they differ only
# in the command Compose passes. Three stages feed it:
#
#   assets  (node:20)      compiles the Vite/Tailwind bundle
#   vendor  (php + composer) resolves PHP dependencies
#   final   (php:8.3-fpm)  runtime, copying the artifacts from both
#
# Assets and vendor are baked at build time because the runtime image carries
# neither Node nor Composer, and the host is not required to carry them either.
# ==============================================================================


# ------------------------------------------------------------------------------
# Base — the runtime and its extension set, shared by the vendor and final stages
# so the extensions Composer validates against are the ones that actually ship.
# ------------------------------------------------------------------------------
FROM php:8.3-fpm AS base

COPY --from=mlocati/php-extension-installer:latest /usr/bin/install-php-extensions /usr/local/bin/

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        git \
        unzip \
        ca-certificates \
        default-mysql-client \
    && rm -rf /var/lib/apt/lists/*

# pdo_mysql  — MySQL 8.0
# redis      — phpredis, the cache store and queue connection (see README)
# pcntl,posix— required by laravel/horizon
# sockets    — required by laravel/reverb
# opcache    — bytecode cache; tuned in php.ini
# bcmath,intl,zip,gd,exif — framework and image-handling baseline
RUN install-php-extensions \
        pdo_mysql \
        redis \
        pcntl \
        posix \
        sockets \
        opcache \
        bcmath \
        intl \
        zip \
        gd \
        exif

WORKDIR /var/www/html


# ------------------------------------------------------------------------------
# Stage: assets — Vite + Tailwind bundle
# ------------------------------------------------------------------------------
FROM node:20-alpine AS assets

WORKDIR /app

# Dependency layer first, so a source-only change does not reinstall packages.
COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund

COPY vite.config.js tailwind.config.js postcss.config.js ./
COPY resources ./resources

RUN npm run build


# ------------------------------------------------------------------------------
# Stage: vendor — Composer dependencies
#
# require-dev is installed on purpose: `php artisan test` runs inside this
# container (F13), which needs Pest and Faker present. The delivered artifact is
# a local evaluation stack, not a production image.
# ------------------------------------------------------------------------------
FROM base AS vendor

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

ENV COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_NO_INTERACTION=1

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install \
        --prefer-dist \
        --no-progress \
        --no-scripts \
        --no-autoloader

COPY . .
RUN composer dump-autoload --optimize --no-scripts


# ------------------------------------------------------------------------------
# Stage: final — runtime
# ------------------------------------------------------------------------------
FROM base AS final

COPY docker/php/php.ini /usr/local/etc/php/conf.d/99-pokelink.ini
COPY docker/php/www.conf /usr/local/etc/php-fpm.d/zz-pokelink.conf

COPY --chown=www-data:www-data . .
COPY --from=vendor --chown=www-data:www-data /app/vendor ./vendor
COPY --from=assets --chown=www-data:www-data /app/public/build ./public/build

COPY docker/php/entrypoint.sh /usr/local/bin/entrypoint

# The container runs as the host's UID/GID (see `user:` in docker-compose.yml)
# so that files written through the bind mount — .env, the public/storage
# symlink — stay owned by the developer rather than by root. The two writable
# trees are named volumes, initialised from the image, so they are made
# world-writable here: whichever UID Compose picks has to be able to write them.
RUN chmod +x /usr/local/bin/entrypoint \
    && mkdir -p storage/framework/cache/data \
                storage/framework/sessions \
                storage/framework/views \
                storage/logs \
                storage/app/public \
                bootstrap/cache \
    && chmod -R 0777 storage bootstrap/cache

EXPOSE 9000

ENTRYPOINT ["entrypoint"]
CMD ["php-fpm"]
