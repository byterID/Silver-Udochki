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

    // При strict_hierarchy свою роль назначить нельзя, только ниже
    public function isAssignableBy(int $actorLevel): bool
    {
        return config('access.strict_hierarchy', true)
            ? $this->level() < $actorLevel
            : $this->level() <= $actorLevel;
    }

    /** @return list<string> */
    public function defaultPermissions(): array
    {
        return match ($this) {
            self::Admin => PermissionCode::values(),
            self::Manager => [
                PermissionCode::AccessControlPanel->value,
                PermissionCode::ManageUsers->value,
                PermissionCode::ViewPromotions->value,
                PermissionCode::CreatePromotions->value,
                PermissionCode::UpdatePromotions->value,
                PermissionCode::DeletePromotions->value,
                PermissionCode::ViewStock->value,
                PermissionCode::UpdateStock->value,
                PermissionCode::CreateStockReport->value,
            ],
            self::User, self::Guest => [],
        };
    }

    /** @return list<string> */
    public static function staffValues(): array
    {
        return array_values(array_map(
            fn (self $r) => $r->value,
            array_filter(self::cases(), fn (self $role) => $role->isStaff()),
        ));
    }

    /** @return list<string> */
    public static function customerValues(): array
    {
        return [self::User->value, self::Guest->value];
    }
}
