<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Models\Category;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\User;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as Router;
use Tests\TestCase;

/**
 * Обходит все GET-страницы приложения гостем и администратором и требует,
 * чтобы ни одна не отвечала 5xx. Новый маршрут попадает сюда сам; если у него
 * параметр, которого нет в params(), тест подскажет, что дописать.
 */
class PagesSmokeTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->admin = User::factory()->create(['email_verified_at' => now()]);
        $this->admin->syncRoles([RoleName::Admin->value]);
    }

    public function test_страницы_работают_с_каталогом_и_акциями(): void
    {
        $this->seed(CatalogSeeder::class);

        $products = Product::query()->limit(3)->get();
        Promotion::factory()->percent(15)->hasAttached($products)->create(['ends_at' => now()->addMinutes(30)]);
        Promotion::factory()->code('SMOKE500', 500, maxUses: 10)->create(['ends_at' => now()->addDays(2)]);
        Promotion::factory()->freeDelivery(5000)->create();
        Promotion::factory()->ended()->create();
        Promotion::factory()->create(['is_active' => false, 'starts_at' => now()->addWeek()]);

        $this->visitAll();
    }

    public function test_страницы_работают_на_пустой_базе(): void
    {
        // Ни глав, ни товаров, ни акций: так выглядит свежая установка до сидеров
        $this->visitAll(category: Category::factory()->create()->slug);
    }

    private function visitAll(?string $category = null): void
    {
        $params = $this->params($category);
        $visited = 0;

        foreach ($this->getRoutes() as $route) {
            $uri = $this->resolve($route, $params);

            foreach (['гость' => null, 'админ' => $this->admin] as $who => $user) {
                if ($user) {
                    $this->actingAs($user);
                } else {
                    $this->app['auth']->forgetGuards();
                }

                $response = $this->get($uri);

                $this->assertLessThan(500, $response->status(), sprintf(
                    '%s: GET %s (%s) ответил %d: %s',
                    $who, $uri, $route->getName() ?? 'без имени', $response->status(),
                    $response->exception?->getMessage() ?? '',
                ));
                $visited++;
            }
        }

        $this->assertGreaterThan(40, $visited);
    }

    /** @return list<Route> */
    private function getRoutes(): array
    {
        return array_values(array_filter(
            Router::getRoutes()->getRoutes(),
            fn (Route $r) => in_array('GET', $r->methods(), true)
                && ! str_starts_with($r->uri(), '_')            // служебные маршруты пакетов
                && ! str_starts_with($r->uri(), 'storage/')
                && $r->uri() !== 'up',
        ));
    }

    /** @return array<string, string> */
    private function params(?string $category): array
    {
        $task = $this->admin->tasks()->create(['type' => 'demo_report', 'payload' => ['seconds' => 5]]);

        return [
            'slug' => $category ?? Category::query()->orderBy('id')->value('slug'),
            'promotion' => (string) (Promotion::query()->value('id') ?? Promotion::factory()->create()->id),
            'task' => $task->id,
            'token' => 'smoke-test-token',
            'id' => (string) $this->admin->id,
            'hash' => sha1($this->admin->email),
        ];
    }

    /** @param array<string, string> $params */
    private function resolve(Route $route, array $params): string
    {
        $uri = '/'.ltrim($route->uri(), '/');

        foreach ($route->parameterNames() as $name) {
            $this->assertArrayHasKey($name, $params, sprintf(
                'У маршрута %s параметр {%s}: добавьте его значение в PagesSmokeTest::params().',
                $route->uri(), $name,
            ));
            $uri = preg_replace('/\{'.$name.'\??\}/', rawurlencode($params[$name]), $uri);
        }

        return $uri;
    }
}
