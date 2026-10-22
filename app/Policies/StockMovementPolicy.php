<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\PermissionCode;
use App\Models\User;

// Только права, без проверки роли, как у акций
class StockMovementPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can(PermissionCode::ViewStock->value);
    }

    public function create(User $actor): bool
    {
        return $actor->can(PermissionCode::UpdateStock->value);
    }
}
