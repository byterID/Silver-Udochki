<?php

declare(strict_types=1);

namespace App\Enums;

/** Раунд мини-игры. Награду получает только Finished. */
enum GameRoundStatus: string
{
    /** Утки в воздухе, ждём выстрелы. */
    case Playing = 'playing';

    /** Выстрелы проверены, награда начислена. */
    case Finished = 'finished';

    /** Игрок начал новый раунд, не закончив этот. */
    case Abandoned = 'abandoned';

    /** Итог пришёл слишком поздно. */
    case Expired = 'expired';

    /** Выстрелы не прошли проверку: так быстро двустволка не стреляет. */
    case Rejected = 'rejected';
}
