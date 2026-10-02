# Test runner. The engine itself needs nothing but PHP; pdo_mysql is here for the
# integration suite and pcov for the coverage gate.
FROM php:8.2-cli

RUN apt-get update \
    && apt-get install -y --no-install-recommends $PHPIZE_DEPS unzip git \
    && docker-php-ext-install -j"$(nproc)" pdo_mysql \
    && pecl install pcov \
    && docker-php-ext-enable pcov \
    && apt-get purge -y --auto-remove $PHPIZE_DEPS \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app
