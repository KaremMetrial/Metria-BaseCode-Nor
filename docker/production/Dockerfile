# syntax=docker/dockerfile:1
FROM php:8.5-fpm-alpine AS php-base
RUN apk add --no-cache icu-libs icu-data-full libzip oniguruma \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS icu-dev libzip-dev oniguruma-dev \
    && docker-php-ext-install -j2 pdo_mysql bcmath pcntl intl mbstring zip \
    && apk del .build-deps
WORKDIR /var/www/html

FROM php-base AS dependencies
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
ENV COMPOSER_ALLOW_SUPERUSER=1
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction --no-progress

FROM node:22-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci --ignore-scripts
COPY resources ./resources
COPY vite.config.js ./
COPY --from=dependencies /var/www/html/vendor/laravel/framework/src/Illuminate/Pagination/resources/views ./vendor/laravel/framework/src/Illuminate/Pagination/resources/views
RUN npm run build

FROM dependencies AS application-build
COPY . .
COPY --from=assets /app/public/build ./public/build
RUN mkdir -p bootstrap/cache storage/app/private storage/app/public storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs \
    && composer dump-autoload --no-dev --classmap-authoritative --no-scripts \
    && APP_ENV=production LOG_CHANNEL=stderr php artisan package:discover --no-interaction

FROM php-base AS app
ENV APP_ENV=production APP_DEBUG=false LOG_CHANNEL=stderr
COPY --from=application-build --chown=www-data:www-data /var/www/html /var/www/html
COPY docker/production/php.ini /usr/local/etc/php/conf.d/production.ini
COPY docker/php/zz-docker.conf /usr/local/etc/php-fpm.d/zz-docker.conf
COPY --chmod=755 docker/production/entrypoint.sh /usr/local/bin/metrial-entrypoint
USER www-data
EXPOSE 9000
ENTRYPOINT ["metrial-entrypoint"]
CMD ["php-fpm", "-F"]

FROM nginx:alpine AS web
COPY docker/production/nginx.conf /etc/nginx/nginx.conf
COPY --from=application-build /var/www/html/public /var/www/html/public
USER nginx
EXPOSE 8080
ENTRYPOINT ["nginx", "-g", "daemon off;"]
HEALTHCHECK --interval=10s --timeout=3s --start-period=15s --retries=6 CMD wget -q -O /dev/null http://127.0.0.1:8080/up || exit 1
