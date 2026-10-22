<?php

declare(strict_types=1);

namespace App\Services\Tasks;

use App\Enums\TaskType;
use App\Models\Task;
use App\Models\User;

final class TaskDispatcher
{
    public function __construct(private readonly TaskEvents $events) {}

    /** @param array<string, mixed> $payload */
    public function dispatch(User $user, TaskType $type, array $payload): Task
    {
        // Сначала строка в БД, она источник правды; событие можно дослать позже
        $task = Task::create([
            'user_id' => $user->id,
            'type' => $type,
            'payload' => $payload,
        ]);

        $this->events->requested($task);

        return $task;
    }
}
