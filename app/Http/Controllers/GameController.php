<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\DuckKind;
use App\Enums\GameRoundStatus;
use App\Http\Requests\FinishGameRoundRequest;
use App\Models\GameRound;
use App\Models\User;
use App\Services\Game\GameMaster;
use App\Services\Game\RoundStillRunning;
use App\Services\Wallet\WalletKeeper;
use App\Support\DogCoat;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GameController extends Controller
{
    public const RECENT = 5;

    public function __construct(private readonly GameMaster $game) {}

    public function index(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();
        $rate = (int) config('game.rate');

        return view('game.index', [
            'kinds' => array_map(fn (DuckKind $kind) => [
                'label' => $kind->label(),
                'points' => $kind->points(),
                'reward' => $kind->points() * $rate,
            ], DuckKind::cases()),
            'dailyLimit' => (int) config('game.daily_limit'),
            'setup' => [
                'startUrl' => route('game.rounds.store'),
                'dailyLeft' => $this->game->dailyLeft($user),
                'recentLimit' => self::RECENT,
                'recent' => $user->gameRounds()
                    ->where('status', GameRoundStatus::Finished)
                    ->latest('finished_at')
                    ->limit(self::RECENT)
                    ->get()
                    ->map(fn (GameRound $round) => [
                        'at' => $round->finished_at?->format('d.m H:i'),
                        'hits' => $round->hits,
                        'ducks' => count($round->plan['ducks']),
                        'score' => $round->score,
                        'reward' => $round->reward,
                    ]),
                'quips' => config('game.quips'),
                'coat' => DogCoat::for($user->id),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $round = $this->game->start($user);

        return response()->json([
            'id' => $round->id,
            'finish_url' => route('game.rounds.finish', $round),
            'plan' => $round->plan,
        ], 201);
    }

    // Повтор возвращает тот же итог. 425 — утки ещё летят, итог можно прислать позже
    public function finish(FinishGameRoundRequest $request, GameRound $round, WalletKeeper $wallets): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        try {
            $round = $this->game->finish($round, $request->shots());
        } catch (RoundStillRunning $e) {
            return response()->json(['message' => $e->getMessage()], 425);
        }

        return match ($round->status) {
            GameRoundStatus::Finished => response()->json([
                'hits' => $round->hits,
                'ducks' => count($round->plan['ducks']),
                'score' => $round->score,
                'reward' => $round->reward,
                'daily_left' => $this->game->dailyLeft($user),
                'balance' => $wallets->walletOf($user)->balance,
            ]),
            GameRoundStatus::Abandoned => response()->json(['message' => 'Этот раунд закрыт: вы начали новый.'], 409),
            GameRoundStatus::Expired => response()->json(['message' => 'Итог пришёл слишком поздно, раунд сгорел.'], 410),
            GameRoundStatus::Rejected => response()->json(['message' => 'Выстрелы не прошли проверку, раунд без награды.'], 422),
            GameRoundStatus::Playing => abort(500),
        };
    }
}
