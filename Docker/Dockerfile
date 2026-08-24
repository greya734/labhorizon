# syntax=docker/dockerfile:1

##############################
# 1) Build des assets front (Vite / Tailwind / Alpine)
##############################
FROM node:20-alpine AS node-build

WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci

# On a besoin des vues Blade/resources pour que Vite/Tailwind scanne les classes utilisées
COPY resources ./resources
COPY vite.config.* ./
COPY tailwind.config.* ./
COPY postcss.config.* ./

RUN npm run build

##############################
# 2) Dépendances PHP (Composer)
##############################
FROM composer:2 AS composer-build

WORKDIR /app

COPY composer.json composer.lock ./

# Installe les dépendances sans exécuter les scripts artisan (pas encore de code app copié)
RUN composer install \
    --no-dev \
    --no-scripts \
    --no-autoloader \
    --prefer-dist \
    --no-interaction

COPY . .

RUN composer dump-autoload --optimize --no-dev \
    && composer run-script post-autoload-dump --no-interaction || true

##############################
# 3) Image finale PHP-FPM + Nginx
##############################
FROM php:8.2-fpm-alpine AS final

# Dépendances système + extensions PHP requises par Laravel 12 / Breeze / Sanctum / smalot-pdfparser / Redis
RUN apk add --no-cache \
        nginx \
        supervisor \
        bash \
        curl \
        libzip-dev \
        libpng-dev \
        libjpeg-turbo-dev \
        freetype-dev \
        icu-dev \
        oniguruma-dev \
        mysql-client \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo_mysql \
        mbstring \
        exif \
        pcntl \
        bcmath \
        gd \
        zip \
        intl \
        opcache \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps

# Config OPcache recommandée en prod
RUN { \
    echo 'opcache.enable=1'; \
    echo 'opcache.memory_consumption=128'; \
    echo 'opcache.interned_strings_buffer=8'; \
    echo 'opcache.max_accelerated_files=10000'; \
    echo 'opcache.validate_timestamps=0'; \
    } > /usr/local/etc/php/conf.d/opcache-recommended.ini

WORKDIR /var/www/html

# Code applicatif + vendor (depuis le stage composer-build)
COPY --from=composer-build /app /var/www/html

# Assets compilés (public/build) depuis le stage node-build
COPY --from=node-build /app/public/build /var/www/html/public/build

# Config Nginx (sert public/, proxy PHP-FPM sur 9000)
COPY docker/nginx.conf /etc/nginx/http.d/default.conf
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# Permissions storage / cache (nécessaires à Laravel)
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

EXPOSE 80

ENTRYPOINT ["entrypoint.sh"]
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
