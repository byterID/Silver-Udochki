<?php

declare(strict_types=1);

namespace App\Enums;

use App\Models\User;
use App\Tasks\DemoReportRunner;
use App\Tasks\StockReportRunner;
use App\Tasks\TaskRunner;

enum TaskType: string
{
    case DemoReport = 'demo_report';
    case StockReport = 'stock_report';

    public function label(): string
    {
        return match ($this) {
            self::DemoReport => 'Демо-отчёт',
            self::StockReport => 'Отчёт об остатках',
        };
    }

    /** @return class-string<TaskRunner> */
    public function runnerClass(): string
    {
        return match ($this) {
            self::DemoReport => DemoReportRunner::class,
            self::StockReport => StockReportRunner::class,
        };
    }

    // Проверяется и при запуске, и в воркере. null — доступна всем
    public function permission(): ?PermissionCode
    {
        return match ($this) {
            self::DemoReport => null,
            self::StockReport => PermissionCode::CreateStockReport,
        };
    }

    public function allows(User $user): bool
    {
        $permission = $this->permission();

        return $permission === null || $user->can($permission->value);
    }
}
