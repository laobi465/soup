FROM php:8.2-fpm-alpine

# I6: Alpine mirror 可配（默认官方源，国内构建用 --build-arg ALPINE_MIRROR=mirrors.aliyun.com）
ARG ALPINE_MIRROR=dl-cdn.alpinelinux.org
RUN if [ "$ALPINE_MIRROR" != "dl-cdn.alpinelinux.org" ]; then \
        sed -i "s|dl-cdn.alpinelinux.org|${ALPINE_MIRROR}|g" /etc/apk/repositories; \
    fi

RUN apk add --no-cache \
    freetype \
    libjpeg-turbo \
    libpng \
    libzip \
    oniguruma \
    icu \
    curl \
    git \
    unzip

RUN apk add --no-cache --virtual .build-deps \
    freetype-dev \
    libjpeg-turbo-dev \
    libpng-dev \
    libzip-dev \
    oniguruma-dev \
    icu-dev \
    curl-dev \
    $PHPIZE_DEPS

RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
    gd \
    pdo_mysql \
    zip \
    mbstring \
    intl \
    pcntl \
    bcmath \
    opcache

RUN pecl install redis \
    && docker-php-ext-enable redis

RUN apk del .build-deps

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

COPY php.ini /usr/local/etc/php/php.ini

WORKDIR /var/www/server

RUN addgroup -g 1000 www \
    && adduser -G www -g www -s /bin/sh -D www

USER www
