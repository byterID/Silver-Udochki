<?php

declare(strict_types=1);

namespace App\Enums;

enum WalletReason: string
{
    case Welcome = 'welcome';
    case Purchase = 'purchase';
    case Refund = 'refund';
    case Game = 'game';

    public function label(): string
    {
        return match ($this) {
            self::Welcome => 'Подарок при регистрации',
            self::Purchase => 'Оплата заказа',
            self::Refund => 'Возврат по заказу',
            self::Game => 'Награда за охоту',
        };
    }
}
