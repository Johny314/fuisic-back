FROM php:8.3-fpm

COPY ./docker/app/php.ini $PHP_INI_DIR/php.ini

RUN apt-get -y update \
    && apt-get install -y libfreetype6-dev libpq-dev libicu-dev libjpeg62-turbo-dev libpng-dev libxml2-dev libgmp-dev libzip-dev zip unzip curl git \
    && php -r "readfile('https://getcomposer.org/installer');" | php -- --install-dir=/usr/bin/ --filename=composer \
    && pecl install redis \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) iconv gd pdo_pgsql bcmath gmp intl opcache zip pcntl sockets \
    && docker-php-ext-enable redis

WORKDIR /var/www/html

EXPOSE 9000

CMD ["php-fpm"]
