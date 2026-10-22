<?php

declare(strict_types=1);

namespace App\Tasks;

use App\Models\Task;

interface TaskRunner
{
    // Возвращает путь к результату на диске local. Исключение — задача failed
    public function run(Task $task): string;
}
