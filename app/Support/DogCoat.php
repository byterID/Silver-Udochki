<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Окрас собаки пользователя: на аватаре в шапке и у ретривера в мини-игре.
 * Выбирается по id, чтобы не менялся между визитами.
 */
final class DogCoat
{
    private const COATS = [
        ['head' => '#a0632e', 'ears' => '#6b3f1d'], // рыжий спаниель
        ['head' => '#3f3a36', 'ears' => '#1f1b18'], // чёрный лабрадор
        ['head' => '#d9a066', 'ears' => '#8a5a2b'], // палевый ретривер
    ];

    /** @return array{head: string, ears: string} */
    public static function for(int $seed): array
    {
        return self::COATS[abs($seed) % count(self::COATS)];
    }
}
