<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\GameRound;
use App\Models\User;

class GameRoundPolicy
{
    /** Стрелять и забирать награду может только тот, кто начал раунд. */
    public function play(User $user, GameRound $round): bool
    {
        return $round->user_id === $user->id;
    }
}
