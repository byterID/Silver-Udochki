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
# app:migrate-on-boot = migrate --isolated, который не падает на пустой базе (см. команду).
if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
    echo "Запуск миграций..."
    php artisan app:migrate-on-boot
fi

# Непримененные миграции — частая причина «relation ... does not exist» на страницах.
# Контейнер не роняем (в проде миграции идут отдельным шагом деплоя), но говорим громко.
if ! php artisan migrate:status --pending=1 > /dev/null 2>&1; then
    echo "ВНИМАНИЕ: в базе есть непримененные миграции (или база недоступна)." >&2
    echo "Страницы будут падать с ошибкой «relation ... does not exist». Выполните:" >&2
    echo "    docker compose exec app php artisan migrate" >&2
    echo "или задайте RUN_MIGRATIONS=true в .env, чтобы миграции шли при старте." >&2
fi

# Кэширование конфига — явный шаг деплоя, а не догадка по пустой переменной.
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
