FROM php:7.0-cli
# in 5.6 problem with ssl connection

RUN apt-get update && apt-get install -y \
    libcurl4-openssl-dev \
    curl \
    git \
    libicu-dev \
    libgmp-dev \
    libssl-dev \
    libjpeg-dev \
    libpng-dev \
    libfreetype6-dev \
    libsqlite3-dev \
    nodejs \
    npm \
    && ln -s /usr/include/x86_64-linux-gnu/gmp.h /usr/include/gmp.h \
    && docker-php-ext-configure intl \
    && docker-php-ext-install -j$(nproc) zip intl curl bcmath gmp pdo pdo_mysql mbstring \
    && rm -r /var/lib/apt/lists/*

RUN ln -s /usr/bin/nodejs /usr/bin/node

RUN npm install -g grunt-cli
RUN npm install -g bower

RUN usermod -u 1000 -d /home/www-data www-data
RUN mkdir /home/www-data
RUN chown -R www-data:www-data /home/www-data

RUN curl -sS https://getcomposer.org/installer | php
RUN mv composer.phar /usr/local/bin/composer
RUN chmod +x /usr/local/bin/composer

ADD application.ini /usr/local/etc/php/conf.d/
ADD browscap.ini /etc/browscap.ini

WORKDIR /code