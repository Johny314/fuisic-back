FROM php:8.4-fpm

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
RUN cp "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini"
COPY ./docker/app/app.ini $PHP_INI_DIR/conf.d/zz-app.ini

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        libfreetype6-dev libjpeg62-turbo-dev libpng-dev libpq-dev libicu-dev libgmp-dev libzip-dev unzip git \
    && pecl install redis \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" gd pdo_pgsql bcmath gmp intl opcache zip pcntl sockets \
    && docker-php-ext-enable redis \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* /tmp/pear

ENV COMPOSER_HOME=/tmp/composer

WORKDIR /var/www/html

EXPOSE 9000

CMD ["php-fpm"]
