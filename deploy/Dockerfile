# syntax=docker/dockerfile:1
#
# Athari production image: Drupal 11 on FrankenPHP (PHP 8.3 + the Caddy web
# server, with automatic HTTPS). Built from the repository by:
#   docker compose -f deploy/docker-compose.yml build
#
# Development tools (Devel, PHPUnit, PHPCS) are NOT installed: Composer runs
# with --no-dev.

ARG PHP_BASE=dunglas/frankenphp:1-php8.3

# ---------------------------------------------------------------- build stage
FROM ${PHP_BASE} AS build
RUN apt-get update \
 && apt-get install -y --no-install-recommends git unzip \
 && rm -rf /var/lib/apt/lists/*
COPY --from=ghcr.io/mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions gd intl zip opcache pdo_mysql
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

WORKDIR /app
COPY . /app
RUN COMPOSER_ALLOW_SUPERUSER=1 composer install \
      --no-dev --optimize-autoloader --no-interaction --prefer-dist \
 && rm -rf /root/.composer /root/.cache

# --------------------------------------------------------------- runtime stage
FROM ${PHP_BASE} AS runtime
RUN apt-get update \
 && apt-get install -y --no-install-recommends libcap2-bin mariadb-client curl \
 && rm -rf /var/lib/apt/lists/*
COPY --from=ghcr.io/mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions gd intl zip opcache pdo_mysql apcu

COPY deploy/php.ini /usr/local/etc/php/conf.d/zz-athari.ini
COPY deploy/Caddyfile /etc/frankenphp/Caddyfile

WORKDIR /app
COPY --from=build --chown=www-data:www-data /app /app

# Writable places, owned by the unprivileged user so named volumes inherit them.
RUN mkdir -p /app/web/sites/default/files /app/private /data/caddy /config/caddy \
 && chown -R www-data:www-data /app/web/sites/default/files /app/private /data /config \
 && setcap CAP_NET_BIND_SERVICE=+ep /usr/local/bin/frankenphp

ENV XDG_CONFIG_HOME=/config XDG_DATA_HOME=/data PATH="/app/vendor/bin:${PATH}"
USER www-data
