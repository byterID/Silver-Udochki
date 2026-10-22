<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\StockState;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property int $id
 * @property int $category_id
 * @property string $sku
 * @property string $name
 * @property string|null $description
 * @property int $price
 * @property int $stock
 * @property string $icon
 * @property list<string>|null $tags
 * @property bool $is_active
 * @property-read Category $category
 * @property-read Collection<int, Promotion> $promotions
 */
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    protected $fillable = [
        'category_id', 'sku', 'name', 'description', 'price', 'stock', 'icon', 'tags', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'stock' => 'integer',
            'tags' => 'array',
            'is_active' => 'boolean',
        ];
    }

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    // Многие-ко-многим только по форме: product_id в promotion_product уникален
    /** @return BelongsToMany<Promotion, $this> */
    public function promotions(): BelongsToMany
    {
        return $this->belongsToMany(Promotion::class, 'promotion_product');
    }

    public function isLowStock(): bool
    {
        return $this->stock > 0 && $this->stock <= (int) config('shop.low_stock');
    }

    /** @param Builder<Product> $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /** @param Builder<Product> $query */
    public function scopeInStockState(Builder $query, StockState $state): void
    {
        $column = $query->qualifyColumn('stock');

        match ($state) {
            StockState::Low => $query->whereBetween($column, [1, (int) config('shop.low_stock')]),
            StockState::Out => $query->where($column, 0),
        };
    }
}
