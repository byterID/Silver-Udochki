<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\TaskStatus;
use App\Models\Task;
use Illuminate\Notifications\Notification;

final class TaskFinished extends Notification
{
    public function __construct(
        private readonly Task $task,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        $label = $this->task->type->label();

        return [
            'task_id' => $this->task->id,
            'status' => $this->task->status->value,
            'message' => $this->task->status === TaskStatus::Done
                ? "«{$label}» готов, можно скачать результат"
                : "«{$label}» завершился с ошибкой",
        ];
    }
}
