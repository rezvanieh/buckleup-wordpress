# Custom WordPress PHP-FPM image for BuckleUp.
# Pins WP + PHP and adds the extensions WordPress + the BuckleUp plugin need
# (redis for object cache, intl/gd for media + i18n, opcache for speed).
ARG WP_VERSION=6.9
ARG PHP_VERSION=8.3
FROM wordpress:${WP_VERSION}-php${PHP_VERSION}-fpm

# System libs for the PHP extensions below
RUN apt-get update && apt-get install -y --no-install-recommends \
      libicu-dev \
      libzip-dev \
      git \
      less \
      default-mysql-client \
    && rm -rf /var/lib/apt/lists/*

# Redis (object cache) via PECL; intl/zip via docker-php-ext
RUN pecl install redis \
    && docker-php-ext-enable redis \
    && docker-php-ext-configure intl \
    && docker-php-ext-install -j"$(nproc)" intl zip

# Dev-friendly PHP + OPcache settings
COPY php.ini /usr/local/etc/php/conf.d/zz-buckleup.ini

# Install WP-CLI in the image so we can run it without a separate container if desired
RUN curl -fsSL https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar -o /usr/local/bin/wp \
    && chmod +x /usr/local/bin/wp
