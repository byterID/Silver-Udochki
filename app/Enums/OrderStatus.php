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

    public function label(): string
    {
        return match ($this) {
            self::New => 'Новый',
            self::Paid => 'Оплачен',
            self::Confirmed => 'Подтверждён',
            self::Shipped => 'Передан в доставку',
            self::Completed => 'Выполнен',
            self::Cancelled => 'Отменён',
        };
    }
}
