<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/*
 * Права на склад. Как и права на акции, заводятся миграцией: деплой не запускает сидеры.
 * Выдаём admin и manager, если роли уже есть. Коды строками, без зависимости от enum.
 */
return new class extends Migration
{
    private const GROUP = 'Склад';

    private const PERMISSIONS = [
        'view_stock' => 'Просмотр склада',
        'update_stock' => 'Изменение остатков',
    ];

    private const ROLES = ['admin', 'manager'];

    public function up(): void
    {
        $tables = config('permission.table_names');
        $now = now();

        $groupId = DB::table('action_groups')->where('name', self::GROUP)->value('id')
            ?? DB::table('action_groups')->insertGetId([
                'name' => self::GROUP, 'created_at' => $now, 'updated_at' => $now,
            ]);

        foreach (self::PERMISSIONS as $name => $title) {
            DB::table($tables['permissions'])->updateOrInsert(
                ['name' => $name, 'guard_name' => 'web'],
                ['title' => $title, 'action_group_id' => $groupId, 'created_at' => $now, 'updated_at' => $now],
            );
        }

        $permissionIds = DB::table($tables['permissions'])
            ->whereIn('name', array_keys(self::PERMISSIONS))
            ->where('guard_name', 'web')
            ->pluck('id');
        $roleIds = DB::table($tables['roles'])
            ->whereIn('name', self::ROLES)
            ->where('guard_name', 'web')
            ->pluck('id');

        $rows = [];
        foreach ($roleIds as $roleId) {
            foreach ($permissionIds as $permissionId) {
                $rows[] = ['role_id' => $roleId, 'permission_id' => $permissionId];
            }
        }
        DB::table($tables['role_has_permissions'])->insertOrIgnore($rows);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $tables = config('permission.table_names');

        DB::table($tables['permissions'])
            ->whereIn('name', array_keys(self::PERMISSIONS))
            ->where('guard_name', 'web')
            ->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
