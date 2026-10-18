<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Controllers\CartController;
use App\Models\Order;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/** Оформление заказа: оплата кошельком, списание остатков, идемпотентность. */
class CheckoutTest extends TestCase
{
    use RefreshDatabase;

    private User $buyer;

    protected function setUp(): void
    {
        parent::setUp();

        config(['shop.delivery_price' => 700, 'shop.welcome_balance' => 20_000]);
        $this->buyer = User::factory()->create();
    }

    /** @param array<int, int> $items id => qty */
    private function items(array $items): array
    {
        return collect($items)->map(fn ($qty, $id) => ['id' => $id, 'qty' => $qty])->values()->all();
    }

    /**
     * @param  array<int, int>  $items  id => qty
     * @return TestResponse<JsonResponse>
     */
    private function checkout(array $items, int $total, ?string $key = null): TestResponse
    {
        return $this->actingAs($this->buyer)->postJson(route('orders.store'), [
            'items' => $this->items($items),
            'total' => $total,
            'key' => $key ?? (string) Str::uuid(),
        ]);
    }

    private function balance(): int
    {
        return (int) Wallet::query()->where('user_id', $this->buyer->id)->value('balance');
    }

    public function test_корзина_считает_доставку_и_сумму_к_оплате(): void
    {
        $product = Product::factory()->create(['price' => 1000]);

        $this->postJson(route('cart.quote'), ['items' => $this->items([$product->id => 2])])
            ->assertOk()
            ->assertJsonPath('total', 2000)
            ->assertJsonPath('delivery_fee', 700)
            ->assertJsonPath('delivery_price', 700)
            ->assertJsonPath('to_pay', 2700);

        $this->postJson(route('cart.quote'), ['items' => []])
            ->assertJsonPath('delivery_price', 0)
            ->assertJsonPath('to_pay', 0);
    }

    public function test_заказ_оплачивается_кошельком_и_списывает_остатки(): void
    {
        $rod = Product::factory()->create(['price' => 3000, 'stock' => 5, 'sku' => 'SU-ROD', 'name' => 'Удочка']);
        $bait = Product::factory()->create(['price' => 200, 'stock' => 10]);
        $promotion = Promotion::factory()->percent(10)->hasAttached($rod)->create();

        // 2 × 2700 + 3 × 200 = 6000, доставка 700
        $response = $this->checkout([$rod->id => 2, $bait->id => 3], 6700)
            ->assertCreated()
            ->assertJsonPath('order.status', 'paid')
            ->assertJsonPath('order.subtotal', 6600)
            ->assertJsonPath('order.items_discount', 600)
            ->assertJsonPath('order.delivery_price', 700)
            ->assertJsonPath('order.total', 6700)
            ->assertJsonPath('order.items.0.price', 2700)
            ->assertJsonPath('order.items.0.old_price', 3000)
            ->assertJsonPath('balance', 13_300);

        $order = Order::query()->sole();
        $this->assertSame($order->id, $response->json('order.id'));
        $this->assertSame('№ '.$order->id, $response->json('order.number'));

        $this->assertDatabaseHas('order_items', [
            'order_id' => $order->id, 'product_id' => $rod->id, 'sku' => 'SU-ROD', 'name' => 'Удочка',
            'unit_price' => 3000, 'unit_discount' => 300, 'quantity' => 2, 'promotion_id' => $promotion->id,
        ]);
        $this->assertSame(3, $rod->fresh()?->stock);
        $this->assertSame(7, $bait->fresh()?->stock);
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $rod->id, 'delta' => -2, 'stock_after' => 3, 'reason' => 'sale',
            'user_id' => $this->buyer->id, 'note' => 'Заказ '.$order->number(),
        ]);

        $this->assertSame(13_300, $this->balance());
        $this->assertDatabaseHas('wallet_transactions', [
            'amount' => -6700, 'balance_after' => 13_300, 'reason' => 'purchase', 'reference' => 'order:'.$order->id,
        ]);
    }

    public function test_бесплатная_доставка_обнуляет_доставку(): void
    {
        $product = Product::factory()->create(['price' => 1500]);
        Promotion::factory()->freeDelivery(3000)->create();

        $this->checkout([$product->id => 2], 3000)
            ->assertCreated()
            ->assertJsonPath('order.delivery_price', 0)
            ->assertJsonPath('order.total', 3000);

        $this->assertSame(17_000, $this->balance());
    }

    public function test_промокод_расходуется_и_забывается(): void
    {
        $product = Product::factory()->create(['price' => 1000]);
        $promotion = Promotion::factory()->code('RYBAK300', 300, maxUses: 5)->create();

        $this->actingAs($this->buyer)
            ->postJson(route('cart.promo-code.apply'), ['items' => $this->items([$product->id => 2]), 'code' => 'RYBAK300'])
            ->assertOk();

        $this->checkout([$product->id => 2], 2400)
            ->assertCreated()
            ->assertJsonPath('order.code_discount', 300)
            ->assertJsonPath('order.promo_code', 'RYBAK300')
            ->assertSessionMissing(CartController::SESSION_KEY);

        $this->assertSame(1, $promotion->fresh()?->used_count);
        $this->assertDatabaseHas('orders', ['promotion_id' => $promotion->id, 'total' => 2400]);
    }

    public function test_промокод_израсходован_к_моменту_оформления(): void
    {
        $product = Product::factory()->create(['price' => 1000]);
        $promotion = Promotion::factory()->code('LAST', 300, maxUses: 1)->create();

        $this->actingAs($this->buyer)
            ->postJson(route('cart.promo-code.apply'), ['items' => $this->items([$product->id => 1]), 'code' => 'LAST'])
            ->assertOk();

        // Последний раз его успел применить кто-то другой
        $promotion->forceFill(['used_count' => 1])->save();

        $this->checkout([$product->id => 1], 1400)
            ->assertStatus(409)
            ->assertJsonPath('message', 'Промокод уже израсходован.')
            ->assertJsonPath('quote.code', null)
            ->assertJsonPath('quote.to_pay', 1700)
            ->assertSessionMissing(CartController::SESSION_KEY);

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_без_денег_заказ_не_оформляется_и_ничего_не_меняется(): void
    {
        $product = Product::factory()->create(['price' => 25_000, 'stock' => 3]);

        $this->checkout([$product->id => 1], 25_700)
            ->assertStatus(422)
            ->assertJsonPath('quote', null)
            ->assertJson(fn ($json) => $json->where('message', fn (string $m) => str_contains($m, 'Недостаточно денег'))->etc());

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('order_items', 0);
        $this->assertDatabaseCount('stock_movements', 0);
        $this->assertSame(3, $product->fresh()?->stock);
        $this->assertSame(20_000, $this->balance());
    }

    public function test_товара_меньше_чем_в_корзине(): void
    {
        $product = Product::factory()->create(['price' => 1000, 'stock' => 1]);

        $this->checkout([$product->id => 2], 2700)
            ->assertStatus(409)
            ->assertJsonPath('quote.limited', [$product->id])
            ->assertJsonPath('quote.items.0.qty', 1);

        $this->assertDatabaseCount('orders', 0);
        $this->assertSame(1, $product->fresh()?->stock);
        $this->assertSame(20_000, $this->balance());
    }

    public function test_изменившаяся_цена_не_списывается_молча(): void
    {
        $product = Product::factory()->create(['price' => 1000]);

        // Покупатель видел 1000 + 700, а цена уже 1200
        $product->update(['price' => 1200]);

        $this->checkout([$product->id => 1], 1700)
            ->assertStatus(409)
            ->assertJsonPath('quote.to_pay', 1900);

        $this->assertDatabaseCount('orders', 0);
        $this->assertSame(20_000, $this->balance());
    }

    public function test_повтор_с_тем_же_ключом_не_оформляет_второй_заказ(): void
    {
        $product = Product::factory()->create(['price' => 1000, 'stock' => 10]);
        $key = (string) Str::uuid();

        $first = $this->checkout([$product->id => 1], 1700, $key)->assertCreated();
        $again = $this->checkout([$product->id => 1], 1700, $key)->assertOk();

        $this->assertSame($first->json('order.id'), $again->json('order.id'));
        $this->assertDatabaseCount('orders', 1);
        $this->assertSame(9, $product->fresh()?->stock);
        $this->assertSame(18_300, $this->balance());

        // Новый ключ — новый заказ
        $this->checkout([$product->id => 1], 1700)->assertCreated();
        $this->assertDatabaseCount('orders', 2);
    }

    public function test_гостю_оформление_недоступно(): void
    {
        $product = Product::factory()->create();

        $this->postJson(route('orders.store'), [
            'items' => $this->items([$product->id => 1]), 'total' => 1700, 'key' => (string) Str::uuid(),
        ])->assertUnauthorized();

        $this->getJson(route('orders.index'))->assertUnauthorized();
    }

    public function test_оформление_проверяет_ввод(): void
    {
        $this->actingAs($this->buyer)
            ->postJson(route('orders.store'), ['items' => [], 'total' => -1, 'key' => 'не-uuid'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['items', 'total', 'key']);
    }

    public function test_история_показывает_только_свои_заказы(): void
    {
        $product = Product::factory()->create(['price' => 1000, 'name' => 'Блесна']);
        $this->checkout([$product->id => 2], 2700)->assertCreated();

        $stranger = User::factory()->create();
        Order::query()->create(['user_id' => $stranger->id, 'subtotal' => 1, 'total' => 1]);

        $this->actingAs($this->buyer)
            ->getJson(route('orders.index'))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status_label', 'Оплачен')
            ->assertJsonPath('data.0.total', 2700)
            ->assertJsonPath('data.0.items.0.name', 'Блесна')
            ->assertJsonPath('data.0.items.0.qty', 2)
            ->assertJsonMissingPath('data.0.checkout_key');
    }

    public function test_в_меню_есть_мои_заказы(): void
    {
        $this->actingAs($this->buyer)
            ->get(route('home'))
            ->assertOk()
            ->assertSee('Мои заказы')
            ->assertSee('Оформить за', false);
    }
}
