<?php

declare(strict_types=1);

namespace App\Enums;

// Заказ оплачивается кошельком при оформлении, поэтому создаётся сразу Paid
enum OrderStatus: string
{
    case New = 'new';
    case Paid = 'paid';
    case Confirmed = 'confirmed';
    case Shipped = 'shipped';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case PartiallyRefunded = 'partially_refunded';
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

    public function isRefundable(): bool
    {
        return in_array($this, [self::Paid, self::Confirmed, self::Shipped, self::Completed, self::PartiallyRefunded], true);
    }
}
