<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\PermissionCode;
use App\Enums\RoleName;
use App\Models\ActionGroup;
use App\Models\Permission;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // 1. Права и их группы — источник истины в PermissionCode
        foreach (PermissionCode::cases() as $code) {
            $group = ActionGroup::firstOrCreate(['name' => $code->group()]);

            Permission::updateOrCreate(
                ['name' => $code->value, 'guard_name' => 'web'],
                ['title' => $code->title(), 'action_group_id' => $group->id],
            );
        }

        // 2. Роли и их права по умолчанию
        foreach (RoleName::cases() as $roleName) {
            $role = Role::findOrCreate($roleName->value, 'web');
            $role->syncPermissions($roleName->defaultPermissions());
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
