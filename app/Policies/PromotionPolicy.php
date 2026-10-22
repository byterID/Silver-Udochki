<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\PermissionCode;
use App\Models\Promotion;
use App\Models\User;

// Только права, без проверки роли: решает то, что выдано в «Управлении доступом»
class PromotionPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can(PermissionCode::ViewPromotions->value);
    }

    public function create(User $actor): bool
    {
        return $actor->can(PermissionCode::CreatePromotions->value);
    }

    public function update(User $actor, Promotion $promotion): bool
    {
        return $actor->can(PermissionCode::UpdatePromotions->value);
    }

    public function delete(User $actor, Promotion $promotion): bool
    {
        return $actor->can(PermissionCode::DeletePromotions->value);
    }

    public function pickProducts(User $actor): bool
    {
        return $actor->canAny([
            PermissionCode::CreatePromotions->value,
            PermissionCode::UpdatePromotions->value,
        ]);
    }
}
