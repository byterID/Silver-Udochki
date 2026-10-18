<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Promotion;
use App\Services\Pricing\PriceCalculator;
use App\Services\ShopCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PromotionPricingTest extends TestCase
{
    use RefreshDatabase;

    public function test_процент_округляется_в_пользу_лавки_и_не_обнуляет_цену(): void
    {
        $this->assertSame(850, PriceCalculator::applyPercent(1000, 15));
        $this->assertSame(850, PriceCalculator::applyPercent(999, 15)); // 149,85 → скидка 149
        $this->assertSame(1, PriceCalculator::applyPercent(1, 99));
        $this->assertSame(500, PriceCalculator::applyPercent(500, 0));
    }

    public function test_скидка_действует_только_в_свой_период_и_когда_включена(): void
    {
        $product = Product::factory()->create(['price' => 2000]);
        $promotion = Promotion::factory()->percent(25)->hasAttached($product)->create();
        $calc = fn () => (new PriceCalculator)->priceFor($product);

        $this->assertSame(1500, $calc()->final);
        $this->assertSame($promotion->id, $calc()->promotion?->id);

        $promotion->update(['starts_at' => now()->addHour()]);
        $this->assertSame(2000, $calc()->final);

        $promotion->update(['starts_at' => now()->subDay(), 'ends_at' => now()->subMinute()]);
        $this->assertSame(2000, $calc()->final);

        $promotion->update(['ends_at' => now()->addDay(), 'is_active' => false]);
        $this->assertSame(2000, $calc()->final);
    }

    public function test_витрина_показывает_старую_и_новую_цену(): void
    {
        $category = Category::factory()->create(['slug' => 'spoons']);
        $product = Product::factory()->for($category)->create(['name' => 'Щучья ложка', 'price' => 1000]);
        Promotion::factory()->percent(20)->hasAttached($product)->create(['color' => '#fca5a5']);

        $this->get(route('catalog.category', 'spoons'))
            ->assertOk()
            ->assertSee('Щучья ложка')
            ->assertSee('−20%')
            ->assertSee('<s class="text-sm text-stone-500">1 000 ₽</s>', false)
            ->assertSee('800 ₽');

        $monitor = app(ShopCatalog::class)->catalogData()['categories']['spoons']['products'][0];
        $this->assertSame(800, $monitor['price']);
        $this->assertSame(1000, $monitor['old_price']);
        $this->assertSame('#fca5a5', $monitor['color']);
    }

    public function test_доска_показывает_только_действующие_акции(): void
    {
        Promotion::factory()->create(['title' => 'Сейчас идёт', 'ends_at' => now()->addHours(5)]);
        Promotion::factory()->create(['title' => 'Бессрочная']);
        Promotion::factory()->ended()->create(['title' => 'Уже прошла']);
        Promotion::factory()->create(['title' => 'Выключенная', 'is_active' => false]);
        Promotion::factory()->code('USED', 100, maxUses: 1)->create(['title' => 'Израсходован', 'used_count' => 1]);

        $promos = app(ShopCatalog::class)->activePromos();

        // Сначала та, что скоро закончится
        $this->assertSame(['Сейчас идёт', 'Бессрочная'], array_column($promos, 'title'));

        $this->get('/')
            ->assertOk()
            ->assertSee('Сейчас идёт')
            ->assertDontSee('Уже прошла')
            ->assertDontSee('Выключенная');
    }
}
