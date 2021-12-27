FROM php:8

# composer
RUN apt-get update && apt-get install -y zlib1g-dev libzip-dev zip unzip
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
RUN docker-php-ext-install zip
RUN mkdir /var/www/.composer && chown -R www-data:www-data /var/www/.composer

# php
COPY docker/php/*.ini $PHP_INI_DIR/conf.d/
RUN ln -s $PHP_INI_DIR/php.ini-development $PHP_INI_DIR/php.ini
RUN docker-php-ext-enable opcache
RUN apt-get update && apt-get install -y libpq5 libpq-dev && docker-php-ext-install -j$(nproc) pdo_pgsql
WORKDIR /usr/src/marble

# xdebug
RUN pecl install xdebug && docker-php-ext-enable xdebug
