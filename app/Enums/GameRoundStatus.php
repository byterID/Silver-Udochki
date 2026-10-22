<?php

declare(strict_types=1);

namespace App\Enums;

enum GameRoundStatus: string
{
    case Playing = 'playing';
    case Finished = 'finished';
    case Abandoned = 'abandoned'; // начат новый раунд, этот не закончен
    case Expired = 'expired';     // итог пришёл слишком поздно
    case Rejected = 'rejected';   // выстрелы не прошли проверку ShotJudge
}
