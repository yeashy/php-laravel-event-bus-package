ARG PHP_VERSION=8.2

FROM php:${PHP_VERSION}-cli-buster
WORKDIR /app
RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime; \
    echo $TZ > /etc/timezone
RUN docker-php-ext-install \
        sockets \
        pcntl
