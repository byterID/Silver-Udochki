<?php

declare(strict_types=1);

namespace App\Tasks;

use App\Models\Task;

interface TaskRunner
{
    /**
     * Выполняет задачу и возвращает путь к результату на диске 'local'.
     * Любая ошибка — исключение: воркер сам пометит задачу как failed.
     */
    public function run(Task $task): string;
}
