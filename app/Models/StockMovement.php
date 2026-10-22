<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\StockReason;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $product_id
 * @property string $sku
 * @property string $name
 * @property int $delta
 * @property int $stock_after
 * @property StockReason $reason
 * @property int|null $user_id
 * @property string|null $note
 * @property Carbon $created_at
 * @property-read Product|null $product
 * @property-read User|null $user
 */
class StockMovement extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'product_id', 'sku', 'name', 'delta', 'stock_after', 'reason', 'user_id', 'note',
    ];

    protected function casts(): array
    {
        return [
            'delta' => 'integer',
            'stock_after' => 'integer',
            'reason' => StockReason::class,
            'created_at' => 'datetime',
        ];
    }

    // Товара может уже не быть в каталоге: остаются sku и name
    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
