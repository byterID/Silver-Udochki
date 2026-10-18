<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Статус заказа. Заказ оплачивается кошельком сразу при оформлении,
 * поэтому новый заказ создаётся уже оплаченным (Paid).
 */
enum OrderStatus: string
{
    case New = 'new';
    case Paid = 'paid';
    case Confirmed = 'confirmed';
    case Shipped = 'shipped';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    /** Часть товаров вернули, остальное можно вернуть позже. */
    case PartiallyRefunded = 'partially_refunded';

    /** Вернули всё: деньги и доставка на кошельке. */
    case Refunded = 'refunded';

    public function label(): string
    {
        return match ($this) {
            self::New => 'Новый',
            self::Paid => 'Оплачен',
            self::Confirmed => 'Подтверждён',
            self::Shipped => 'Передан в доставку',
            self::Completed => 'Выполнен',
            self::Cancelled => 'Отменён',
            self::PartiallyRefunded => 'Частичный возврат',
            self::Refunded => 'Возвращён',
        };
    }

    /** Оплаченный заказ, по которому ещё можно оформить возврат. */
    public function isRefundable(): bool
    {
        return in_array($this, [self::Paid, self::Confirmed, self::Shipped, self::Completed, self::PartiallyRefunded], true);
    }
}
