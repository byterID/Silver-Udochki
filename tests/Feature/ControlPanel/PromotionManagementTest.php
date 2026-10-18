<?php

declare(strict_types=1);

namespace Tests\Feature\ControlPanel;

use App\Enums\PermissionCode;
use App\Enums\PromotionType;
use App\Enums\RoleName;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class PromotionManagementTest extends TestCase
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

    /** @return array<string, mixed> */
    private function percentPayload(array $productIds, array $overrides = []): array
    {
        return [
            'title' => '−15% на блёсны',
            'note' => 'до воскресенья',
            'type' => 'percent',
            'value' => 15,
            'color' => '#FDE68A',
            'is_active' => '1',
            'products' => $productIds,
            ...$overrides,
        ];
    }

    public function test_менеджер_по_умолчанию_управляет_акциями(): void
    {
        $manager = $this->userWithRole(RoleName::Manager);
        $products = Product::factory()->count(2)->create();

        $this->actingAs($manager)->get(route('control-panel.promotions.index'))->assertOk();
        $this->actingAs($manager)->get(route('control-panel.promotions.create'))->assertOk();

        $this->actingAs($manager)
            ->post(route('control-panel.promotions.store'), $this->percentPayload($products->pluck('id')->all()))
            ->assertRedirect(route('control-panel.promotions.index'))
            ->assertSessionHasNoErrors();

        $promotion = Promotion::firstOrFail();
        $this->assertSame(PromotionType::Percent, $promotion->type);
        $this->assertSame('#fde68a', $promotion->color);
        $this->assertEqualsCanonicalizing($products->pluck('id')->all(), $promotion->products->pluck('id')->all());

        $this->actingAs($manager)->get(route('control-panel.promotions.edit', $promotion))
            ->assertOk()
            ->assertSee($products[0]->name);

        $this->actingAs($manager)
            ->delete(route('control-panel.promotions.destroy', $promotion))
            ->assertRedirect(route('control-panel.promotions.index'));

        $this->assertModelMissing($promotion);
        $this->assertSame(0, DB::table('promotion_product')->count());
    }

    public function test_покупателю_акции_недоступны(): void
    {
        $user = $this->userWithRole(RoleName::User);
        $promotion = Promotion::factory()->create();

        $this->actingAs($user)->get(route('control-panel.promotions.index'))->assertForbidden();
        $this->actingAs($user)->post(route('control-panel.promotions.store'), [])->assertForbidden();
        $this->actingAs($user)->delete(route('control-panel.promotions.destroy', $promotion))->assertForbidden();
        $this->assertModelExists($promotion);
    }

    public function test_доступ_решают_права_а_не_роль(): void
    {
        // Админ отнял у менеджеров право удалять акции
        Role::findByName(RoleName::Manager->value)->revokePermissionTo(PermissionCode::DeletePromotions->value);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $manager = $this->userWithRole(RoleName::Manager);
        $promotion = Promotion::factory()->create();

        $this->actingAs($manager)->delete(route('control-panel.promotions.destroy', $promotion))->assertForbidden();
        $this->actingAs($manager)->get(route('control-panel.promotions.index'))
            ->assertOk()
            ->assertDontSee('>Удалить</button>', false);
        $this->assertModelExists($promotion);

        // А покупатель, которому выдали только просмотр, видит список, но не создаёт
        $user = $this->userWithRole(RoleName::User);
        $user->givePermissionTo([PermissionCode::AccessControlPanel->value, PermissionCode::ViewPromotions->value]);

        $this->actingAs($user)->get(route('control-panel.promotions.index'))->assertOk();
        $this->actingAs($user)->get(route('control-panel.promotions.create'))->assertForbidden();
    }

    public function test_у_товара_только_одна_акция(): void
    {
        $manager = $this->userWithRole(RoleName::Manager);
        $product = Product::factory()->create(['name' => 'Блесна щучья']);
        $existing = Promotion::factory()->hasAttached($product)->create(['title' => 'Осенняя']);

        $this->actingAs($manager)
            ->post(route('control-panel.promotions.store'), $this->percentPayload([$product->id]))
            ->assertSessionHasErrors('products');

        $this->assertSame(1, Promotion::count());

        // Сохранение той же акции с её же товаром — не конфликт
        $this->actingAs($manager)
            ->put(route('control-panel.promotions.update', $existing), $this->percentPayload([$product->id], ['title' => 'Осенняя-2']))
            ->assertSessionHasNoErrors();

        $this->assertSame('Осенняя-2', $existing->fresh()?->title);
    }

    public function test_процентной_акции_нужны_товары_и_разумный_процент(): void
    {
        $manager = $this->userWithRole(RoleName::Manager);
        $product = Product::factory()->create();

        $this->actingAs($manager)
            ->post(route('control-panel.promotions.store'), $this->percentPayload([]))
            ->assertSessionHasErrors('products');

        $this->actingAs($manager)
            ->post(route('control-panel.promotions.store'), $this->percentPayload([$product->id], ['value' => 100]))
            ->assertSessionHasErrors('value');

        $this->actingAs($manager)
            ->post(route('control-panel.promotions.store'), $this->percentPayload([$product->id], [
                'starts_at' => '2026-10-20T10:00',
                'ends_at' => '2026-10-19T10:00',
            ]))
            ->assertSessionHasErrors('ends_at');
    }

    public function test_промокод_нормализуется_и_уникален(): void
    {
        $manager = $this->userWithRole(RoleName::Manager);
        $product = Product::factory()->create();

        $payload = [
            'title' => '500 ₽ на первый заказ',
            'type' => 'fixed',
            'value' => 500,
            'code' => '  rybak500 ',
            'max_uses' => 100,
            'color' => '#fca5a5',
            'is_active' => '1',
            // чужие для этого вида поля сервер обнуляет
            'products' => [$product->id],
            'min_order_amount' => 3000,
        ];

        $this->actingAs($manager)->post(route('control-panel.promotions.store'), $payload)->assertSessionHasNoErrors();

        $promotion = Promotion::firstOrFail();
        $this->assertSame('RYBAK500', $promotion->code);
        $this->assertNull($promotion->min_order_amount);
        $this->assertCount(0, $promotion->products);

        $this->actingAs($manager)
            ->post(route('control-panel.promotions.store'), [...$payload, 'code' => 'Rybak500'])
            ->assertSessionHasErrors('code');

        $this->actingAs($manager)
            ->post(route('control-panel.promotions.store'), [...$payload, 'code' => 'скидка'])
            ->assertSessionHasErrors('code');
    }

    public function test_бесплатной_доставке_нужен_порог(): void
    {
        $manager = $this->userWithRole(RoleName::Manager);

        $payload = ['title' => 'Доставка даром', 'type' => 'free_delivery', 'color' => '#86efac', 'is_active' => '1'];

        $this->actingAs($manager)->post(route('control-panel.promotions.store'), $payload)
            ->assertSessionHasErrors('min_order_amount');

        $this->actingAs($manager)->post(route('control-panel.promotions.store'), [...$payload, 'min_order_amount' => 5000, 'value' => 10])
            ->assertSessionHasNoErrors();

        $promotion = Promotion::firstOrFail();
        $this->assertSame(5000, $promotion->min_order_amount);
        $this->assertNull($promotion->value);
    }

    public function test_поиск_товаров_для_формы(): void
    {
        $manager = $this->userWithRole(RoleName::Manager);
        $free = Product::factory()->create(['name' => 'Серебряный хлыст 4 м', 'sku' => 'SU-ROD-001']);
        $taken = Product::factory()->create(['name' => 'Серебряный хлыст 5 м', 'sku' => 'SU-ROD-002']);
        $other = Promotion::factory()->hasAttached($taken)->create(['title' => 'Удочки']);
        Product::factory()->create(['name' => 'Мотыль', 'sku' => 'SU-BAIT-001']);

        $response = $this->actingAs($manager)
            ->getJson(route('control-panel.promotions.products', ['q' => 'хлыст']))
            ->assertOk()
            ->assertJsonCount(2, 'items')
            ->assertJsonPath('more', false);

        $rows = $response->collect('items')->keyBy('id');
        $this->assertNull($rows[$free->id]['taken']);
        $this->assertSame('Удочки', $rows[$taken->id]['taken']);

        // В форме самой акции её товары не считаются занятыми
        $this->actingAs($manager)
            ->getJson(route('control-panel.promotions.products', ['q' => 'SU-ROD-002', 'except' => $other->id]))
            ->assertJsonPath('items.0.taken', null);

        $this->actingAs($manager)
            ->getJson(route('control-panel.promotions.products', ['q' => 'х']))
            ->assertJsonCount(0, 'items');

        $this->actingAs($this->userWithRole(RoleName::User))
            ->getJson(route('control-panel.promotions.products', ['q' => 'хлыст']))
            ->assertForbidden();
    }

    public function test_миграция_выдаёт_права_существующим_ролям(): void
    {
        // Как на проде: роли уже есть, прав на акции ещё нет
        DB::table('permissions')->where('name', 'like', '%_promotions')->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $migration = require database_path('migrations/2026_10_18_120100_add_promotion_permissions.php');
        $migration->up();
        $migration->up(); // повторный запуск ничего не дублирует

        $this->assertSame(4, DB::table('permissions')->where('name', 'like', '%_promotions')->count());
        $this->assertSame(1, DB::table('action_groups')->where('name', 'Акции')->count());

        $manager = Role::findByName(RoleName::Manager->value);
        $this->assertTrue($manager->hasPermissionTo(PermissionCode::DeletePromotions->value));
        $this->assertFalse(Role::findByName(RoleName::User->value)->hasPermissionTo(PermissionCode::ViewPromotions->value));
    }
}
