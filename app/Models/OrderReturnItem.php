<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Сколько штук строки заказа вернули этим возвратом.
 *
 * @property int $id
 * @property int $order_return_id
 * @property int $order_item_id
 * @property int $quantity
 * @property-read OrderReturn $orderReturn
 * @property-read OrderItem $orderItem
 */
class OrderReturnItem extends Model
{
    public $timestamps = false;

    protected $fillable = ['order_return_id', 'order_item_id', 'quantity'];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
        ];
    }

    /** @return BelongsTo<OrderReturn, $this> */
    public function orderReturn(): BelongsTo
    {
        return $this->belongsTo(OrderReturn::class);
    }

    /** @return BelongsTo<OrderItem, $this> */
    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }
}
