<?php

declare(strict_types=1);

namespace App\Enums;

enum PermissionCode: string
{
    case AccessControlPanel = 'access_control_panel';
    case ManageUsers = 'manage_users';
    case ManageAccess = 'manage_access';
    case ExportUsers = 'export_users';
    case ViewPromotions = 'view_promotions';
    case CreatePromotions = 'create_promotions';
    case UpdatePromotions = 'update_promotions';
    case DeletePromotions = 'delete_promotions';
    case ViewStock = 'view_stock';
    case UpdateStock = 'update_stock';

    public function title(): string
    {
        return match ($this) {
            self::AccessControlPanel => 'Вход в панель управления',
            self::ManageUsers => 'Управление пользователями',
            self::ManageAccess => 'Настройка прав и ролей',
            self::ExportUsers => 'Выгрузка пользователей',
            self::ViewPromotions => 'Просмотр акций',
            self::CreatePromotions => 'Создание акций',
            self::UpdatePromotions => 'Изменение акций',
            self::DeletePromotions => 'Удаление акций',
            self::ViewStock => 'Просмотр склада',
            self::UpdateStock => 'Изменение остатков',
        };
    }

    public function group(): string
    {
        return match ($this) {
            self::AccessControlPanel, self::ManageAccess => 'Администрирование',
            self::ManageUsers, self::ExportUsers => 'Пользователи',
            self::ViewPromotions, self::CreatePromotions,
            self::UpdatePromotions, self::DeletePromotions => 'Акции',
            self::ViewStock, self::UpdateStock => 'Склад',
        };
    }

    public static function values(): array
    {
        return array_map(fn (self $c) => $c->value, self::cases());
    }
}
