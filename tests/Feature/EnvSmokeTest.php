<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EnvSmokeTest extends TestCase
{
    /** Локально — sqlite, в CI — pgsql. Больше ничего не ждём. */
    private const ALLOWED_DRIVERS = ['sqlite', 'pgsql'];

    /** RefreshDatabase сотрёт всё, что найдёт: рабочие базы под запретом. */
    private const FORBIDDEN_DATABASES = ['silver_udochki'];

    public function test_конфиг_не_закэширован(): void
    {
        $this->assertFalse(
            app()->configurationIsCached(),
            'Конфиг закэширован, значения из phpunit.xml игнорируются. Выполните php artisan config:clear',
        );
    }

    public function test_окружение_тестовое(): void
    {
        $this->assertSame('testing', app()->environment());
        $this->assertTrue(app()->runningUnitTests());
    }

    public function test_драйвер_бд_разрешён(): void
    {
        $this->assertContains(
            config('database.default'),
            self::ALLOWED_DRIVERS,
            'Тесты запущены на неожидаемом драйвере БД',
        );
    }

    public function test_рабочая_база_не_используется(): void
    {
        $database = DB::connection()->getDatabaseName();

        foreach (self::FORBIDDEN_DATABASES as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                (string) $database,
                "Тесты подключены к базе {$database} — RefreshDatabase уничтожит данные",
            );
        }
    }

    public function test_побочные_эффекты_отключены(): void
    {
        $this->assertSame('array', config('cache.default'));
        $this->assertSame('array', config('session.driver'));
        $this->assertSame('array', config('mail.default'));
        $this->assertSame('sync', config('queue.default'));
    }
}
