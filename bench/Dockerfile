# Bench container for the competitor libraries (OpenSpout and
# fast-excel-writer need ext-zip, which php:*-cli doesn't ship).
ARG PHP_VERSION=8.5
FROM php:${PHP_VERSION}-cli
RUN apt-get update \
 && apt-get install -y --no-install-recommends libzip-dev \
 && docker-php-ext-install zip \
 && rm -rf /var/lib/apt/lists/*
WORKDIR /repo/bench
