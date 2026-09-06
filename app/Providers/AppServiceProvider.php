<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
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

        // За прокси генерируем только https-ссылки
        if ($this->app->isProduction()) {
            URL::forceScheme('https');
        }
    }
}
