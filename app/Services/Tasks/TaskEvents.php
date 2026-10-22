<?php

declare(strict_types=1);

namespace App\Services\Tasks;

use App\Models\Task;
use Junges\Kafka\Facades\Kafka;
use Throwable;

// Ошибка Kafka не роняет запрос: метка *_published_at остаётся пустой, и tasks:reconcile отправит событие позже
final class TaskEvents
{
    public const REQUESTED = 'tasks.requested';

    public const COMPLETED = 'tasks.completed';

    public const DEAD_LETTER = 'tasks.requested.dlq';

    public function requested(Task $task): bool
    {
        return $this->send($task, self::REQUESTED, ['type' => $task->type->value], 'published_at');
    }

    public function completed(Task $task): bool
    {
        return $this->send($task, self::COMPLETED, [
            'status' => $task->status->value,
            'finished_at' => $task->finished_at?->toIso8601String(),
        ], 'completion_published_at');
    }

    /** @param array<string, mixed> $body */
    private function send(Task $task, string $topic, array $body, string $sentAt): bool
    {
        try {
            $message = Kafka::publish()
                ->onTopic($topic)
                ->withKafkaKey((string) $task->user_id)
                ->withHeaders(['schema-version' => '1'])
                ->withBodyKey('task_id', $task->id);

            foreach ($body as $key => $value) {
                $message->withBodyKey($key, $value);
            }

            $message->send();
        } catch (Throwable $e) {
            report($e);

            return false;
        }

        $task->forceFill([$sentAt => now()])->save();

        return true;
    }
}
