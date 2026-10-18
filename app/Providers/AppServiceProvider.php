<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\Pricing\PriceCalculator;
use App\Services\ShopCatalog;
use App\View\Composers\CatalogMonitorComposer;
use App\View\Composers\WalletComposer;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Один экземпляр на запрос: акции и каталог загружаются один раз,
        // а контроллер и композер монитора видят одни и те же данные
        $this->app->scoped(PriceCalculator::class);
        $this->app->scoped(ShopCatalog::class);
    }

    public function boot(): void
    {
        // Единые требования к паролям во всём приложении
        Password::defaults(function () {
            $rule = Password::min(12)->letters()->numbers();

            return $this->app->isProduction()
                ? $rule->mixedCase()->uncompromised()
                : $rule;
        });

        // В разработке ловим N+1 и обращения к незаполненным атрибутам
        Model::shouldBeStrict(! $this->app->isProduction());

        // Корзина пересчитывается при каждом изменении, а вот подбирать промокоды перебором нельзя
        RateLimiter::for('cart', fn (Request $request) => Limit::perMinute(120)->by($request->ip()));
        RateLimiter::for('promo-code', fn (Request $request) => [
            Limit::perMinute(10)->by('minute:'.$request->ip()),
            Limit::perHour(60)->by('hour:'.$request->ip()),
        ]);

        // Монитор-каталог есть на каждой странице (открывается и из шапки)
        View::composer('shop.partials.catalog-monitor', CatalogMonitorComposer::class);
        View::composer('layouts.navigation', WalletComposer::class);

        // За прокси генерируем только https-ссылки
        if ($this->app->isProduction()) {
            URL::forceScheme('https');
        }
    }
}
