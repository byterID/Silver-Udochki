<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $slug
 * @property string $title
 * @property string $icon
 * @property int $sort
 * @property-read Collection<int, Category> $categories
 */
class Chapter extends Model
{
    protected $fillable = ['slug', 'title', 'icon', 'sort'];

    /** @return HasMany<Category, $this> */
    public function categories(): HasMany
    {
        return $this->hasMany(Category::class)->orderBy('sort');
    }
}
