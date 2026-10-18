<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\PermissionCode;
use App\Enums\RoleName;
use App\Enums\StockReason;
use App\Enums\TaskType;
use App\Models\ActionGroup;
use App\Models\Category;
use App\Models\Chapter;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Task;
use App\Models\User;
use App\Tasks\StockReportRunner;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Junges\Kafka\Facades\Kafka;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/** Отчёт об остатках: право на запуск и содержимое CSV. Kafka не трогаем, раннер зовём напрямую. */
class StockReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        Kafka::fake();
        Storage::fake('local');
    }

    private function userWithRole(RoleName $role): User
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->syncRoles([$role->value]);

        return $user->fresh();
    }

    /** @param array<string, mixed> $payload */
    private function reportFor(User $user, array $payload = []): Task
    {
        return $user->tasks()->create(['type' => TaskType::StockReport, 'payload' => $payload]);
    }

    /** @return list<list<string>> */
    private function csv(string $path): array
    {
        $content = Storage::disk('local')->get($path);
        $this->assertStringStartsWith("\xEF\xBB\xBF", $content);

        $lines = explode("\n", rtrim(substr($content, 3), "\n"));

        return array_map(fn (string $line) => str_getcsv($line, ';', '"', ''), $lines);
    }

    private function move(Product $product, StockReason $reason, int $delta, string $at): void
    {
        DB::table('stock_movements')->insert([
            'product_id' => $product->id, 'sku' => $product->sku, 'name' => $product->name,
            'delta' => $delta, 'stock_after' => max(0, $product->stock + $delta),
            'reason' => $reason->value, 'created_at' => $at,
        ]);
    }

    public function test_менеджер_запускает_отчёт_через_kafka(): void
    {
        $manager = $this->userWithRole(RoleName::Manager);

        $this->actingAs($manager)
            ->postJson(route('tasks.store'), ['type' => 'stock_report', 'payload' => ['state' => 'low']])
            ->assertStatus(202);

        $task = Task::query()->sole();
        $this->assertSame(TaskType::StockReport, $task->type);
        $this->assertSame(['state' => 'low'], $task->payload);
        Kafka::assertPublishedOn('tasks.requested');
    }

    public function test_покупателю_отчёт_недоступен(): void
    {
        $user = $this->userWithRole(RoleName::User);

        $this->actingAs($user)->post(route('tasks.store'), ['type' => 'stock_report'])->assertForbidden();
        $this->actingAs($user)->get(route('tasks.index'))->assertOk()->assertDontSee('Отчёт об остатках склада');

        $this->assertDatabaseCount('tasks', 0);
        Kafka::assertNothingPublished();
    }

    public function test_право_в_отдельной_группе_и_без_него_кнопки_нет(): void
    {
        $permission = Permission::query()->where('name', PermissionCode::CreateStockReport->value)->sole();
        $this->assertSame('Создание отчёта остатков', $permission->actionGroup()->value('name'));

        $manager = $this->userWithRole(RoleName::Manager);
        $this->actingAs($manager)->get(route('control-panel.stock.index'))->assertSee('Отчёт в CSV');

        Role::findByName(RoleName::Manager->value)->revokePermissionTo(PermissionCode::CreateStockReport->value);
        $manager = $manager->fresh();

        $this->actingAs($manager)->get(route('control-panel.stock.index'))->assertOk()->assertDontSee('Отчёт в CSV');
        $this->actingAs($manager)->post(route('tasks.store'), ['type' => 'stock_report'])->assertForbidden();
    }

    public function test_неверный_фильтр_отклоняется(): void
    {
        $this->actingAs($this->userWithRole(RoleName::Admin))
            ->postJson(route('tasks.store'), ['type' => 'stock_report', 'payload' => ['state' => 'all']])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('payload.state');
    }

    public function test_отчёт_содержит_остатки_стоимость_и_движение(): void
    {
        config(['shop.low_stock' => 10]);
        $this->travelTo('2026-10-19 12:00:00');

        $chapter = Chapter::factory()->create(['title' => 'Рыбалка', 'sort' => 1]);
        $rods = Category::factory()->for($chapter)->create(['title' => 'Удилища', 'sort' => 1]);
        $hooks = Category::factory()->for($chapter)->create(['title' => 'Крючки', 'sort' => 2]);

        // Создаём «вразнобой», чтобы проверить порядок: раздел → категория → id
        Product::factory()->for($hooks)->create(['sku' => 'H-1', 'name' => 'Крючок; №6', 'price' => 50, 'stock' => 0]);
        $rod = Product::factory()->for($rods)->create(['sku' => 'R-1', 'name' => 'Спиннинг', 'price' => 3000, 'stock' => 4]);
        Product::factory()->for($rods)->create(['sku' => 'R-2', 'name' => 'Фидер', 'price' => 2000, 'stock' => 25, 'is_active' => false]);

        $this->move($rod, StockReason::Sale, -3, '2026-10-10 10:00:00');
        $this->move($rod, StockReason::Sale, -2, '2026-10-18 10:00:00');
        $this->move($rod, StockReason::Returned, 1, '2026-10-18 11:00:00');
        $this->move($rod, StockReason::Sale, -7, '2026-09-01 10:00:00');   // старше 30 дней — не считается
        $this->move($rod, StockReason::Restock, 50, '2026-10-15 10:00:00'); // приход — не продажа

        $task = $this->reportFor($this->userWithRole(RoleName::Manager));
        $rows = $this->csv(app(StockReportRunner::class)->run($task));

        $this->assertSame(['Артикул', 'Товар', 'Раздел', 'Категория', 'В продаже', 'Цена, ₽', 'Остаток, шт.',
            'Стоимость остатка, ₽', 'Состояние', 'Продано за 30 дн., шт.', 'Возвращено за 30 дн., шт.'], $rows[0]);
        $this->assertSame(['R-1', 'Спиннинг', 'Рыбалка', 'Удилища', 'да', '3000', '4', '12000', 'Осталось мало', '5', '1'], $rows[1]);
        $this->assertSame(['R-2', 'Фидер', 'Рыбалка', 'Удилища', 'нет', '2000', '25', '50000', 'В наличии', '0', '0'], $rows[2]);
        $this->assertSame(['H-1', 'Крючок; №6', 'Рыбалка', 'Крючки', 'да', '50', '0', '0', 'Нет в наличии', '0', '0'], $rows[3]);
        $this->assertSame(['Итого позиций', '3', '', '', '', '', '29', '62000'], $rows[5]);
        $this->assertSame(['Отбор', 'весь склад'], $rows[6]);
        $this->assertSame(['Сформирован', '19.10.2026 12:00:00 UTC'], $rows[7]);
    }

    public function test_фильтр_мало_сужает_отчёт(): void
    {
        config(['shop.low_stock' => 10]);
        Product::factory()->create(['sku' => 'LOW', 'stock' => 3]);
        Product::factory()->create(['sku' => 'OUT', 'stock' => 0]);
        Product::factory()->create(['sku' => 'MANY', 'stock' => 90]);

        $task = $this->reportFor($this->userWithRole(RoleName::Admin), ['state' => 'low']);
        $rows = $this->csv(app(StockReportRunner::class)->run($task));

        $this->assertSame(['LOW'], array_column(array_slice($rows, 1, -4), 0));
        $this->assertSame(['Отбор', 'Осталось мало'], $rows[array_key_last($rows) - 1]);
    }

    public function test_воркер_не_выполняет_отчёт_если_право_отозвали(): void
    {
        $manager = $this->userWithRole(RoleName::Manager);
        $task = $this->reportFor($manager);

        // Права уже в памяти процесса, как у долгоживущего воркера
        $this->assertTrue($manager->can(PermissionCode::CreateStockReport->value));

        // Отзыв «из веба»: другой процесс меняет БД и чистит общий кеш, а копию воркера не трогает
        DB::table(config('permission.table_names.role_has_permissions'))
            ->where('role_id', Role::findByName(RoleName::Manager->value)->id)
            ->where('permission_id', Permission::query()->where('name', PermissionCode::CreateStockReport->value)->value('id'))
            ->delete();
        app(PermissionRegistrar::class)->getCacheRepository()->forget(config('permission.cache.key'));

        $this->expectException(AuthorizationException::class);
        app(StockReportRunner::class)->run($task->fresh());
    }

    public function test_миграция_выдаёт_право_админу_и_менеджеру(): void
    {
        Permission::query()->where('name', PermissionCode::CreateStockReport->value)->delete();
        ActionGroup::query()->where('name', 'Создание отчёта остатков')->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $migration = require database_path('migrations/2026_10_19_150000_add_stock_report_permission.php');
        $migration->up();
        $migration->up(); // повторный запуск ничего не ломает

        foreach ([RoleName::Admin, RoleName::Manager] as $role) {
            $this->assertTrue(Role::findByName($role->value)->hasPermissionTo(PermissionCode::CreateStockReport->value));
        }
        $this->assertFalse(Role::findByName(RoleName::User->value)->hasPermissionTo(PermissionCode::CreateStockReport->value));
        $this->assertSame(1, ActionGroup::query()->where('name', 'Создание отчёта остатков')->count());
    }
}
