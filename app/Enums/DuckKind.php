<?php

declare(strict_types=1);

namespace App\Enums;

enum DuckKind: string
{
    case Mallard = 'mallard';
    case Teal = 'teal';
    case Golden = 'golden';

    public function label(): string
    {
        return match ($this) {
            self::Mallard => 'Кряква',
            self::Teal => 'Чирок',
            self::Golden => 'Золотой селезень',
        };
    }

    public function points(): int
    {
        return match ($this) {
            self::Mallard => 1,
            self::Teal => 2,
            self::Golden => 5,
        };
    }

    // Единиц поля в секунду
    public function speed(): int
    {
        return match ($this) {
            self::Mallard => 230,
            self::Teal => 320,
            self::Golden => 410,
        };
    }

    public function radius(): int
    {
        return match ($this) {
            self::Mallard => 34,
            self::Teal => 27,
            self::Golden => 25,
        };
    }

    // Относительная частота в раскладке
    public function weight(): int
    {
        return match ($this) {
            self::Mallard => 65,
            self::Teal => 28,
            self::Golden => 7,
        };
    }
}
