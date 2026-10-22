<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Carbon;

// total = subtotal − items_discount − code_discount + delivery_price, ровно столько списано с кошелька.
// Возвраты суммы заказа не меняют: они лежат в order_returns.
/**
 * @property int $id
 * @property int|null $user_id
 * @property OrderStatus $status
 * @property int $subtotal
 * @property int $items_discount
 * @property int $code_discount
 * @property int $delivery_price
 * @property int $total
 * @property int|null $promotion_id
 * @property string|null $promo_code
 * @property string|null $checkout_key
 * @property Carbon $created_at
 * @property-read int|string|null $refunded сумма возвратов из withReturns()
 * @property-read User|null $user
 * @property-read Promotion|null $promotion
 * @property-read Collection<int, OrderItem> $items
 * @property-read Collection<int, OrderReturn> $returns
 */
class Order extends Model
{
    protected $fillable = [
        'user_id', 'status', 'subtotal', 'items_discount', 'code_discount',
        'delivery_price', 'total', 'promotion_id', 'promo_code', 'checkout_key',
    ];

    protected $hidden = ['checkout_key'];

    protected $attributes = [
        'status' => 'new',
    ];

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'subtotal' => 'integer',
            'items_discount' => 'integer',
            'code_discount' => 'integer',
            'delivery_price' => 'integer',
            'total' => 'integer',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Promotion, $this> */
    public function promotion(): BelongsTo
    {
        return $this->belongsTo(Promotion::class);
    }

    /** @return HasMany<OrderItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /** @return HasMany<OrderReturn, $this> */
    public function returns(): HasMany
    {
        return $this->hasMany(OrderReturn::class);
    }

    // Счётчики возвратов для refundedAmount(), returnedQuantity() и isReturnable()
    /** @param  Builder<Order>  $query */
    public function scopeWithReturns(Builder $query): void
    {
        $query->with(['items' => fn (Relation $items) => $items->withSum('returnItems as returned_quantity', 'quantity')])
            ->withSum('returns as refunded', 'amount');
    }

    public function number(): string
    {
        return '№ '.$this->id;
    }

    public function refundedAmount(): int
    {
        return (int) $this->refunded;
    }

    public function returnDeadline(): Carbon
    {
        return $this->created_at->copy()->addDays((int) config('shop.return_days'));
    }

    public function isReturnable(): bool
    {
        return $this->status->isRefundable()
            && now()->lessThanOrEqualTo($this->returnDeadline())
            && $this->items->contains(fn (OrderItem $item) => $item->returnableQuantity() > 0);
    }
}
