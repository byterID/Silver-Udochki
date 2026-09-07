# Silver Udochki

Веб-приложение для рыболовов: каталог, пользователи, панель управления
с гибкой системой ролей и прав.

## Стек

PHP 8.4 · Laravel 13 · PostgreSQL 17 · Docker · Tailwind CSS · Alpine.js
· spatie/laravel-permission

## Быстрый старт

```bash
git clone https://github.com/byterID/Silver-Udochki.git
cd Silver-Udochki

cp .env.example .env

# Задать пароль БД и данные первого администратора
# DB_PASSWORD=$(openssl rand -base64 24)
# ADMIN_EMAIL=..., ADMIN_PASSWORD=...
${EDITOR:-nano} .env

docker compose up -d --build
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --seed
