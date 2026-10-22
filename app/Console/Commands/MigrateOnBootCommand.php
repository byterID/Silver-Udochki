<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

// migrate --isolated берёт блокировку в кеше, а при CACHE_STORE=database таблицы cache_locks
// на пустой базе ещё нет, и он падает. Поэтому сначала создаём таблицы кеша.
// Второй одновременно стартующий контейнер может упасть здесь: restart его поднимет
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
