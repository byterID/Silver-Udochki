#!/bin/sh
set -e

if [ ! -f .env ]; then
    cp .env.example .env
fi

if ! grep -q "^APP_KEY=base64" .env; then
    php artisan key:generate --force
fi

until php artisan migrate --force 2>/dev/null; do
    echo "Waiting for database..."
    sleep 2
done

exec "$@"
