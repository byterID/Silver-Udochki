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

/**
 * Заказ. Создаёт только OrderPlacer: суммы копируются из расчёта корзины
 * на момент покупки. total = subtotal − items_discount − code_discount + delivery_price,
 * ровно столько списано с кошелька. Возвраты (OrderRefunder) суммы заказа
 * не меняют: они записываются отдельно в order_returns.
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

    /** @return HasMany<OrderReturn, $this> */
    public function returns(): HasMany
    {
        return $this->hasMany(OrderReturn::class);
    }

    /**
     * Строки заказа со счётчиком возвращённых штук и общая сумма возвратов:
     * всё, что нужно окну «Мои заказы» и оформлению возврата.
     *
     * @param  Builder<Order>  $query
     */
    public function scopeWithReturns(Builder $query): void
    {
        $query->with(['items' => fn (Relation $items) => $items->withSum('returnItems as returned_quantity', 'quantity')])
            ->withSum('returns as refunded', 'amount');
    }

    /** Номер для людей: «№ 15». */
    public function number(): string
    {
        return '№ '.$this->id;
    }

    /** Сколько уже вернули на кошелёк. Нужен withReturns(). */
    public function refundedAmount(): int
    {
        return (int) $this->refunded;
    }

    /** До какого момента можно оформить возврат (shop.return_days с покупки). */
    public function returnDeadline(): Carbon
    {
        return $this->created_at->copy()->addDays((int) config('shop.return_days'));
    }

    /** Заказ оплачен, срок не вышел и ещё не всё вернули. Нужен withReturns(). */
    public function isReturnable(): bool
    {
        return $this->status->isRefundable()
            && now()->lessThanOrEqualTo($this->returnDeadline())
            && $this->items->contains(fn (OrderItem $item) => $item->returnableQuantity() > 0);
    }
}
