FROM php:8.2

# composer
COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer
RUN apt-get update && apt-get install -y zlib1g-dev libzip-dev zip unzip
RUN docker-php-ext-install zip

# php
COPY docker/php/*.ini $PHP_INI_DIR/conf.d/
RUN ln -s $PHP_INI_DIR/php.ini-development $PHP_INI_DIR/php.ini
WORKDIR /usr/src/marble

# xdebug
RUN pecl install xdebug && docker-php-ext-enable xdebug
