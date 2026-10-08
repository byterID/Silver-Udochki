<?php

declare(strict_types=1);

namespace App\Services\Tasks;

use App\Enums\TaskStatus;
use App\Models\Task;
use App\Notifications\TaskFinished;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Junges\Kafka\Contracts\ConsumerMessage;

final class TaskNotifier
{
    public function handle(ConsumerMessage $message): void
    {
        $body = $message->getBody();
        $taskId = is_array($body) ? ($body['task_id'] ?? null) : null;

        if (! is_string($taskId) || ! Str::isUuid($taskId)) {
            Log::warning('Некорректное сообщение в tasks.completed, пропускаю', [
                'partition' => $message->getPartition(),
                'offset' => $message->getOffset(),
            ]);

            return;
        }

        $sent = DB::transaction(function () use ($taskId): bool {
            $task = Task::query()
                ->whereKey($taskId)
                ->whereNull('notified_at')
                ->whereIn('status', [TaskStatus::Done->value, TaskStatus::Failed->value])
                ->lockForUpdate()
                ->first();

            if ($task === null) {
                return false;
            }

            $task->user->notify(new TaskFinished($task));
            $task->forceFill(['notified_at' => now()])->save();

            return true;
        });

        Log::info($sent ? 'Уведомление отправлено' : 'Уведомление уже было, пропускаю', ['task_id' => $taskId]);
    }
}
