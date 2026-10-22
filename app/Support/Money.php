<?php

declare(strict_types=1);

namespace App\Support;

final class Money
{
    public static function format(int $amount): string
    {
        return number_format($amount, 0, ',', ' ').' ₽';
    }
}
