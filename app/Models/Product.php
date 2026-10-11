<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
 */
class Product extends Model
{
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

    /** @param Builder<Product> $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }
}
