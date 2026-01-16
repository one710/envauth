FROM php:8.5-apache

# install basic utilities
RUN apt-get update && \
    apt-get install -y curl git zip

# install basic extensions
RUN apt-get install -y libpq-dev libsqlite3-dev libxml2-dev && \
    docker-php-ext-install bcmath ctype exif pcntl pdo_mysql pdo_pgsql pdo_sqlite xml

# install php-curl extension
RUN apt-get install -y libcurl3-dev && \
    docker-php-ext-install curl

# install php-gd extension
RUN apt-get install -y libfreetype6-dev libjpeg62-turbo-dev libpng-dev libwebp-dev && \
    docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp && \
    docker-php-ext-install -j$(nproc) gd

# install php-gmp extension
RUN apt-get install -y libgmp-dev && \
    docker-php-ext-install gmp

# install php-imagick extension
RUN apt-get install -y libmagickwand-dev && \
    git clone https://github.com/Imagick/imagick.git --single-branch /tmp/imagick && \
    cd /tmp/imagick && \
    phpize && \
    ./configure && \
    make && \
    make install && \
    docker-php-ext-enable imagick

# install php-intl extension
RUN apt-get -y install libicu-dev && \
    docker-php-ext-configure intl && \
    docker-php-ext-install intl

# install php-memcache extension
RUN apt-get install -y libmemcached-dev && \
    pecl install memcached && \
    docker-php-ext-enable memcached

# install php-zip extension
RUN apt-get install -y libzip-dev && \
    docker-php-ext-install -j$(nproc) zip

# install mysql client
RUN apt-get install -y default-mysql-client

# clean up
RUN apt-get autoremove -y \
    && rm -rf /var/lib/apt/lists/* \
    && apt-get clean

# install composer
COPY --from=composer /usr/bin/composer /usr/bin/composer

# set consistent uid and gid
RUN usermod -u 1000 www-data && \
    groupmod -g 1000 www-data && \
    usermod --shell /bin/bash www-data

# copy vhost config
COPY .docker/vhost.conf /etc/apache2/sites-available/000-default.conf

# enable rewrite module
RUN a2enmod rewrite

# set working directory
WORKDIR /var/www/html

# install project deps
COPY composer.json composer.lock ./

# install composer deps
RUN composer install --no-autoloader --no-dev --no-interaction --no-scripts

# copy project files
COPY . .

# generate autoload files
RUN composer dump-autoload --optimize

# setup right permissions
RUN chgrp -R www-data storage && \
    chmod -R ug+rw storage
