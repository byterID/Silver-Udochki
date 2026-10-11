#!/bin/sh
# Выгружает каталог (главы, категории, товары) в database/dumps/catalog.sql.
# Только данные: схему создают миграции.
set -eu
cd "$(dirname "$0")/../.."
mkdir -p database/dumps
out=database/dumps/catalog.sql

{
    echo 'BEGIN;'
    echo 'TRUNCATE public.products, public.categories, public.chapters RESTART IDENTITY CASCADE;'
    docker compose exec -T db sh -c 'pg_dump -U "$POSTGRES_USER" -d "$POSTGRES_DB" --data-only --column-inserts --no-owner --no-privileges -t public.chapters -t public.categories -t public.products' < /dev/null
    for t in chapters categories products; do
        echo "SELECT setval(pg_get_serial_sequence('public.$t', 'id'), COALESCE((SELECT MAX(id) FROM public.$t), 1));"
    done
    echo 'COMMIT;'
} > "$out"

echo "Готово: $out"
