#!/bin/sh
# Загружает database/dumps/catalog.sql в базу. Миграции должны быть уже применены.
set -eu
cd "$(dirname "$0")/../.."

if [ ! -f database/dumps/catalog.sql ]; then
    echo "Нет файла database/dumps/catalog.sql — сначала запустите docker/db/catalog-dump.sh" >&2
    exit 1
fi

docker compose exec -T db sh -c 'psql -v ON_ERROR_STOP=1 -q -U "$POSTGRES_USER" -d "$POSTGRES_DB"' < database/dumps/catalog.sql
echo "Каталог восстановлен"
