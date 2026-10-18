<?php

declare(strict_types=1);

namespace App\View\Composers;

use App\Services\ShopCatalog;
use Illuminate\View\View;

/** Данные для монитора-каталога, который есть на каждой странице. */
class CatalogMonitorComposer
{
    public function __construct(private readonly ShopCatalog $shop) {}

    public function compose(View $view): void
    {
        $view->with('monitor', [
            'catalog' => $this->shop->catalogData(),
            'promos' => array_column($this->shop->activePromos(), 'title'),
            'firstChapter' => $this->shop->firstChapter(),
        ]);
    }
}
