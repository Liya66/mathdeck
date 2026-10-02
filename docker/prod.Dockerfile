# Production image: the source is baked in, not bind-mounted, and no dev tooling
# ships with it. The test image (docker/test.Dockerfile) carries pcov and composer;
# this one carries neither.
FROM composer:2 AS vendor

WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install \
        --no-dev \
        --no-scripts \
        --no-interaction \
        --prefer-dist \
        --classmap-authoritative

FROM php:8.2-fpm-alpine

RUN set -eux; \
    apk add --no-cache --virtual .build-deps $PHPIZE_DEPS; \
    docker-php-ext-install -j"$(nproc)" pdo_mysql opcache; \
    apk del .build-deps

# A production opcache: compiled once, never revalidated. Deploying means a new
# container, which is the only way the code can change.
RUN { \
        echo 'opcache.enable=1'; \
        echo 'opcache.validate_timestamps=0'; \
        echo 'opcache.max_accelerated_files=20000'; \
        echo 'opcache.memory_consumption=192'; \
        echo 'opcache.interned_strings_buffer=16'; \
    } > /usr/local/etc/php/conf.d/opcache.ini

RUN { \
        echo 'expose_php=0'; \
        echo 'display_errors=0'; \
        echo 'log_errors=1'; \
        echo 'error_log=/proc/self/fd/2'; \
    } > /usr/local/etc/php/conf.d/production.ini

WORKDIR /app

COPY --from=vendor /app/vendor ./vendor
COPY composer.json composer.lock ./
COPY src ./src
COPY config ./config
COPY public ./public
COPY schema ./schema
COPY migrations ./migrations
COPY bin ./bin

# Nothing in the image needs to be writable by the process that serves it.
RUN chown -R www-data:www-data /app && chmod -R a-w /app

USER www-data

CMD ["php-fpm"]
