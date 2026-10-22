<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Services\ShopCatalog;
use App\Services\Stock\StockException;
use App\Services\Stock\StockKeeper;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\JsonResponse;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class StockTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<int, int>  $items  id => qty
     * @return TestResponse<JsonResponse>
     */
    private function quote(array $items): TestResponse
    {
        return $this->postJson(route('cart.quote'), [
            'items' => $this->cartItems($items),
        ]);
    }

    public function test_каталог_заводится_по_100_штук(): void
    {
        $this->seed(CatalogSeeder::class);

        $this->assertSame([CatalogSeeder::INITIAL_STOCK], Product::query()->distinct()->pluck('stock')->all());
    }

    public function test_карточка_показывает_точный_остаток(): void
    {
        config(['shop.low_stock' => 10]);
        $category = Category::factory()->create(['slug' => 'rods']);
        Product::factory()->for($category)->create(['name' => 'Много', 'stock' => 100]);
        Product::factory()->for($category)->create(['name' => 'Мало', 'stock' => 3]);
        Product::factory()->for($category)->create(['name' => 'Пусто', 'stock' => 0]);

        $this->get(route('catalog.category', 'rods'))
            ->assertOk()
            ->assertSee('В наличии: 100 шт.')
            ->assertSee('Осталось мало: 3 шт.')
            ->assertSee('Нет в наличии');
    }

    public function test_витрина_отдаёт_остаток_и_признак_мало(): void
    {
        config(['shop.low_stock' => 10]);
        $shop = app(ShopCatalog::class);

        $low = $shop->presentProduct(Product::factory()->create(['stock' => 10]));
        $plenty = $shop->presentProduct(Product::factory()->create(['stock' => 11]));
        $none = $shop->presentProduct(Product::factory()->create(['stock' => 0]));

        $this->assertSame([10, true], [$low['stock'], $low['low']]);
        $this->assertSame([11, false], [$plenty['stock'], $plenty['low']]);
        $this->assertSame([0, false], [$none['stock'], $none['low']]);
    }

    public function test_корзина_урезает_количество_до_остатка(): void
    {
        $product = Product::factory()->create(['price' => 100, 'stock' => 3]);

        $this->quote([$product->id => 5])
            ->assertOk()
            ->assertJsonPath('items.0.qty', 3)
            ->assertJsonPath('items.0.stock', 3)
            ->assertJsonPath('limited', [$product->id])
            ->assertJsonPath('total', 300);

        $this->quote([$product->id => 3])
            ->assertJsonPath('items.0.qty', 3)
            ->assertJsonPath('limited', []);
    }

    public function test_закончившийся_товар_убирается_из_корзины(): void
    {
        $gone = Product::factory()->create(['stock' => 0]);
        $left = Product::factory()->create(['stock' => 1]);

        $this->quote([$gone->id => 1, $left->id => 1])
            ->assertOk()
            ->assertJsonCount(1, 'items')
            ->assertJsonPath('items.0.id', $left->id)
            ->assertJsonPath('missing', [$gone->id]);
    }

    public function test_поступление_и_инвентаризация_пишут_журнал(): void
    {
        $product = Product::factory()->create(['stock' => 5]);
        $keeper = app(StockKeeper::class);

        $restock = $keeper->restock($product, 10);
        $this->assertSame([10, 15], [$restock->delta, $restock->stock_after]);
        $this->assertSame(15, $product->stock);

        $inventory = $keeper->setCount($product, 12, note: 'пересчёт полки');
        $this->assertSame([-3, 12], [$inventory?->delta, $inventory?->stock_after]);
        $this->assertSame('пересчёт полки', $inventory?->note);

        $this->assertNull($keeper->setCount($product, 12), 'Без изменений строка в журнал не пишется');
        $this->assertSame(12, $product->refresh()->stock);
        $this->assertDatabaseCount('stock_movements', 2);
    }

    public function test_отрицательный_остаток_невозможен(): void
    {
        $product = Product::factory()->create(['stock' => 5]);

        try {
            app(StockKeeper::class)->setCount($product, -1);
            $this->fail('Ожидалось исключение');
        } catch (StockException) {
            $this->assertSame(5, $product->refresh()->stock);
            $this->assertDatabaseCount('stock_movements', 0);
        }
    }

    public function test_журнал_переживает_удаление_товара(): void
    {
        $product = Product::factory()->create(['sku' => 'SU-T-1', 'name' => 'Удочка']);
        app(StockKeeper::class)->restock($product, 1);

        $product->delete();

        $this->assertDatabaseHas('stock_movements', ['product_id' => $product->id, 'sku' => 'SU-T-1', 'name' => 'Удочка']);
    }
}
