<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Chapter;
use Illuminate\Support\Carbon;

/** Каталог лавки: главы, категории и товары из БД, акции из config/shop.php. */
class ShopCatalog
{
    /** @var array{chapters: array<string, array<string, mixed>>, categories: array<string, array<string, mixed>>}|null */
    private ?array $catalog = null;

    public function firstChapter(): ?string
    {
        return array_key_first($this->catalogData()['chapters']);
    }

    /**
     * Только акции, которые действуют прямо сейчас. ends — метка в мс для таймера в браузере.
     *
     * @return array<int, array{title: mixed, note: mixed, ends: int|null}>
     */
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

    /**
     * Данные для «монитора»: главы, категории и товары внутри них.
     *
     * @return array{chapters: array<string, array<string, mixed>>, categories: array<string, array<string, mixed>>}
     */
    public function catalogData(): array
    {
        if ($this->catalog !== null) {
            return $this->catalog;
        }

        $chapters = Chapter::query()
            ->with('categories.products')
            ->orderBy('sort')
            ->orderBy('id')
            ->get();

        $chapterData = [];
        $categoryData = [];

        foreach ($chapters as $chapter) {
            $slugs = [];

            foreach ($chapter->categories->sortBy('sort') as $category) {
                $slugs[] = $category->slug;

                $categoryData[$category->slug] = [
                    'title' => $category->title,
                    'icon' => $category->icon,
                    'description' => $category->description,
                    'url' => route('catalog.category', $category->slug),
                    'products' => $category->products
                        ->where('is_active', true)
                        ->sortBy('id')
                        ->map(fn ($p) => [
                            'id' => $p->id,
                            'name' => $p->name,
                            'price' => $p->price,
                            'icon' => $p->icon ?: $category->icon,
                        ])
                        ->values()
                        ->all(),
                ];
            }

            $chapterData[$chapter->slug] = [
                'title' => $chapter->title,
                'icon' => $chapter->icon,
                'categories' => $slugs,
            ];
        }

        return $this->catalog = [
            'chapters' => $chapterData,
            'categories' => $categoryData,
        ];
    }
}
