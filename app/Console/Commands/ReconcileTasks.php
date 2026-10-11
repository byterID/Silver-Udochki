<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\TaskStatus;
use App\Models\Task;
use App\Services\Tasks\TaskCompletedPublisher;
use App\Services\Tasks\TaskDispatcher;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;

final class ReconcileTasks extends Command
{
    protected $signature = 'tasks:reconcile';

    protected $description = 'Находит зависшие задачи и возвращает их в работу';

    /** Сколько отправок в Kafka не удалось за этот запуск. */
    private int $publishErrors = 0;

    public function handle(TaskDispatcher $dispatcher, TaskCompletedPublisher $completedPublisher): int
    {
        $staleBefore = now()->subMinutes(Config::integer('tasks.stale_after_minutes'));
        $graceBefore = now()->subSeconds(Config::integer('tasks.publish_grace_seconds'));
        $expireBefore = now()->subMinutes(Config::integer('tasks.pending_timeout_minutes'));
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

        // 3. Слишком долго в очереди, никто так и не взял: не держим пользователя
        //    в «ожидании» вечно. Событие о завершении отправит шаг 6.
        $expired = Task::query()
            ->where('status', TaskStatus::Pending->value)
            ->where('created_at', '<', $expireBefore)
            ->update([
                'status' => TaskStatus::Failed->value,
                'error' => 'Задача не была взята в работу: обработчик недоступен',
                'finished_at' => now(),
            ]);

        // 4. Ждёт, но в Kafka так и не попала: публикуем
        $published = $this->publishEach(
            Task::query()
                ->where('status', TaskStatus::Pending->value)
                ->whereNull('published_at')
                ->where('created_at', '<', $graceBefore)
                ->orderBy('created_at'),
            fn (Task $task): bool => $dispatcher->publish($task),
        );

        // 5. Попала в Kafka, но никто не взял (сообщение потеряно вместе с топиком
        //    или volume): публикуем повторно. Дубли безопасны — claim() атомарен.
        $republished = $this->publishEach(
            Task::query()
                ->where('status', TaskStatus::Pending->value)
                ->whereNotNull('published_at')
                ->where('published_at', '<', $staleBefore)
                ->orderBy('created_at'),
            fn (Task $task): bool => $dispatcher->publish($task),
        );

        // 6. Завершена, но событие о завершении не ушло: публикуем
        $completed = $this->publishEach(
            Task::query()
                ->whereIn('status', [TaskStatus::Done->value, TaskStatus::Failed->value])
                ->whereNull('completion_published_at')
                ->where('finished_at', '<', $graceBefore)
                ->orderBy('finished_at'),
            fn (Task $task): bool => $completedPublisher->publish($task),
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

    /**
     * Публикует задачи по одной. После первой неудачи останавливается:
     * Kafka, скорее всего, недоступна, и остальные отправки упадут так же.
     *
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

                return false; // прерывает each()
            }

            $sent++;

            return true;
        });

        return $sent;
    }
}
