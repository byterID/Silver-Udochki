<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Task;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

final class PruneTaskResults extends Command
{
    protected $signature = 'tasks:prune-results';

    protected $description = 'Удаляет файлы результатов, у которых истёк срок хранения';

    public function handle(): int
    {
        $disk = Storage::disk('local');
        $threshold = now()->subMinutes(Config::integer('tasks.result_ttl_minutes'));
        $deleted = 0;

        Task::query()
            ->whereNotNull('result_path')
            ->where('finished_at', '<', $threshold)
            // lazyById читает пачками по 100 и не держит в памяти всю выборку.
            // С UUID v7 это работает: они упорядочены по времени создания.
            ->lazyById(100)
            ->each(function (Task $task) use ($disk, &$deleted): void {
                // Сначала файл, потом запись в БД. Если упадёт между ними,
                // строка будет указывать на несуществующий файл: download() честно
                // ответит 410, а следующий запуск команды доделает работу.
                // При обратном порядке остался бы файл, о котором никто не знает.
                $disk->delete($task->result_path); // отсутствующий файл не ошибка

                // result_path не в $fillable (защита от подделки), поэтому forceFill.
                $task->forceFill(['result_path' => null])->save();
                $deleted++;
            });

        if ($deleted > 0) {
            Log::info('Удалены просроченные результаты задач', ['count' => $deleted]);
        }

        $this->info("Удалено файлов: {$deleted}");

        return self::SUCCESS;
    }
}
