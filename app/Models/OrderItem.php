<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Строка заказа: цена, скидка и название товара на момент покупки.
 *
 * @property int $id
 * @property int $order_id
 * @property int|null $product_id
 * @property string $sku
 * @property string $name
 * @property int $unit_price
 * @property int $unit_discount
 * @property int $quantity
 * @property int|null $promotion_id
 * @property-read Order $order
 * @property-read Product|null $product
 * @property-read Promotion|null $promotion
 */
class OrderItem extends Model
{
    protected $fillable = [
        'order_id', 'product_id', 'sku', 'name', 'unit_price', 'unit_discount', 'quantity', 'promotion_id',
    ];

    protected function casts(): array
    {
        return [
            'unit_price' => 'integer',
            'unit_discount' => 'integer',
            'quantity' => 'integer',
        ];
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Товар может быть уже удалён из каталога: тогда остаются sku и name.
     *
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return BelongsTo<Promotion, $this> */
    public function promotion(): BelongsTo
    {
        return $this->belongsTo(Promotion::class);
    }

    public function lineTotal(): int
    {
        return ($this->unit_price - $this->unit_discount) * $this->quantity;
    }
}
