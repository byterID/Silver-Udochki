<?php

declare(strict_types=1);

namespace App\Enums;

enum RoleName: string
{
    case Admin = 'admin';
    case Manager = 'manager';
    case User = 'user';
    case Guest = 'guest';

    public function level(): int
    {
        return match ($this) {
            self::Admin => 100,
            self::Manager => 50,
            self::User => 10,
            self::Guest => 0,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Администратор',
            self::Manager => 'Менеджер',
            self::User => 'Покупатель',
            self::Guest => 'Гость',
        };
    }

    public function isStaff(): bool
    {
        return $this->level() >= self::Manager->level();
    }

    public function defaultPermissions(): array
    {
        return match ($this) {
            self::Admin => array_map(
                fn (PermissionCode $c) => $c->value,
                PermissionCode::cases()
            ),
            self::Manager => [
                PermissionCode::AccessControlPanel->value,
                PermissionCode::ManageUsers->value,
            ],
            self::User, self::Guest => [],
        };
    }

    public static function staffCases(): array
    {
        return array_values(array_filter(
            self::cases(),
            fn (self $role) => $role->isStaff()
        ));
    }

    public static function staffValues(): array
    {
        return array_map(fn (self $r) => $r->value, self::staffCases());
    }

    public static function customerValues(): array
    {
        return [self::User->value];
    }
}
