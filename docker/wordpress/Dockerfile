# Custom WordPress PHP-FPM image for BuckleUp.
# Pins WP + PHP and adds the extensions WordPress + the BuckleUp plugin need
# (intl for i18n, zip, opcache for speed) plus EWWW's local image-optimizer
# binaries so WebP conversion + bulk optimization run with no cloud API key.
ARG WP_VERSION=6.9
ARG PHP_VERSION=8.3
FROM wordpress:${WP_VERSION}-php${PHP_VERSION}-fpm

# System libs for the PHP extensions below + EWWW Image Optimizer's local tools
# (webp=cwebp, optipng, libjpeg-turbo-progs=jpegtran, gifsicle, pngquant) so the
# plugin can generate WebP + compress uploads locally (no API key needed).
RUN apt-get update && apt-get install -y --no-install-recommends \
      libicu-dev \
      libzip-dev \
      git \
      less \
      default-mysql-client \
      webp \
      optipng \
      libjpeg-turbo-progs \
      gifsicle \
      pngquant \
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
