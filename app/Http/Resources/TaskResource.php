<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\TaskStatus;
use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Config;

/** @mixin Task */
final class TaskResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $available = $this->status === TaskStatus::Done && $this->result_path !== null;

        // copy() обязателен: datetime-cast в Laravel даёт изменяемый Carbon,
        // и addMinutes() без copy() сдвинул бы само поле finished_at у модели.
        $expiresAt = $this->finished_at?->copy()->addMinutes(Config::integer('tasks.result_ttl_minutes'));

        return [
            'id' => $this->id,
            'type_label' => $this->type->label(),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'finished' => $this->status->isFinished(),
            'notified' => $this->notified_at !== null,       // новое: нотификатор отработал
            'created_at' => $this->created_at->toIso8601String(),
            'finished_at' => $this->finished_at?->toIso8601String(),
            'download_url' => $available ? route('tasks.download', $this->resource) : null,
            // новое: сколько секунд осталось до удаления файла
            'expires_in' => $available && $expiresAt
                ? max(0, (int) now()->diffInSeconds($expiresAt, false))
                : null,
            // новое: задача выполнена, но файл уже удалён
            'expired' => $this->status === TaskStatus::Done && $this->result_path === null,
            'error' => $this->status === TaskStatus::Failed
                ? 'Не удалось выполнить задачу. Попробуйте ещё раз.'
                : null,
        ];
    }
}
