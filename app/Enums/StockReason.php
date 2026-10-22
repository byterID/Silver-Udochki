<?php

declare(strict_types=1);

namespace App\Enums;

enum StockReason: string
{
    case Restock = 'restock';
    case Inventory = 'inventory'; // остаток ставится точным числом
    case Sale = 'sale';
    case Returned = 'return';

    // Продажи и возвраты проводят только заказы, руками в панели — остальное
    /** @return list<self> */
    public static function manual(): array
    {
        return [self::Restock, self::Inventory];
    }

    public function label(): string
    {
        return match ($this) {
            self::Restock => 'Поступление',
            self::Inventory => 'Инвентаризация',
            self::Sale => 'Продажа',
            self::Returned => 'Возврат',
        };
    }
}
