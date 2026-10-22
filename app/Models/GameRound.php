<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\GameRoundStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @phpstan-type Duck array{id: int, kind: string, points: int, radius: int, path: list<array{0: int, 1: int, 2: int}>}
 * @phpstan-type Plan array{field: array{width: int, height: int, horizon: int}, duration_ms: int, magazine: int, reload_ms: int, min_interval_ms: int, rate: int, ducks: list<Duck>}
 *
 * @property string $id UUID
 * @property int $user_id
 * @property GameRoundStatus $status
 * @property Plan $plan
 * @property int $shots
 * @property int $hits
 * @property int $score
 * @property int $reward
 * @property Carbon $started_at
 * @property Carbon|null $finished_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read User $user
 */
class GameRound extends Model
{
    use HasUuids;

    protected $fillable = ['status', 'plan', 'started_at'];

    protected function casts(): array
    {
        return [
            'status' => GameRoundStatus::class,
            'plan' => 'array',
            'shots' => 'integer',
            'hits' => 'integer',
            'score' => 'integer',
            'reward' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function endsAt(): Carbon
    {
        return $this->started_at->copy()->addMilliseconds($this->plan['duration_ms']);
    }
}
