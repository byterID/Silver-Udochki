<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\RoleName;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    use HasFactory, HasRoles, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function highestRoleLevel(): int
    {
        return (int) $this->roles
            ->map(fn ($role) => RoleName::tryFrom($role->name)?->level() ?? 0)
            ->max();
    }

    public function primaryRole(): ?RoleName
    {
        $name = $this->roles->pluck('name')->first();

        return $name === null ? null : RoleName::tryFrom($name);
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole(config('access.super_role'));
    }
}
