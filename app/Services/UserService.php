<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class UserService
{
    public function create(array $data, User $actor): User
    {
        return DB::transaction(function () use ($data, $actor) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                // Хэширование делает каст 'hashed' в модели — Hash::make не нужен
                'password' => $data['password'],
                'email_verified_at' => now(),
            ]);

            $user->syncRoles([$data['role']]);

            $this->audit('user.created', $actor, $user, ['role' => $data['role']]);

            return $user;
        });
    }

    public function update(User $user, array $data, User $actor): User
    {
        return DB::transaction(function () use ($user, $data, $actor) {
            $oldRole = $user->roles->pluck('name')->first();

            $user->fill([
                'name' => $data['name'],
                'email' => $data['email'],
            ]);

            // Сменили email — подтверждение сбрасывается.
            if ($user->isDirty('email')) {
                $user->email_verified_at = null;
            }

            if (filled($data['password'] ?? null)) {
                $user->password = $data['password'];
                $user->setRememberToken(Str::random(60));

            }

            $user->save();

            if ($data['role'] !== $oldRole) {
                $this->guardLastSuperAdmin($user, $data['role']);
                $user->syncRoles([$data['role']]);

                $this->audit('user.role_changed', $actor, $user, [
                    'from' => $oldRole,
                    'to' => $data['role'],
                ]);
            }

            return $user;
        });
    }

    public function delete(User $user, User $actor): void
    {
        DB::transaction(function () use ($user, $actor) {
            $this->guardLastSuperAdmin($user, null);

            $this->audit('user.deleted', $actor, $user, [
                'role' => $user->roles->pluck('name')->first(),
            ]);

            $user->delete();
        });
    }

    private function guardLastSuperAdmin(User $user, ?string $newRole): void
    {
        $superRole = (string) config('access.super_role');

        if (! $user->hasRole($superRole) || $newRole === $superRole) {
            return;
        }

        if (User::role($superRole)->count() <= 1) {
            throw ValidationException::withMessages([
                'role' => 'Это последний администратор: снять роль или удалить его нельзя. '
                    .'Сначала назначьте другого администратора.',
            ]);
        }
    }

    private function audit(string $event, User $actor, User $target, array $context = []): void
    {
        Log::channel('audit')->info($event, [
            'actor_id' => $actor->id,
            'actor_email' => $actor->email,
            'target_id' => $target->id,
            'target_email' => $target->email,
            'ip' => request()->ip(),
        ] + $context);
    }
}
