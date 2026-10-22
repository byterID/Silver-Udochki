<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\PermissionCode;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class UserPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can(PermissionCode::ManageUsers->value);
    }

    public function create(User $actor): bool
    {
        return $actor->can(PermissionCode::ManageUsers->value);
    }

    public function update(User $actor, User $target): Response
    {
        return $this->manage($actor, $target, 'Собственную учётную запись меняйте через профиль.', 'редактировать');
    }

    public function delete(User $actor, User $target): Response
    {
        return $this->manage($actor, $target, 'Нельзя удалить собственную учётную запись.', 'удалить');
    }

    public function manageRoleAccess(User $actor, int $roleLevel): Response
    {
        if (! $actor->can(PermissionCode::ManageAccess->value)) {
            return Response::deny('Недостаточно прав для настройки доступа.');
        }

        if (! $actor->isSuperAdmin() && $roleLevel >= $actor->highestRoleLevel()) {
            return Response::deny('Нельзя менять права роли, равной вашей или выше.');
        }

        return Response::allow();
    }

    private function manage(User $actor, User $target, string $selfMessage, string $verb): Response
    {
        return match (true) {
            ! $actor->can(PermissionCode::ManageUsers->value) => Response::deny('Недостаточно прав для управления пользователями.'),
            $actor->is($target) => Response::deny($selfMessage),
            $target->highestRoleLevel() >= $actor->highestRoleLevel() => Response::deny("Нельзя {$verb} пользователя с равными или большими правами."),
            default => Response::allow(),
        };
    }
}
