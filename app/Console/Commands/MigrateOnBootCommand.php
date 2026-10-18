<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

/**
 * Миграции при старте контейнера (RUN_MIGRATIONS=true в docker/entrypoint.sh).
 *
 * `migrate --isolated` держит блокировку в кеше, чтобы два контейнера не мигрировали разом.
 * При CACHE_STORE=database блокировка живёт в таблице cache_locks, которой на пустой базе
 * ещё нет, и голый `migrate --isolated` падает. Поэтому сначала создаём таблицы кеша.
 * Если два контейнера впервые стартуют одновременно, второй может упасть на этом шаге:
 * restart: unless-stopped поднимет его, и он уже найдёт таблицы на месте.
 */
class MigrateOnBootCommand extends Command
{
    public const CACHE_MIGRATION = 'database/migrations/0001_01_01_000001_create_cache_table.php';

    protected $signature = 'app:migrate-on-boot';

    protected $description = 'Применить миграции при старте контейнера (безопасно для пустой базы)';

    public function handle(): int
    {
        if ($this->lockNeedsCacheTables()) {
            $this->components->info('Таблиц кеша ещё нет: создаю их до блокировки.');

            $code = $this->call('migrate', ['--force' => true, '--path' => self::CACHE_MIGRATION]);
            if ($code !== self::SUCCESS) {
                return $code;
            }
        }

        return $this->call('migrate', ['--force' => true, '--isolated' => true]);
    }

    private function lockNeedsCacheTables(): bool
    {
        /** @var array{driver?: string, connection?: string|null, lock_connection?: string|null, lock_table?: string|null} $store */
        $store = config('cache.stores.'.config('cache.default'), []);

        if (($store['driver'] ?? null) !== 'database') {
            return false;
        }

        $connection = $store['lock_connection'] ?? $store['connection'] ?? null;

        return ! Schema::connection($connection)->hasTable($store['lock_table'] ?? 'cache_locks');
    }
}
