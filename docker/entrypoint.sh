#!/bin/sh
set -eu

# .env и APP_KEY готовятся заранее (локально — вручную, в проде — секретами
# оркестратора). Генерировать ключ при старте нельзя: он меняется на каждый
# перезапуск и делает нечитаемыми сессии и зашифрованные данные.
if [ ! -f .env ]; then
    echo "ОШИБКА: файл .env отсутствует. Скопируйте .env.example и заполните его." >&2
    exit 1
fi

# Миграции — осознанный шаг, а не побочный эффект запуска контейнера.
if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
    echo "Запуск миграций..."
    php artisan migrate --force --isolated
fi

if [ "${APP_ENV:-production}" = "production" ]; then
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
    php artisan event:cache
else
    php artisan optimize:clear
fi

exec "$@"
