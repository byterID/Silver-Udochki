<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ReturnReason;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Возврат по заказу. Создаёт только OrderRefunder: товары уже на складе,
 * $amount уже на кошельке покупателя.
 *
 * @property int $id
 * @property int $order_id
 * @property int|null $user_id
 * @property ReturnReason $reason
 * @property string|null $comment
 * @property int $amount
 * @property string $return_key
 * @property Carbon $created_at
 * @property-read Order $order
 * @property-read User|null $user
 * @property-read Collection<int, OrderReturnItem> $items
 */
class OrderReturn extends Model
{
    protected $fillable = ['order_id', 'user_id', 'reason', 'comment', 'amount', 'return_key'];

    protected $hidden = ['return_key'];

    protected function casts(): array
    {
        return [
            'reason' => ReturnReason::class,
            'amount' => 'integer',
        ];
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<OrderReturnItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(OrderReturnItem::class);
    }
}
