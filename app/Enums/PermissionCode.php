<?php

declare(strict_types=1);

namespace App\Enums;

enum PermissionCode: string
{
    case AccessControlPanel = 'access_control_panel';
    case ManageUsers = 'manage_users';
    case ManageAccess = 'manage_access';
    case ExportUsers = 'export_users';

    public function title(): string
    {
        return match ($this) {
            self::AccessControlPanel => 'Вход в панель управления',
            self::ManageUsers => 'Управление пользователями',
            self::ManageAccess => 'Настройка прав и ролей',
            self::ExportUsers => 'Выгрузка пользователей',
        };
    }

    public function group(): ?string
    {
        return match ($this) {
            self::AccessControlPanel, self::ManageAccess => 'Администрирование',
            self::ManageUsers, self::ExportUsers => 'Пользователи',
        };
    }

    public static function values(): array
    {
        return array_map(fn (self $c) => $c->value, self::cases());
    }
}
