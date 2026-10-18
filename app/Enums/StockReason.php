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

    /** Товар ушёл покупателю по заказу. */
    case Sale = 'sale';

    /** Покупатель вернул товар по заказу: остаток растёт. */
    case Returned = 'return';

    /**
     * Что можно сделать руками в панели склада. Продажи и возвраты проводят только заказы.
     *
     * @return list<self>
     */
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
