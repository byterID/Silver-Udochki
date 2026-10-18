<?php

declare(strict_types=1);

namespace Tests\Feature\ControlPanel;

use App\Enums\PermissionCode;
use App\Enums\RoleName;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class StockManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function userWithRole(RoleName $role): User
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->syncRoles([$role->value]);

        return $user->fresh();
    }

    public function test_менеджер_видит_склад_и_меняет_остатки(): void
    {
        $manager = $this->userWithRole(RoleName::Manager);
        $product = Product::factory()->create(['name' => 'Мормышка', 'stock' => 4]);

        $this->actingAs($manager)->get(route('control-panel.stock.index'))
            ->assertOk()
            ->assertSee('Мормышка')
            ->assertSee('4 шт.');

        $this->actingAs($manager)
            ->from(route('control-panel.stock.index'))
            ->put(route('control-panel.stock.update', $product), ['reason' => 'restock', 'quantity' => 20])
            ->assertRedirect(route('control-panel.stock.index'))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status');
        $this->assertSame(24, $product->refresh()->stock);

        $this->actingAs($manager)
            ->put(route('control-panel.stock.update', $product), ['reason' => 'inventory', 'quantity' => 0])
            ->assertSessionHasNoErrors();
        $this->assertSame(0, $product->refresh()->stock);

        $movement = StockMovement::query()->latest('id')->firstOrFail();
        $this->assertSame(-24, $movement->delta);
        $this->assertSame($manager->id, $movement->user_id);

        $this->actingAs($manager)->get(route('control-panel.stock.movements', ['product' => $product->id]))
            ->assertOk()
            ->assertSee('Инвентаризация')
            ->assertSee('Поступление')
            ->assertSee($manager->name);
    }

    public function test_фильтр_мало_и_нет_в_наличии(): void
    {
        config(['shop.low_stock' => 10]);
        $admin = $this->userWithRole(RoleName::Admin);
        Product::factory()->create(['name' => 'Полно', 'stock' => 50]);
        Product::factory()->create(['name' => 'Почти', 'stock' => 2]);
        Product::factory()->create(['name' => 'Ноль', 'stock' => 0]);

        // Через данные вида: монитор каталога на каждой странице содержит все товары
        $names = fn (array $query) => $this->actingAs($admin)
            ->get(route('control-panel.stock.index', $query))
            ->assertOk()
            ->viewData('products')
            ->pluck('name')
            ->all();

        $this->assertSame(['Почти'], $names(['state' => 'low']));
        $this->assertSame(['Ноль'], $names(['state' => 'out']));
        $this->assertCount(3, $names([]));
    }

    public function test_приход_должен_быть_положительным(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);
        $product = Product::factory()->create(['stock' => 5]);

        foreach ([['restock', 0], ['restock', -5], ['inventory', -1], ['oops', 5]] as [$reason, $qty]) {
            $this->actingAs($admin)
                ->put(route('control-panel.stock.update', $product), ['reason' => $reason, 'quantity' => $qty])
                ->assertSessionHasErrors();
        }

        $this->assertSame(5, $product->refresh()->stock);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_без_права_на_изменение_только_просмотр(): void
    {
        $manager = $this->userWithRole(RoleName::Manager);
        Role::findByName(RoleName::Manager->value)->revokePermissionTo(PermissionCode::UpdateStock->value);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $product = Product::factory()->create(['stock' => 5]);

        $this->actingAs($manager)->get(route('control-panel.stock.index'))
            ->assertOk()
            ->assertDontSee('+ Приход');

        $this->actingAs($manager)
            ->put(route('control-panel.stock.update', $product), ['reason' => 'restock', 'quantity' => 1])
            ->assertForbidden();
        $this->assertSame(5, $product->refresh()->stock);
    }

    public function test_покупателю_склад_недоступен(): void
    {
        $user = $this->userWithRole(RoleName::User);
        $product = Product::factory()->create();

        $this->actingAs($user)->get(route('control-panel.stock.index'))->assertForbidden();
        $this->actingAs($user)->get(route('control-panel.stock.movements'))->assertForbidden();
        $this->actingAs($user)
            ->put(route('control-panel.stock.update', $product), ['reason' => 'restock', 'quantity' => 1])
            ->assertForbidden();
    }
}
