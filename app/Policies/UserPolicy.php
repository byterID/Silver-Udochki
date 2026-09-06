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
        if (! $actor->can(PermissionCode::ManageUsers->value)) {
            return Response::deny('Недостаточно прав для управления пользователями.');
        }

        if ($actor->is($target)) {
            return Response::deny('Собственную учётную запись меняйте через профиль.');
        }

        if ($target->highestRoleLevel() >= $actor->highestRoleLevel()) {
            return Response::deny('Нельзя редактировать пользователя с равными или большими правами.');
        }

        return Response::allow();
    }

    public function delete(User $actor, User $target): Response
    {
        if (! $actor->can(PermissionCode::ManageUsers->value)) {
            return Response::deny('Недостаточно прав для управления пользователями.');
        }

        if ($actor->is($target)) {
            return Response::deny('Нельзя удалить собственную учётную запись.');
        }

        if ($target->highestRoleLevel() >= $actor->highestRoleLevel()) {
            return Response::deny('Нельзя удалить пользователя с равными или большими правами.');
        }

        return Response::allow();
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
}
