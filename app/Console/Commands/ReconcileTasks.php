<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\TaskStatus;
use App\Models\Task;
use App\Services\Tasks\TaskCompletedPublisher;
use App\Services\Tasks\TaskDispatcher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;

final class ReconcileTasks extends Command
{
    protected $signature = 'tasks:reconcile';

    protected $description = 'Находит зависшие задачи и возвращает их в работу';

    public function handle(TaskDispatcher $dispatcher, TaskCompletedPublisher $completedPublisher): int
    {
        $staleBefore = now()->subMinutes(Config::integer('tasks.stale_after_minutes'));
        $maxAttempts = Config::integer('tasks.max_attempts');

        // 1. Зависла, и попытки кончились: честно сообщаем пользователю об ошибке
        $failed = Task::query()
            ->where('status', TaskStatus::Processing->value)
            ->where('started_at', '<', $staleBefore)
            ->where('attempts', '>=', $maxAttempts)
            ->update([
                'status' => TaskStatus::Failed->value,
                'error' => 'Обработка не завершилась за отведённое число попыток',
                'finished_at' => now(),
            ]);

        // 2. Зависла, но попытки есть: возвращаем в очередь
        $requeued = Task::query()
            ->where('status', TaskStatus::Processing->value)
            ->where('started_at', '<', $staleBefore)
            ->where('attempts', '<', $maxAttempts)
            ->update([
                'status' => TaskStatus::Pending->value,
                'started_at' => null,
                'published_at' => null,
            ]);

        // 3. Ждёт, но в Kafka так и не попала: публикуем
        $published = 0;
        Task::query()
            ->where('status', TaskStatus::Pending->value)
            ->whereNull('published_at')
            ->where('created_at', '<', now()->subSeconds(Config::integer('tasks.publish_grace_seconds')))
            ->orderBy('created_at')
            ->limit(100)
            ->get()
            ->each(function (Task $task) use ($dispatcher, &$published): void {
                if ($dispatcher->publish($task)) {
                    $published++;
                }
            });
        // 4. Завершена, но событие о завершении не ушло: публикуем
        $completed = 0;
        Task::query()
            ->whereIn('status', [TaskStatus::Done->value, TaskStatus::Failed->value])
            ->whereNull('completion_published_at')
            ->where('finished_at', '<', now()->subSeconds(Config::integer('tasks.publish_grace_seconds')))
            ->orderBy('finished_at')
            ->limit(100)
            ->get()
            ->each(function (Task $task) use ($completedPublisher, &$completed): void {
                if ($completedPublisher->publish($task)) {
                    $completed++;
                }
            });

        if ($failed + $requeued + $published + $completed > 0) {
            Log::warning('Сверка задач нашла проблемы', compact('failed', 'requeued', 'published', 'completed'));
        }

        $this->line("failed={$failed} requeued={$requeued} published={$published} completed={$completed}");

        return self::SUCCESS;
    }
}
