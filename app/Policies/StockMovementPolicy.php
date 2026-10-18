<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\PermissionCode;
use App\Models\User;

/** Склад: только права, без проверки роли (как у акций). */
class StockMovementPolicy
{
    /** Остатки и журнал движения. */
    public function viewAny(User $actor): bool
    {
        return $actor->can(PermissionCode::ViewStock->value);
    }

    /** Поступление и инвентаризация. */
    public function create(User $actor): bool
    {
        return $actor->can(PermissionCode::UpdateStock->value);
    }
}
