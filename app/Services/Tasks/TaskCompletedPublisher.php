<?php

// можно потом свести в класс TaskEvents. Объединить с TaskDispatcher
declare(strict_types=1);

namespace App\Services\Tasks;

use App\Models\Task;
use Junges\Kafka\Facades\Kafka;
use Throwable;

final class TaskCompletedPublisher
{
    public const TOPIC = 'tasks.completed';

    public function publish(Task $task): bool
    {
        try {
            Kafka::publish()
                ->onTopic(self::TOPIC)
                ->withKafkaKey((string) $task->user_id)
                ->withHeaders(['schema-version' => '1'])
                ->withBodyKey('task_id', $task->id)
                ->withBodyKey('status', $task->status->value)
                ->withBodyKey('finished_at', $task->finished_at?->toIso8601String())
                ->send();
        } catch (Throwable $e) {
            report($e);

            return false;
        }

        $task->forceFill(['completion_published_at' => now()])->save();

        return true;
    }
}
