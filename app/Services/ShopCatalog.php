<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Carbon;

/** Каталог лавки из config/shop.php: главы, категории, товары и акции. */
class ShopCatalog
{
    public function firstChapter(): ?string
    {
        /** @var array<string, array<string, mixed>> $chapters */
        $chapters = config('shop.chapters', []);

        return array_key_first($chapters);
    }

    /** Только акции, которые действуют прямо сейчас. ends — метка в мс для таймера в браузере. */
    public function activePromos(): array
    {
        $now = now();

        /** @var array<int, array<string, mixed>> $promos */
        $promos = config('shop.promos', []);

        return collect($promos)
            ->filter(function (array $p) use ($now): bool {
                $starts = isset($p['starts_at']) ? Carbon::parse($p['starts_at']) : null;
                $ends = isset($p['ends_at']) ? Carbon::parse($p['ends_at']) : null;

                return (! $starts || $starts->lte($now)) && (! $ends || $ends->gt($now));
            })
            ->map(fn (array $p) => [
                'title' => $p['title'],
                'note' => $p['note'] ?? null,
                'ends' => isset($p['ends_at']) ? Carbon::parse($p['ends_at'])->getTimestampMs() : null,
            ])
            ->values()
            ->all();
    }

    /** Данные для «монитора»: главы, категории и товары внутри них. */
    public function catalogData(): array
    {
        /** @var array<string, array<string, mixed>> $categories */
        $categories = config('shop.categories', []);

        /** @var array<int, array<string, mixed>> $productList */
        $productList = config('shop.products', []);

        /** @var array<string, array<string, mixed>> $chapters */
        $chapters = config('shop.chapters', []);

        $products = collect($productList)->groupBy('category');

        return [
            'chapters' => collect($chapters)
                ->map(fn (array $c) => [
                    'title' => $c['title'],
                    'icon' => $c['icon'],
                    'categories' => array_values(array_filter(
                        (array) $c['categories'],
                        fn (string $s) => isset($categories[$s]),
                    )),
                ])
                ->all(),
            'categories' => collect($categories)
                ->map(fn (array $c, string $slug) => [
                    'title' => $c['title'],
                    'icon' => $c['icon'],
                    'description' => $c['description'],
                    'url' => route('catalog.category', $slug),
                    'products' => $products->get($slug, collect())
                        ->map(fn (array $p) => [
                            'id' => $p['id'],
                            'name' => $p['name'],
                            'price' => $p['price'],
                            'icon' => $p['icon'],
                        ])
                        ->values()
                        ->all(),
                ])
                ->all(),
        ];
    }
}
