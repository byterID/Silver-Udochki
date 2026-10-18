<?php

declare(strict_types=1);

namespace App\Enums;

/** Причина движения по складу: пишется в журнал stock_movements. */
enum StockReason: string
{
    /** Пришёл товар: остаток увеличивается. */
    case Restock = 'restock';

    /** Пересчитали полку: остаток ставится точным числом. */
    case Inventory = 'inventory';

    public function label(): string
    {
        return match ($this) {
            self::Restock => 'Поступление',
            self::Inventory => 'Инвентаризация',
        };
    }
}
