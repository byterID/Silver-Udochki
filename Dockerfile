FROM php:8.4-fpm

# Системные зависимости для сборки PHP-расширений
RUN apt-get update && apt-get install -y \
    libpq-dev \
    unzip \
    git \
    && rm -rf /var/lib/apt/lists/*

# PHP-расширения: pdo_pgsql нужен для работы с Postgres
RUN docker-php-ext-install pdo pdo_pgsql

# Ставим Composer внутрь образа (копируем из официального образа composer)
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

# Копируем код проекта в образ
COPY . .

# Ставим зависимости проекта
RUN composer install --no-interaction --optimize-autoloader

# PHP-FPM слушает порт 9000 внутри контейнера
EXPOSE 9000
CMD ["php-fpm"]
