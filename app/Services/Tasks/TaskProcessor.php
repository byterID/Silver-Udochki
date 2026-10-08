<?php

declare(strict_types=1);

namespace App\Services\Tasks;

use App\Enums\TaskStatus;
use App\Models\Task;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Junges\Kafka\Contracts\ConsumerMessage;
use Junges\Kafka\Facades\Kafka;
use Throwable;

final class TaskProcessor
{
    public const DLQ_TOPIC = 'tasks.requested.dlq';

    public function __construct(
        private readonly TaskCompletedPublisher $completed,
    ) {}

    public function handle(ConsumerMessage $message): void
    {
        // 1. Проверяем, что сообщение вообще похоже на наше
        $body = $message->getBody();
        $taskId = is_array($body) ? ($body['task_id'] ?? null) : null;

        if (! is_string($taskId) || ! Str::isUuid($taskId)) {
            $this->toDeadLetter($message, 'нет корректного task_id');

            return;
        }

        // 2. Атомарно захватываем задачу
        $task = $this->claim($taskId);
        if ($task === null) {
            Log::info('Задача уже обработана или в работе, пропускаю', ['task_id' => $taskId]);

            return;
        }

        Log::info('Взял задачу', ['task_id' => $task->id, 'partition' => $message->getPartition(), 'offset' => $message->getOffset()]);

        // 3. Выполняем
        try {
            $path = app($task->type->runnerClass())->run($task);

            $task->forceFill([
                'status' => TaskStatus::Done,
                'result_path' => $path,
                'finished_at' => now(),
            ])->save();
        } catch (Throwable $e) {
            $task->forceFill([
                'status' => TaskStatus::Failed,
                'error' => Str::limit($e->getMessage(), 1000),
                'finished_at' => now(),
            ])->save();

            report($e);
        }
        // 4. Сообщаем миру о завершении (и для done, и для failed)
        $this->completed->publish($task);
    }

    private function claim(string $taskId): ?Task
    {
        $claimed = Task::query()
            ->whereKey($taskId)
            ->where('status', TaskStatus::Pending->value)
            ->where('attempts', '<', Config::integer('tasks.max_attempts'))
            ->update([
                'status' => TaskStatus::Processing->value,
                'started_at' => now(),
                'attempts' => DB::raw('attempts + 1'),
            ]);

        return $claimed === 1 ? Task::find($taskId) : null;
    }

    private function toDeadLetter(ConsumerMessage $message, string $reason): void
    {
        Kafka::publish()
            ->onTopic(self::DLQ_TOPIC)
            ->withBodyKey('reason', $reason)
            ->withBodyKey('original', $message->getBody())
            ->withBodyKey('source_partition', $message->getPartition())
            ->withBodyKey('source_offset', $message->getOffset())
            ->send();

        Log::warning('Сообщение отправлено в DLQ', ['reason' => $reason, 'offset' => $message->getOffset()]);
    }
}
