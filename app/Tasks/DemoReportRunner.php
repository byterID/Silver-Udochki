<?php

declare(strict_types=1);

namespace App\Tasks;

use App\Enums\TaskStatus;
use App\Models\Task;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Storage;

final class DemoReportRunner implements TaskRunner
{
    private const string SEP = ';'; // разделитель, который русский Excel понимает без настройки

    public function run(Task $task): string
    {
        // claim() обновлял строку в БД атомарным UPDATE, минуя этот объект.
        // Без refresh() у нас были бы устаревшие started_at и attempts.
        $task->refresh();

        $seconds = (int) ($task->payload['seconds'] ?? 5);
        $worker = sprintf('%s (pid %d)', gethostname(), getmypid());
        $rows = [];

        // Замыкание добавляет строку журнала. &$rows — передача по ссылке:
        // без амперсанда функция дописывала бы в свою копию массива.
        $log = function (CarbonInterface $at, string $stage, string $who, string $state, string $note = '') use (&$rows, $task): void {
            $rows[] = [
                $task->id,
                $at->format('Y-m-d H:i:s.v'),                          // .v — миллисекунды
                $stage,
                $who,
                number_format(abs($task->created_at->diffInMilliseconds($at)) / 1000, 1, ',', ''),
                $state,
                $note,
            ];
        };

        // События, которые произошли до воркера. Восстанавливаем их по меткам в БД.
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

        // Сама «работа»: каждый шаг — секунда и строка журнала.
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

        return $this->write($task, $rows);
    }

    /** @param list<list<string>> $rows */
    private function write(Task $task, array $rows): string
    {
        // php://temp — поток в памяти (при росте сам уходит во временный файл).
        // fputcsv умеет писать только в поток, отсюда и этот приём.
        $stream = fopen('php://temp', 'r+');

        // BOM: по этим трём байтам Excel понимает, что файл в UTF-8.
        // Без них кириллица превратится в кракозябры.
        fwrite($stream, "\xEF\xBB\xBF");

        $header = ['Задача', 'Время', 'Этап', 'Исполнитель', 'С момента запроса, с', 'Состояние', 'Комментарий'];

        foreach ([$header, ...$rows] as $row) {
            // fputcsv сам берёт в кавычки значения с ; или кавычками внутри.
            // Последний аргумент (escape) передаём явно: в PHP 8.4 значение по умолчанию устарело.
            fputcsv($stream, $row, self::SEP, '"', '');
        }

        rewind($stream); // перемотка в начало, иначе Storage прочитает пустоту

        $path = "results/{$task->id}.csv";
        Storage::disk('local')->put($path, $stream);
        fclose($stream);

        return $path;
    }
}
