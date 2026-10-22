<?php

declare(strict_types=1);

namespace App\Enums;

enum PromotionType: string
{
    case Percent = 'percent';            // на выбранные товары, без кода
    case Fixed = 'fixed';                // промокод на сумму со всего заказа
    case FreeDelivery = 'free_delivery'; // от min_order_amount

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
