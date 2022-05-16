ARG PHP_VERSION=8.0

FROM composer:2 AS composer

FROM php:${PHP_VERSION}-cli

COPY --from=composer /usr/bin/composer /usr/bin/composer

RUN apt-get update && apt-get install -y --no-install-recommends \
      git \
      libzip-dev \
    && docker-php-ext-install -j $(nproc) \
      zip \
    && rm -rf /var/lib/apt/lists/*