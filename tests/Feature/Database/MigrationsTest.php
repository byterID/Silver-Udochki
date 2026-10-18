<?php

declare(strict_types=1);

namespace Tests\Feature\Database;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Без RefreshDatabase: каждый тест начинает с пустой базы, как свежий контейнер.
 * В CI те же тесты идут на PostgreSQL, где база живёт между тестами, поэтому
 * перед тестом её стираем, а после — оставляем чистой и мигрированной для остальных.
 */
class MigrationsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Artisan::call('db:wipe', ['--force' => true]);
    }

    protected function tearDown(): void
    {
        config(['cache.default' => 'array']);
        Artisan::call('migrate:fresh', ['--force' => true]);
        parent::tearDown();
    }

    /** Таблицы, без которых витрина и панель падают с «relation ... does not exist». */
    private const REQUIRED_TABLES = [
        'users', 'cache', 'cache_locks', 'sessions', 'roles', 'permissions', 'action_groups',
        'tasks', 'notifications', 'chapters', 'categories', 'products',
        'promotions', 'promotion_product', 'orders', 'order_items', 'stock_movements',
        'wallets', 'wallet_transactions',
    ];

    public function test_старт_контейнера_мигрирует_пустую_базу_при_кеше_в_бд(): void
    {
        // Как в .env.example: CACHE_STORE=database, а таблицы cache_locks ещё нет
        config(['cache.default' => 'database']);
        $this->assertFalse(Schema::hasTable('cache_locks'));

        $this->assertSame(0, Artisan::call('app:migrate-on-boot'), Artisan::output());

        foreach (self::REQUIRED_TABLES as $table) {
            $this->assertTrue(Schema::hasTable($table), "Нет таблицы {$table}");
        }
        $this->assertSame(0, Artisan::call('migrate:status', ['--pending' => 1]), 'Остались непримененные миграции');

        // Повторный старт ничего не ломает
        $this->assertSame(0, Artisan::call('app:migrate-on-boot'), Artisan::output());
    }

    public function test_голый_migrate_isolated_на_пустой_базе_падает(): void
    {
        // Ради этого и нужна app:migrate-on-boot. Если Laravel начнёт справляться сам,
        // тест упадёт — тогда команду можно упростить.
        config(['cache.default' => 'database']);

        $this->expectException(QueryException::class);
        Artisan::call('migrate', ['--force' => true, '--isolated' => true]);
    }

    public function test_пустая_база_видна_как_непримененные_миграции(): void
    {
        // Эту проверку делает docker/entrypoint.sh, чтобы предупредить в логах
        $this->assertNotSame(0, Artisan::call('migrate:status', ['--pending' => 1]));

        Artisan::call('migrate', ['--force' => true]);

        $this->assertSame(0, Artisan::call('migrate:status', ['--pending' => 1]));
    }

    public function test_миграции_откатываются_и_накатываются_заново(): void
    {
        $this->assertSame(0, Artisan::call('migrate', ['--force' => true]), Artisan::output());
        $this->assertSame(0, Artisan::call('migrate:reset', ['--force' => true]), Artisan::output());

        foreach (self::REQUIRED_TABLES as $table) {
            $this->assertFalse(Schema::hasTable($table), "down() не удалил таблицу {$table}");
        }

        $this->assertSame(0, Artisan::call('migrate', ['--force' => true]), Artisan::output());
        $this->assertTrue(Schema::hasTable('promotions'));
    }
}
