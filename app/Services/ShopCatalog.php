<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Chapter;
use App\Models\Product;
use App\Models\Promotion;
use App\Services\Pricing\PriceCalculator;

/** Каталог лавки и доска акций: всё из БД, цены — через PriceCalculator. */
class ShopCatalog
{
    /** @var array{chapters: array<string, array<string, mixed>>, categories: array<string, array<string, mixed>>}|null */
    private ?array $catalog = null;

    /** @var list<array{id: int, title: string, note: string|null, color: string, ends: int|null}>|null */
    private ?array $promos = null;

    public function __construct(private readonly PriceCalculator $prices) {}

    public function firstChapter(): ?string
    {
        return array_key_first($this->catalogData()['chapters']);
    }

    /**
     * Акции, которые действуют прямо сейчас: сначала те, что скоро закончатся.
     * ends — метка в мс для таймера в браузере.
     *
     * @return list<array{id: int, title: string, note: string|null, color: string, ends: int|null}>
     */
    public function activePromos(): array
    {
        return $this->promos ??= Promotion::query()
            ->available()
            ->orderByRaw('ends_at is null')
            ->orderBy('ends_at')
            ->orderBy('id')
            ->get()
            ->map(fn (Promotion $p) => [
                'id' => $p->id,
                'title' => $p->title,
                'note' => $p->note,
                'color' => $p->color,
                'ends' => $p->ends_at?->getTimestampMs(),
            ])
            ->values()
            ->all();
    }

    /**
     * Товар для витрины, карточки и корзины: цена уже со скидкой, old_price — до неё.
     * stock — точный остаток, low — осталось мало (подписывается красным).
     *
     * @return array{id: int, name: string, icon: string, price: int, old_price: int|null, badge: string|null, color: string|null, stock: int, low: bool}
     */
    public function presentProduct(Product $product, ?string $fallbackIcon = null): array
    {
        $price = $this->prices->priceFor($product);
        $promotion = $price->hasDiscount() ? $price->promotion : null;

        return [
            'id' => $product->id,
            'name' => $product->name,
            'icon' => $product->icon ?: (string) $fallbackIcon,
            'price' => $price->final,
            'old_price' => $promotion ? $price->base : null,
            'badge' => $promotion?->benefit(),
            'color' => $promotion?->color,
            'stock' => $product->stock,
            'low' => $product->isLowStock(),
        ];
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
                        ->map(fn (Product $p) => $this->presentProduct($p, $category->icon))
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
