<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TaskStatus;
use App\Enums\TaskType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Типы — такие, какими поля становятся ПОСЛЕ кастов.
 *
 * @property string $id UUID
 * @property int $user_id
 * @property TaskType $type
 * @property TaskStatus $status
 * @property array<string, mixed>|null $payload
 * @property string|null $result_path
 * @property string|null $error
 * @property int $attempts
 * @property Carbon|null $published_at
 * @property Carbon|null $started_at
 * @property Carbon|null $finished_at
 * @property Carbon|null $completion_published_at
 * @property Carbon|null $notified_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read User $user
 */
class Task extends Model
{
    use HasUuids;

    protected $fillable = ['user_id', 'type', 'payload'];

    protected $attributes = [
        'status' => 'pending',
    ];

    protected function casts(): array
    {
        return [
            'type' => TaskType::class,
            'status' => TaskStatus::class,
            'payload' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'published_at' => 'datetime',
            'completion_published_at' => 'datetime',
            'notified_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
