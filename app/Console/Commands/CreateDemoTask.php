<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\TaskType;
use App\Models\User;
use App\Services\Tasks\TaskDispatcher;
use Illuminate\Console\Command;

final class CreateDemoTask extends Command
{
    protected $signature = 'tasks:demo
        {--seconds=5 : Сколько секунд работает задача}
        {--count=1 : Сколько задач создать}
        {--user= : ID пользователя (по умолчанию первый)}';

    protected $description = 'Создаёт демо-задачи для проверки пайплайна';

    public function handle(TaskDispatcher $dispatcher): int
    {
        $user = $this->option('user')
            ? User::findOrFail($this->option('user'))
            : User::firstOrFail();

        for ($i = 0; $i < (int) $this->option('count'); $i++) {
            $task = $dispatcher->dispatch($user, TaskType::DemoReport, [
                'seconds' => (int) $this->option('seconds'),
            ]);

            $this->line("{$task->id}  published=".($task->published_at ? 'yes' : 'no'));
        }

        return self::SUCCESS;
    }
}
