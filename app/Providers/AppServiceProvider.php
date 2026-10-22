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
        // Один экземпляр на запрос: контроллер и композер монитора делят загруженные акции
        $this->app->scoped(PriceCalculator::class);
        $this->app->scoped(ShopCatalog::class);
    }

    public function boot(): void
    {
        Password::defaults(function () {
            $rule = Password::min(12)->letters()->numbers();

            return $this->app->isProduction()
                ? $rule->mixedCase()->uncompromised()
                : $rule;
        });

        Model::shouldBeStrict(! $this->app->isProduction());

        // Корзина пересчитывается на каждый клик, а промокоды нельзя подбирать перебором
        RateLimiter::for('cart', fn (Request $request) => Limit::perMinute(120)->by($request->ip()));
        RateLimiter::for('promo-code', fn (Request $request) => [
            Limit::perMinute(10)->by('minute:'.$request->ip()),
            Limit::perHour(60)->by('hour:'.$request->ip()),
        ]);
        RateLimiter::for('checkout', fn (Request $request) => Limit::perMinute(10)->by((string) ($request->user()?->getAuthIdentifier() ?? $request->ip())));
        // Раунд идёт минуту: чаще стартует только тот, кто перебирает раскладки
        RateLimiter::for('game-start', fn (Request $request) => [
            Limit::perMinute(6)->by('minute:'.$request->user()?->getAuthIdentifier()),
            Limit::perHour(90)->by('hour:'.$request->user()?->getAuthIdentifier()),
        ]);
        RateLimiter::for('order-return', fn (Request $request) => Limit::perMinute(10)->by((string) ($request->user()?->getAuthIdentifier() ?? $request->ip())));

        View::composer('shop.partials.catalog-monitor', CatalogMonitorComposer::class);
        View::composer('layouts.navigation', WalletComposer::class);

        if ($this->app->isProduction()) {
            URL::forceScheme('https');
        }
    }
}
