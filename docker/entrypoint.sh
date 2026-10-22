#!/bin/sh
set -eu

# Ключ при старте не генерируем: новый ключ на каждый перезапуск ломает сессии и шифрованные данные
if [ ! -f .env ]; then
    echo "ОШИБКА: файл .env отсутствует. Скопируйте .env.example и заполните его." >&2
    exit 1
fi

# migrate --isolated, который не падает на пустой базе (см. MigrateOnBootCommand)
if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
    echo "Запуск миграций..."
    php artisan app:migrate-on-boot
fi

# Контейнер не роняем: в проде миграции идут отдельным шагом деплоя
if ! php artisan migrate:status --pending=1 > /dev/null 2>&1; then
    echo "ВНИМАНИЕ: в базе есть непримененные миграции (или база недоступна)." >&2
    echo "Страницы будут падать с ошибкой «relation ... does not exist». Выполните:" >&2
    echo "    docker compose exec app php artisan migrate" >&2
    echo "или задайте RUN_MIGRATIONS=true в .env, чтобы миграции шли при старте." >&2
fi

if [ "${OPTIMIZE_ON_BOOT:-false}" = "true" ]; then
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
    php artisan event:cache
else
    php artisan config:clear || true
    php artisan route:clear || true
    php artisan view:clear || true
fi

exec "$@"
