# Application runtime, served by Nginx over FastCGI.
FROM php:8.2-fpm

RUN apt-get update \
    && apt-get install -y --no-install-recommends $PHPIZE_DEPS \
    && docker-php-ext-install -j"$(nproc)" pdo_mysql opcache \
    && apt-get purge -y --auto-remove $PHPIZE_DEPS \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app
