<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $chapter_id
 * @property string $slug
 * @property string $title
 * @property string $icon
 * @property string|null $description
 * @property int $sort
 * @property-read Chapter $chapter
 * @property-read Collection<int, Product> $products
 */
class Category extends Model
{
    /** @use HasFactory<CategoryFactory> */
    use HasFactory;

    protected $fillable = ['chapter_id', 'slug', 'title', 'icon', 'description', 'sort'];

    /** @return BelongsTo<Chapter, $this> */
    public function chapter(): BelongsTo
    {
        return $this->belongsTo(Chapter::class);
    }

    /** @return HasMany<Product, $this> */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
