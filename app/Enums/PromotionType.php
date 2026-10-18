<?php

declare(strict_types=1);

namespace App\Enums;

enum PromotionType: string
{
    /** Скидка в процентах на выбранные товары, действует сама. */
    case Percent = 'percent';

    /** Фиксированная сумма со всего заказа по промокоду. */
    case Fixed = 'fixed';

    /** Бесплатная доставка, если заказ не меньше порога. */
    case FreeDelivery = 'free_delivery';

    public function label(): string
    {
        return match ($this) {
            self::Percent => 'Скидка в процентах на товары',
            self::Fixed => 'Промокод на сумму',
            self::FreeDelivery => 'Бесплатная доставка',
        };
    }

    public function shortLabel(): string
    {
        return match ($this) {
            self::Percent => 'Скидка %',
            self::Fixed => 'Промокод',
            self::FreeDelivery => 'Доставка',
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(fn (self $t) => $t->value, self::cases());
    }
}
