<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Services\ShopCatalog;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CatalogSeeder::class);
    }

    public function test_catalog_is_seeded(): void
    {
        $this->assertGreaterThan(30, Category::count());
        $this->assertGreaterThan(500, Product::count());
    }

    public function test_seeder_can_run_twice(): void
    {
        $before = Product::count();

        $this->seed(CatalogSeeder::class);

        $this->assertSame($before, Product::count());
    }

    public function test_home_page_renders(): void
    {
        $this->get('/')->assertOk();
    }

    public function test_category_page_lists_products(): void
    {
        $this->get(route('catalog.category', 'rods'))
            ->assertOk()
            ->assertSee('Серебряный хлыст');
    }

    public function test_every_category_page_opens(): void
    {
        foreach (Category::pluck('slug') as $slug) {
            $this->get(route('catalog.category', $slug))->assertOk();
        }
    }

    public function test_unknown_category_returns_404(): void
    {
        $this->get(route('catalog.category', 'no-such-category'))->assertNotFound();
    }

    public function test_search_finds_products(): void
    {
        $this->get(route('catalog.search', ['q' => 'блесна на щуку']))
            ->assertOk()
            ->assertSee('Щучья ложка');
    }

    public function test_empty_search_returns_nothing(): void
    {
        $this->get(route('catalog.search', ['q' => '']))
            ->assertOk()
            ->assertSee('Напиши, что ищешь');
    }

    public function test_monitor_catalog_comes_from_database(): void
    {
        $shop = app(ShopCatalog::class);
        $data = $shop->catalogData();

        $this->assertCount(4, $data['chapters']);
        $this->assertSame('fishing', $shop->firstChapter());
        $this->assertArrayHasKey('ice-rods', $data['categories']);
        $this->assertNotEmpty($data['categories']['rods']['products']);
    }
}
