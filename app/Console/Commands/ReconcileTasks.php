<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\TaskStatus;
use App\Models\Task;
use App\Services\Tasks\TaskEvents;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;

final class ReconcileTasks extends Command
{
    protected $signature = 'tasks:reconcile';

    protected $description = 'Находит зависшие задачи и возвращает их в работу';

    private int $publishErrors = 0;

    public function handle(TaskEvents $events): int
    {
        $staleBefore = now()->subMinutes(Config::integer('tasks.stale_after_minutes'));
        $graceBefore = now()->subSeconds(Config::integer('tasks.publish_grace_seconds'));
        $expireBefore = now()->subMinutes(Config::integer('tasks.pending_timeout_minutes'));
        $maxAttempts = Config::integer('tasks.max_attempts');
        $stale = fn () => Task::query()->where('status', TaskStatus::Processing->value)->where('started_at', '<', $staleBefore);
        $pending = fn () => Task::query()->where('status', TaskStatus::Pending->value);

        $failed = $stale()->where('attempts', '>=', $maxAttempts)->update([
            'status' => TaskStatus::Failed->value,
            'error' => 'Обработка не завершилась за отведённое число попыток',
            'finished_at' => now(),
        ]);

        $requeued = $stale()->where('attempts', '<', $maxAttempts)->update([
            'status' => TaskStatus::Pending->value,
            'started_at' => null,
            'published_at' => null,
        ]);

        // Событие о завершении для них отправит шаг completed ниже
        $expired = $pending()->where('created_at', '<', $expireBefore)->update([
            'status' => TaskStatus::Failed->value,
            'error' => 'Задача не была взята в работу: обработчик недоступен',
            'finished_at' => now(),
        ]);

        $published = $this->publishEach(
            $pending()->whereNull('published_at')->where('created_at', '<', $graceBefore)->orderBy('created_at'),
            $events->requested(...),
        );

        // Сообщение потерялось вместе с топиком или volume. Дубли безопасны: claim() атомарен
        $republished = $this->publishEach(
            $pending()->where('published_at', '<', $staleBefore)->orderBy('created_at'),
            $events->requested(...),
        );

        $completed = $this->publishEach(
            Task::query()
                ->whereIn('status', [TaskStatus::Done->value, TaskStatus::Failed->value])
                ->whereNull('completion_published_at')
                ->where('finished_at', '<', $graceBefore)
                ->orderBy('finished_at'),
            $events->completed(...),
        );

        $stats = compact('failed', 'requeued', 'expired', 'published', 'republished', 'completed');

        if (array_sum($stats) > 0) {
            Log::warning('Сверка задач нашла проблемы', $stats);
        }

        $this->line(collect($stats)->map(fn (int $n, string $k) => "{$k}={$n}")->implode(' '));

        if ($this->publishErrors > 0) {
            Log::error('Сверка задач: не удалось отправить события в Kafka', ['errors' => $this->publishErrors]);
            $this->error('Kafka недоступна или нет топиков: отправка не удалась, повторю через минуту');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    // После первой неудачи останавливается: Kafka, скорее всего, недоступна
    /**
     * @param  Builder<Task>  $query
     * @param  callable(Task): bool  $publish
     */
    private function publishEach(Builder $query, callable $publish): int
    {
        if ($this->publishErrors > 0) {
            return 0;
        }

        $sent = 0;

        $query->limit(100)->get()->each(function (Task $task) use ($publish, &$sent): bool {
            if (! $publish($task)) {
                $this->publishErrors++;

                return false;
            }

            $sent++;

            return true;
        });

        return $sent;
    }
}
