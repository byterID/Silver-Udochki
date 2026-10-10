<?php

declare(strict_types=1);

namespace App\View\Composers;

use Illuminate\View\View;

/** Данные для экрана «Каталог» в шапке: разделы и категории (без товаров). */
class CatalogNavComposer
{
    public function compose(View $view): void
    {
        $view->with('catalogNav', $this->build());
    }

    /**
     * @return array{chapters: array<string, array<string, mixed>>, categories: array<string, array<string, mixed>>}
     */
    private function build(): array
    {
        /** @var array<string, array<string, mixed>> $categories */
        $categories = config('shop.categories', []);

        /** @var array<string, array<string, mixed>> $chapters */
        $chapters = config('shop.chapters', []);

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
                ])
                ->all(),
        ];
    }
}
