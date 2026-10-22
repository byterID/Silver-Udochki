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
            ->lazyById(100)
            ->each(function (Task $task) use ($disk, &$deleted): void {
                // Сначала файл, потом строка: при сбое между ними download() ответит 410,
                // а следующий запуск доделает. Обратный порядок оставил бы ничейный файл
                $disk->delete($task->result_path);
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
