<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Заказ. Создаёт только OrderPlacer: суммы копируются из расчёта корзины
 * на момент покупки. total = subtotal − items_discount − code_discount + delivery_price,
 * ровно столько списано с кошелька.
 *
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
 * @property-read User|null $user
 * @property-read Promotion|null $promotion
 * @property-read Collection<int, OrderItem> $items
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

    /**
     * Промокод, применённый к заказу.
     *
     * @return BelongsTo<Promotion, $this>
     */
    public function promotion(): BelongsTo
    {
        return $this->belongsTo(Promotion::class);
    }

    /** @return HasMany<OrderItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /** Номер для людей: «№ 15». */
    public function number(): string
    {
        return '№ '.$this->id;
    }
}
