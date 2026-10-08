<?php

declare(strict_types=1);

namespace App\Services\Tasks;

use App\Enums\TaskType;
use App\Models\Task;
use App\Models\User;
use Junges\Kafka\Facades\Kafka;
use Throwable;

final class TaskDispatcher
{
    public const TOPIC = 'tasks.requested';

    public function dispatch(User $user, TaskType $type, array $payload): Task
    {
        // 1. Сначала тикет в БД: это источник правды
        $task = Task::create([
            'user_id' => $user->id,
            'type' => $type,
            'payload' => $payload,
        ]);

        // 2. Потом событие в Kafka
        $this->publish($task);

        return $task;
    }

    public function publish(Task $task): bool
    {
        try {
            Kafka::publish()
                ->onTopic(self::TOPIC)
                ->withKafkaKey((string) $task->user_id)
                ->withHeaders(['schema-version' => '1'])
                ->withBodyKey('task_id', $task->id)
                ->withBodyKey('type', $task->type->value)
                ->send();
        } catch (Throwable $e) {
            report($e);

            return false;
        }

        $task->forceFill(['published_at' => now()])->save();

        return true;
    }
}
