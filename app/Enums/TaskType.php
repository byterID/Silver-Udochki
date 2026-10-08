<?php

declare(strict_types=1);

namespace App\Enums;

use App\Tasks\DemoReportRunner;
use App\Tasks\TaskRunner;

enum TaskType: string
{
    case DemoReport = 'demo_report';

    public function label(): string
    {
        return match ($this) {
            self::DemoReport => 'Демо-отчёт',
        };
    }

    /** @return class-string<TaskRunner> */
    public function runnerClass(): string
    {
        return match ($this) {
            self::DemoReport => DemoReportRunner::class,
        };
    }
}
