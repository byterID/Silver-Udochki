<?php

declare(strict_types=1);

namespace App\Services\Game;

use App\Enums\GameRoundStatus;
use App\Enums\WalletReason;
use App\Models\GameRound;
use App\Models\User;
use App\Services\Wallet\WalletKeeper;
use Illuminate\Support\Facades\DB;

/**
 * Раунды мини-игры: выдаёт полёты, принимает выстрелы и начисляет награду.
 * Деньги считаются только здесь, по раскладке раунда и суточному лимиту.
 *
 * @phpstan-import-type Shot from ShotJudge
 */
class GameMaster
{
    /** Запас на задержку сети: итог может прийти чуть раньше расчётного конца раунда. */
    public const EARLY_MS = 2000;

    public function __construct(
        private readonly FlightPlanner $planner,
        private readonly ShotJudge $judge,
        private readonly WalletKeeper $wallets,
    ) {}

    /** Новый раунд. Незаконченный прежний сгорает: два раунда сразу не сыграть. */
    public function start(User $user): GameRound
    {
        return DB::transaction(function () use ($user): GameRound {
            // Блокировка пользователя: две вкладки не откроют два раунда одновременно
            User::query()->lockForUpdate()->findOrFail($user->id);

            $user->gameRounds()
                ->where('status', GameRoundStatus::Playing)
                ->update(['status' => GameRoundStatus::Abandoned, 'finished_at' => now()]);

            return $user->gameRounds()->create([
                'status' => GameRoundStatus::Playing,
                'plan' => $this->planner->plan(),
                'started_at' => now(),
            ]);
        });
    }

    /**
     * Итог раунда. Повторный вызов возвращает уже подведённый итог.
     *
     * @param  list<Shot>  $shots
     *
     * @throws RoundStillRunning утки ещё летят
     */
    public function finish(GameRound $round, array $shots): GameRound
    {
        return DB::transaction(function () use ($round, $shots): GameRound {
            // Сначала пользователь, потом раунд — в том же порядке, что и в start()
            $user = User::query()->lockForUpdate()->findOrFail($round->user_id);
            $round = GameRound::query()->lockForUpdate()->findOrFail($round->id);

            if ($round->status !== GameRoundStatus::Playing) {
                return $round;
            }

            if (now()->lt($round->endsAt()->subMilliseconds(self::EARLY_MS))) {
                throw new RoundStillRunning('Раунд ещё идёт.');
            }

            if (now()->gt($round->endsAt()->addSeconds((int) config('game.grace_seconds')))) {
                return $this->close($round, GameRoundStatus::Expired, count($shots));
            }

            try {
                $outcome = $this->judge->judge($round->plan, $shots);
            } catch (ShotsRejected) {
                return $this->close($round, GameRoundStatus::Rejected, count($shots));
            }

            $hits = count($outcome['hits']);
            $reward = min($outcome['score'] * $round->plan['rate'], $this->dailyLeft($user));

            $round = $this->close($round, GameRoundStatus::Finished, count($shots), $hits, $outcome['score'], $reward);

            if ($reward > 0) {
                $this->wallets->credit($user, $reward, WalletReason::Game, 'game:'.$round->id,
                    sprintf('Охота: %d из %d уток', $hits, count($round->plan['ducks'])));
            }

            return $round;
        });
    }

    /** Сколько ещё можно заработать за скользящие 24 часа. */
    public function dailyLeft(User $user): int
    {
        $earned = (int) $user->gameRounds()
            ->where('status', GameRoundStatus::Finished)
            ->where('finished_at', '>=', now()->subDay())
            ->sum('reward');

        return max(0, (int) config('game.daily_limit') - $earned);
    }

    private function close(GameRound $round, GameRoundStatus $status, int $shots, int $hits = 0, int $score = 0, int $reward = 0): GameRound
    {
        $round->forceFill([
            'status' => $status,
            'shots' => $shots,
            'hits' => $hits,
            'score' => $score,
            'reward' => $reward,
            'finished_at' => now(),
        ])->save();

        return $round;
    }
}
