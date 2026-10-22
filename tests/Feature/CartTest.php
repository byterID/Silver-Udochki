<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Promotion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CartTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<int, int>  $items  id => qty
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function body(array $items, array $extra = []): array
    {
        return ['items' => $this->cartItems($items), ...$extra];
    }

    public function test_цены_считает_сервер_а_не_браузер(): void
    {
        $rod = Product::factory()->create(['price' => 3000]);
        $bait = Product::factory()->create(['price' => 200]);
        Promotion::factory()->percent(10)->hasAttached($rod)->create();

        // Браузер не может прислать свою цену: её просто не читают
        $this->postJson(route('cart.quote'), [
            'items' => [['id' => $rod->id, 'qty' => 2, 'price' => 1], ['id' => $bait->id, 'qty' => 3]],
        ])
            ->assertOk()
            ->assertJsonPath('items.0.price', 2700)
            ->assertJsonPath('items.0.old_price', 3000)
            ->assertJsonPath('items.0.badge', '−10%')
            ->assertJsonPath('items.1.price', 200)
            ->assertJsonPath('items.1.old_price', null)
            ->assertJsonPath('subtotal', 6600)
            ->assertJsonPath('items_discount', 600)
            ->assertJsonPath('total', 6000)
            ->assertJsonPath('delivery', null);
    }

    public function test_снятые_с_продажи_товары_возвращаются_как_missing(): void
    {
        $gone = Product::factory()->create(['is_active' => false]);

        $this->postJson(route('cart.quote'), $this->body([$gone->id => 1, 999999 => 1]))
            ->assertOk()
            ->assertJsonCount(0, 'items')
            ->assertJsonPath('missing', [$gone->id, 999999]);
    }

    public function test_промокод_применяется_и_хранится_в_сессии(): void
    {
        $product = Product::factory()->create(['price' => 1000]);
        Promotion::factory()->code('RYBAK300', 300)->create();

        $this->postJson(route('cart.promo-code.apply'), $this->body([$product->id => 2], ['code' => ' rybak300 ']))
            ->assertOk()
            ->assertJsonPath('code.code', 'RYBAK300')
            ->assertJsonPath('code.discount', 300)
            ->assertJsonPath('total', 1700);

        // Дальше код не передаётся, его помнит сессия
        $this->postJson(route('cart.quote'), $this->body([$product->id => 1]))
            ->assertJsonPath('code.code', 'RYBAK300')
            ->assertJsonPath('total', 700);

        $this->deleteJson(route('cart.promo-code.remove'))->assertNoContent();

        $this->postJson(route('cart.quote'), $this->body([$product->id => 1]))
            ->assertJsonPath('code', null)
            ->assertJsonPath('total', 1000);
    }

    public function test_промокод_не_уводит_сумму_в_минус(): void
    {
        $product = Product::factory()->create(['price' => 200]);
        Promotion::factory()->code('BIG', 5000)->create();

        $this->postJson(route('cart.promo-code.apply'), $this->body([$product->id => 1], ['code' => 'BIG']))
            ->assertOk()
            ->assertJsonPath('code.discount', 200)
            ->assertJsonPath('total', 0);
    }

    public function test_неподходящий_промокод_объясняется(): void
    {
        $product = Product::factory()->create();
        Promotion::factory()->code('OLD', 100)->ended()->create();
        Promotion::factory()->code('SPENT', 100, maxUses: 5)->create(['used_count' => 5]);
        Promotion::factory()->code('LATER', 100)->create(['starts_at' => now()->addDay()]);

        foreach ([
            'NOPE' => 'Такого промокода нет.',
            'old' => 'Срок действия промокода истёк.',
            'SPENT' => 'Промокод уже израсходован.',
            'LATER' => 'Промокод ещё не начал действовать.',
        ] as $code => $message) {
            $this->postJson(route('cart.promo-code.apply'), $this->body([$product->id => 1], ['code' => $code]))
                ->assertStatus(422)
                ->assertJsonPath('message', $message)
                ->assertJsonPath('code', null);
        }
    }

    public function test_истёкший_промокод_пропадает_из_сессии(): void
    {
        $product = Product::factory()->create(['price' => 1000]);
        $promotion = Promotion::factory()->code('SOON', 100)->create(['ends_at' => now()->addHour()]);

        $this->postJson(route('cart.promo-code.apply'), $this->body([$product->id => 1], ['code' => 'SOON']))->assertOk();

        $promotion->update(['ends_at' => now()->subMinute()]);

        $this->postJson(route('cart.quote'), $this->body([$product->id => 1]))
            ->assertJsonPath('code', null)
            ->assertJsonPath('code_error', 'Срок действия промокода истёк.');

        $this->postJson(route('cart.quote'), $this->body([$product->id => 1]))
            ->assertJsonPath('code_error', null)
            ->assertSessionMissing('cart.promo_code');
    }

    public function test_порог_бесплатной_доставки(): void
    {
        $product = Product::factory()->create(['price' => 1500]);
        Promotion::factory()->freeDelivery(5000)->create();
        Promotion::factory()->freeDelivery(3000)->create(); // действует самый низкий порог

        $this->postJson(route('cart.quote'), $this->body([$product->id => 1]))
            ->assertJsonPath('delivery.free', false)
            ->assertJsonPath('delivery.from', 3000)
            ->assertJsonPath('delivery.left', 1500);

        $this->postJson(route('cart.quote'), $this->body([$product->id => 2]))
            ->assertJsonPath('delivery.free', true)
            ->assertJsonPath('delivery.left', 0);
    }

    public function test_подбор_промокодов_ограничен(): void
    {
        $product = Product::factory()->create();

        for ($i = 0; $i < 10; $i++) {
            $this->postJson(route('cart.promo-code.apply'), $this->body([$product->id => 1], ['code' => "TRY{$i}"]))
                ->assertStatus(422);
        }

        $this->postJson(route('cart.promo-code.apply'), $this->body([$product->id => 1], ['code' => 'TRY10']))
            ->assertStatus(429);
    }

    public function test_корзина_проверяет_ввод(): void
    {
        $this->postJson(route('cart.quote'), ['items' => [['id' => 'x', 'qty' => 0]]])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['items.0.id', 'items.0.qty']);

        $this->postJson(route('cart.quote'), ['items' => []])
            ->assertOk()
            ->assertJsonPath('total', 0);
    }
}
