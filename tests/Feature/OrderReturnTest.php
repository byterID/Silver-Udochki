<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderReturn;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/** Возврат по заказу: деньги на кошелёк, товары на склад, частичные возвраты. */
class OrderReturnTest extends TestCase
{
    use RefreshDatabase;

    private User $buyer;

    protected function setUp(): void
    {
        parent::setUp();

        config(['shop.delivery_price' => 700, 'shop.welcome_balance' => 20_000, 'shop.return_days' => 14]);
        $this->buyer = User::factory()->create();
    }

    /**
     * Оформляет заказ через корзину, как покупатель.
     *
     * @param  array<int, int>  $items  product_id => qty
     */
    private function order(array $items, int $total, ?string $code = null): Order
    {
        $payload = collect($items)->map(fn ($qty, $id) => ['id' => $id, 'qty' => $qty])->values()->all();

        if ($code !== null) {
            $this->actingAs($this->buyer)
                ->postJson(route('cart.promo-code.apply'), ['items' => $payload, 'code' => $code])
                ->assertOk();
        }

        $id = $this->actingAs($this->buyer)
            ->postJson(route('orders.store'), ['items' => $payload, 'total' => $total, 'key' => (string) Str::uuid()])
            ->assertCreated()
            ->json('order.id');

        return Order::query()->findOrFail($id);
    }

    private function line(Order $order, Product $product): OrderItem
    {
        return $order->items()->where('product_id', $product->id)->sole();
    }

    /**
     * @param  array<int, int>  $lines  order_item_id => qty
     * @return TestResponse<JsonResponse>
     */
    private function returnItems(Order $order, array $lines, string $reason = 'not_suitable', ?string $comment = null, ?string $key = null, ?User $as = null): TestResponse
    {
        return $this->actingAs($as ?? $this->buyer)->postJson(route('orders.returns.store', $order), [
            'items' => collect($lines)->map(fn ($qty, $id) => ['id' => $id, 'qty' => $qty])->values()->all(),
            'reason' => $reason,
            'comment' => $comment,
            'key' => $key ?? (string) Str::uuid(),
        ]);
    }

    private function balance(): int
    {
        return (int) Wallet::query()->where('user_id', $this->buyer->id)->value('balance');
    }

    public function test_полный_возврат_возвращает_деньги_с_доставкой_и_остатки(): void
    {
        $rod = Product::factory()->create(['price' => 3000, 'stock' => 5]);
        $bait = Product::factory()->create(['price' => 200, 'stock' => 10]);
        // 2 × 3000 + 3 × 200 + доставка 700
        $order = $this->order([$rod->id => 2, $bait->id => 3], 7300);
        $this->assertSame(12_700, $this->balance());

        $this->returnItems($order, [$this->line($order, $rod)->id => 2, $this->line($order, $bait)->id => 3], 'defect', 'Катушка заедает')
            ->assertCreated()
            ->assertJsonPath('refund', 7300)
            ->assertJsonPath('balance', 20_000)
            ->assertJsonPath('order.status', 'refunded')
            ->assertJsonPath('order.status_label', 'Возвращён')
            ->assertJsonPath('order.refunded', 7300)
            ->assertJsonPath('order.returnable', false)
            ->assertJsonPath('order.items.0.returned', 2);

        $this->assertSame(20_000, $this->balance());
        $this->assertSame(5, $rod->fresh()?->stock);
        $this->assertSame(10, $bait->fresh()?->stock);

        $return = OrderReturn::query()->sole();
        $this->assertSame('defect', $return->reason->value);
        $this->assertSame('Катушка заедает', $return->comment);
        $this->assertSame($this->buyer->id, $return->user_id);

        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $rod->id, 'delta' => 2, 'stock_after' => 5, 'reason' => 'return',
            'user_id' => $this->buyer->id, 'note' => 'Возврат по заказу '.$order->number(),
        ]);
        $this->assertDatabaseHas('wallet_transactions', [
            'amount' => 7300, 'balance_after' => 20_000, 'reason' => 'refund', 'reference' => 'return:'.$return->id,
        ]);
    }

    public function test_частичные_возвраты_делят_промокод_и_в_сумме_дают_оплаченное(): void
    {
        $rod = Product::factory()->create(['price' => 3000, 'stock' => 5]);
        $bait = Product::factory()->create(['price' => 1000, 'stock' => 5]);
        Promotion::factory()->code('RYBAK400', 400)->create();

        // 4000 − 400 + 700 = 4300
        $order = $this->order([$rod->id => 1, $bait->id => 1], 4300, 'RYBAK400');

        // Блесна — четверть стоимости товаров: 3600 × 1000 / 4000 = 900, доставку не трогаем
        $this->returnItems($order, [$this->line($order, $bait)->id => 1])
            ->assertCreated()
            ->assertJsonPath('refund', 900)
            ->assertJsonPath('order.status', 'partially_refunded')
            ->assertJsonPath('order.returnable', true);

        // Остаток заказа: 3600 − 900 + доставка 700
        $this->returnItems($order, [$this->line($order, $rod)->id => 1])
            ->assertCreated()
            ->assertJsonPath('refund', 3400)
            ->assertJsonPath('order.status', 'refunded')
            ->assertJsonPath('order.refunded', 4300);

        $this->assertSame(20_000, $this->balance());
    }

    public function test_возврат_по_штукам_не_теряет_рубли_на_округлении(): void
    {
        $float = Product::factory()->create(['price' => 1000, 'stock' => 10]);
        Promotion::factory()->code('MINUS100', 100)->create();

        // 3000 − 100 + 700 = 3600; за товары оплачено 2900
        $order = $this->order([$float->id => 3], 3600, 'MINUS100');
        $line = $this->line($order, $float)->id;

        $refunds = [];
        foreach ([1, 1, 1] as $qty) {
            $refunds[] = $this->returnItems($order, [$line => $qty])->assertCreated()->json('refund');
        }

        // 2900/3 = 966,67 → 966, затем до 1933 → 967, последняя штука забирает остаток и доставку
        $this->assertSame([966, 967, 1667], $refunds);
        $this->assertSame(3600, array_sum($refunds));
        $this->assertSame(20_000, $this->balance());
        $this->assertSame(10, $float->fresh()?->stock);
    }

    public function test_нельзя_вернуть_больше_чем_купили(): void
    {
        $product = Product::factory()->create(['price' => 1000, 'stock' => 10]);
        $order = $this->order([$product->id => 2], 2700);
        $line = $this->line($order, $product)->id;

        $this->returnItems($order, [$line => 3])
            ->assertStatus(422)
            ->assertJsonPath('message', '«'.$product->name.'»: можно вернуть не больше 2 шт.');

        $this->returnItems($order, [$line => 1])->assertCreated();
        $this->returnItems($order, [$line => 2])
            ->assertStatus(422)
            ->assertJsonPath('message', '«'.$product->name.'»: можно вернуть не больше 1 шт.');

        $this->returnItems($order, [$line => 1])->assertCreated()->assertJsonPath('order.status', 'refunded');
        $this->returnItems($order, [$line => 1])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Заказ уже возвращён целиком.');

        $this->assertDatabaseCount('order_returns', 2);
        $this->assertSame(10, $product->fresh()?->stock);
        $this->assertSame(20_000, $this->balance());
    }

    public function test_чужая_строка_заказа_не_возвращается(): void
    {
        $product = Product::factory()->create(['price' => 1000]);
        $mine = $this->order([$product->id => 1], 1700);
        $other = $this->order([$product->id => 1], 1700);

        $this->returnItems($mine, [$this->line($other, $product)->id => 1])
            ->assertStatus(422)
            ->assertJsonPath('message', 'В заказе нет такой позиции. Откройте «Мои заказы» заново.');

        $this->assertDatabaseCount('order_returns', 0);
    }

    public function test_повтор_с_тем_же_ключом_не_возвращает_деньги_дважды(): void
    {
        $product = Product::factory()->create(['price' => 1000, 'stock' => 10]);
        $order = $this->order([$product->id => 2], 2700);
        $line = $this->line($order, $product)->id;
        $key = (string) Str::uuid();

        $first = $this->returnItems($order, [$line => 1], key: $key)->assertCreated();
        $again = $this->returnItems($order, [$line => 1], key: $key)->assertOk();

        $this->assertSame($first->json('refund'), $again->json('refund'));
        $this->assertDatabaseCount('order_returns', 1);
        $this->assertSame(9, $product->fresh()?->stock);
        $this->assertSame(17_300 + 1000, $this->balance());
    }

    public function test_после_срока_возврат_не_оформить(): void
    {
        $product = Product::factory()->create(['price' => 1000, 'stock' => 10]);
        $order = $this->order([$product->id => 1], 1700);

        $this->travel(15)->days();

        $this->actingAs($this->buyer)->getJson(route('orders.index'))
            ->assertJsonPath('data.0.returnable', false);

        $this->returnItems($order, [$this->line($order, $product)->id => 1])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Срок возврата истёк: вернуть можно в течение 14 дней после покупки.');

        $this->assertSame(9, $product->fresh()?->stock);
        $this->assertSame(18_300, $this->balance());
    }

    public function test_товара_уже_нет_в_каталоге_деньги_всё_равно_возвращаются(): void
    {
        $product = Product::factory()->create(['price' => 1000, 'stock' => 10]);
        $order = $this->order([$product->id => 1], 1700);
        $line = $this->line($order, $product)->id;

        // Как после восстановления каталога: строки заказа остались, товара нет
        $product->delete();

        $this->returnItems($order, [$line => 1])->assertCreated()->assertJsonPath('refund', 1700);

        $this->assertDatabaseMissing('stock_movements', ['reason' => 'return']);
        $this->assertSame(20_000, $this->balance());
    }

    public function test_чужой_заказ_вернуть_нельзя(): void
    {
        $product = Product::factory()->create(['price' => 1000]);
        $order = $this->order([$product->id => 1], 1700);
        $line = $this->line($order, $product)->id;

        $this->returnItems($order, [$line => 1], as: User::factory()->create())->assertForbidden();

        auth()->logout();
        $this->postJson(route('orders.returns.store', $order), [
            'items' => [['id' => $line, 'qty' => 1]], 'reason' => 'other', 'key' => (string) Str::uuid(),
        ])->assertUnauthorized();

        $this->assertDatabaseCount('order_returns', 0);
    }

    public function test_форма_возврата_проверяет_ввод(): void
    {
        $product = Product::factory()->create(['price' => 1000]);
        $order = $this->order([$product->id => 1], 1700);
        $line = $this->line($order, $product)->id;

        $this->actingAs($this->buyer)
            ->postJson(route('orders.returns.store', $order), ['items' => [], 'reason' => 'whim', 'key' => 'не-uuid'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['items', 'reason', 'key']);

        // «Другое» без объяснения не принимаем
        $this->returnItems($order, [$line => 1], 'other', '   ')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['comment' => 'Опишите причину возврата.']);

        $this->returnItems($order, [$line => 1], 'other', 'Подарили такую же')->assertCreated();
        $this->assertSame('Подарили такую же', OrderReturn::query()->sole()->comment);
    }

    public function test_окно_заказов_показывает_кнопку_и_возвраты(): void
    {
        $product = Product::factory()->create(['price' => 1000, 'name' => 'Блесна']);
        $order = $this->order([$product->id => 2], 2700);

        $this->actingAs($this->buyer)->getJson(route('orders.index'))
            ->assertOk()
            ->assertJsonPath('data.0.returnable', true)
            ->assertJsonPath('data.0.refunded', 0)
            ->assertJsonPath('data.0.items.0.returned', 0);

        $this->returnItems($order, [$this->line($order, $product)->id => 1])->assertCreated();

        $this->actingAs($this->buyer)->getJson(route('orders.index'))
            ->assertJsonPath('data.0.status_label', 'Частичный возврат')
            ->assertJsonPath('data.0.refunded', 1000)
            ->assertJsonPath('data.0.items.0.returned', 1)
            ->assertJsonPath('data.0.returnable', true);

        $this->actingAs($this->buyer)->get(route('home'))
            ->assertOk()
            ->assertSee('Оформить возврат')
            ->assertSee('Причина возврата');
    }
}
