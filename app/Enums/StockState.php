<?php

declare(strict_types=1);

namespace App\Enums;

// Фильтр склада: и список в панели, и отчёт об остатках
enum StockState: string
{
    case Low = 'low';
    case Out = 'out';

    public function label(): string
    {
        return match ($this) {
            self::Low => 'Осталось мало',
            self::Out => 'Нет в наличии',
        };
    }
}
