# Этап 1: сборка фронтенда
FROM node:22-alpine AS frontend

WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci

COPY vite.config.js tailwind.config.js postcss.config.js ./
COPY resources ./resources
RUN npm run build


# Этап 2: база с PHP и расширениями
FROM php:8.4-fpm-alpine AS base

RUN apk add --no-cache postgresql-libs icu-libs librdkafka \
    && apk add --no-cache --virtual .build-deps \
        postgresql-dev icu-dev librdkafka-dev $PHPIZE_DEPS \
    && docker-php-ext-install -j"$(nproc)" pdo_pgsql intl opcache pcntl \
    && pecl install rdkafka \
    && docker-php-ext-enable rdkafka \
    && apk del .build-deps

WORKDIR /var/www


# Этап 3: зависимости PHP
FROM base AS vendor

RUN apk add --no-cache unzip git

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Сначала только манифесты: слой зависимостей не пересобирается при правке кода
COPY composer.json composer.lock ./
RUN composer install \
        --no-dev \
        --no-scripts \
        --no-autoloader \
        --prefer-dist \
        --no-interaction

# Код нужен composer'у для карты классов
COPY . .
RUN composer dump-autoload --optimize --no-dev --no-scripts --no-interaction


# Этап 4: рантайм
FROM base AS runtime

COPY docker/php/opcache.ini /usr/local/etc/php/conf.d/opcache.ini
COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-app.ini

COPY . .
COPY --from=vendor /var/www/vendor ./vendor
COPY --from=frontend /app/public/build ./public/build

RUN chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R ug+rwX storage bootstrap/cache

COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 9000
ENTRYPOINT ["entrypoint.sh"]
CMD ["php-fpm"]

# Этап 5: nginx со статикой
FROM nginx:stable-alpine AS web

COPY --from=runtime /var/www/public /var/www/public

# Аналог storage:link: сами файлы приходят через общий том
RUN ln -sfn ../storage/app/public /var/www/public/storage