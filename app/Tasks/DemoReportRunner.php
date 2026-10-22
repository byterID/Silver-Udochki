<?php

declare(strict_types=1);

namespace App\Tasks;

use App\Enums\TaskStatus;
use App\Models\Task;
use Carbon\CarbonInterface;

// Журнал прохождения задачи по конвейеру: для проверки Kafka и воркеров
final class DemoReportRunner implements TaskRunner
{
    public function run(Task $task): string
    {
        // claim() обновил строку атомарным UPDATE в обход модели: started_at и attempts здесь устарели
        $task->refresh();

        $seconds = (int) ($task->payload['seconds'] ?? 5);
        $worker = sprintf('%s (pid %d)', gethostname(), getmypid());
        $rows = [['Задача', 'Время', 'Этап', 'Исполнитель', 'С момента запроса, с', 'Состояние', 'Комментарий']];

        $log = function (CarbonInterface $at, string $stage, string $who, string $state, string $note = '') use (&$rows, $task): void {
            $rows[] = [
                $task->id,
                $at->format('Y-m-d H:i:s.v'),
                $stage,
                $who,
                number_format(abs($task->created_at->diffInMilliseconds($at)) / 1000, 1, ',', ''),
                $state,
                $note,
            ];
        };

        $log($task->created_at, 'Задача создана', 'app (веб)', TaskStatus::Pending->label(), "параметры: {$seconds} с");

        if ($task->published_at !== null) {
            $log($task->published_at, 'Отправлена в Kafka', 'app (веб)', TaskStatus::Pending->label(), 'топик tasks.requested');
        }

        $log(
            $task->started_at ?? now(),
            'Взята в работу',
            $worker,
            TaskStatus::Processing->label(),
            sprintf('попытка %d из %d', $task->attempts, config('tasks.max_attempts')),
        );

        for ($step = 1; $step <= $seconds; $step++) {
            sleep(1);

            $log(
                now(),
                "Шаг {$step} из {$seconds}",
                $worker,
                TaskStatus::Processing->label(),
                sprintf('%d %%, память %s МБ', intdiv($step * 100, $seconds), number_format(memory_get_usage(true) / 1048576, 1, ',', '')),
            );
        }

        $log(now(), 'Результат записан', $worker, 'Результат записан', 'статус «Готово» и уведомление ставятся после записи файла');

        return CsvResult::save($task, $rows);
    }
}
