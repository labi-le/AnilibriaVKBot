FROM php:8-fpm-alpine

WORKDIR /var/www/

RUN apk update && apk add \
    build-base

RUN apk add libzip-dev curl-dev libxml2-dev
RUN apk add --update composer

RUN docker-php-ext-install curl xml zip pcntl sockets

RUN addgroup -S appgroup && adduser -S appuser -G appgroup

COPY . .
RUN composer install --no-dev --classmap-authoritative

RUN chown appuser . -R