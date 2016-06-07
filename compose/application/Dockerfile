FROM ubuntu:14.04

RUN apt-get update && apt-get install -y \
    git-core \
    curl \
    php5 \
    php5-mysql \
    php5-intl \
    php5-curl \
    nodejs \
    npm

RUN ln -s /usr/bin/nodejs /usr/bin/node

RUN usermod -u 1000 -d /home/www-data www-data
RUN mkdir /home/www-data
RUN chown -R www-data:www-data /home/www-data

RUN curl -sS https://getcomposer.org/installer | php
RUN mv composer.phar /usr/local/bin/composer
RUN chmod +x /usr/local/bin/composer

ADD application.ini /etc/php5/cli/conf.d/

ADD browscap.ini /etc/browscap.ini

RUN npm install -g grunt-cli
RUN npm install -g bower

WORKDIR /code