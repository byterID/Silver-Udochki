FROM php:8.4-fpm

# Системные зависимости для сборки PHP-расширений
RUN apt-get update && apt-get install -y \
    libpq-dev \
    unzip \
    git \
    curl \
    && curl -fsSL https://deb.nodesource.com/setup_22.x | bash - \
    && apt-get install -y nodejs \
    && rm -rf /var/lib/apt/lists/*

# PHP-расширения: pdo_pgsql нужен для работы с Postgres
RUN docker-php-ext-install pdo pdo_pgsql

# Ставим Composer внутрь образа (копируем из официального образа composer)
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

# Копируем код проекта в образ
COPY . .

# Права на storage и bootstrap/cache (иначе Laravel не сможет писать логи и кэш)
RUN chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache

COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 9000
ENTRYPOINT ["entrypoint.sh"]
CMD ["php-fpm"]
