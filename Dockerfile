# syntax=docker/dockerfile:1

################################################################################
# Stage 1 — Frontend assets (Vite / Tailwind / Alpine)
################################################################################
FROM node:20-alpine AS assets

WORKDIR /app
COPY package.json package-lock.json* ./
RUN npm ci
COPY resources ./resources
COPY vite.config.js tailwind.config.js postcss.config.js ./
RUN npm run build

################################################################################
# Stage 2 — PHP dependencies (Composer)
################################################################################
FROM composer:2 AS vendor

# filament/support requires ext-intl; the composer:2 base image doesn't ship it.
RUN apk add --no-cache icu-dev g++ make autoconf \
    && docker-php-ext-install intl \
    && apk del g++ make autoconf

WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-scripts \
    --no-autoloader \
    --prefer-dist \
    --no-interaction

################################################################################
# Stage 3 — Application runtime (PHP-FPM + Nginx vía Supervisor)
################################################################################
FROM php:8.4-fpm AS app

# System dependencies + PHP extensions + Nginx + Supervisor.
RUN apt-get update && apt-get install -y \
        git curl zip unzip libpng-dev libjpeg-dev \
        libfreetype6-dev libonig-dev libxml2-dev libzip-dev \
        libicu-dev nginx supervisor \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip opcache intl \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && rm -rf /var/lib/apt/lists/* \
    && rm -f /etc/nginx/sites-enabled/default \
    && mkdir -p /run/php

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# PHP-FPM escucha por socket unix en vez de puerto TCP — override en archivo
# aparte (se carga después de www.conf y gana), no depende del formato exacto
# de la config por defecto de la imagen base.
COPY docker-fpm-setup/zz-socket.conf /usr/local/etc/php-fpm.d/zz-socket.conf

COPY docker-fpm-setup/nginx.conf /etc/nginx/sites-enabled/app.conf
COPY docker-fpm-setup/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

WORKDIR /var/www/html

# Copy the application, then the pre-built vendor and asset artifacts.
COPY . .
COPY --from=vendor /app/vendor ./vendor
COPY --from=assets /app/public/build ./public/build

# Finish the autoloader and optimize now that all source is present.
RUN composer dump-autoload --no-dev --optimize \
    && chown -R www-data:www-data storage bootstrap/cache

EXPOSE 8000
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
