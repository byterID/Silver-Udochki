<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/*
 * Право на отчёт об остатках в отдельной группе доступа: его можно выдать роли,
 * не открывая ей сам склад. Заводится миграцией, потому что деплой не запускает сидеры.
 * Выдаём admin и manager, если роли уже есть. Коды строками, без зависимости от enum.
 */
return new class extends Migration
{
    private const GROUP = 'Создание отчёта остатков';

    private const PERMISSION = 'create_stock_report';

    private const TITLE = 'Выгрузка отчёта об остатках';

    private const ROLES = ['admin', 'manager'];

    public function up(): void
    {
        $tables = config('permission.table_names');
        $now = now();

        $groupId = DB::table('action_groups')->where('name', self::GROUP)->value('id')
            ?? DB::table('action_groups')->insertGetId([
                'name' => self::GROUP, 'created_at' => $now, 'updated_at' => $now,
            ]);

        DB::table($tables['permissions'])->updateOrInsert(
            ['name' => self::PERMISSION, 'guard_name' => 'web'],
            ['title' => self::TITLE, 'action_group_id' => $groupId, 'created_at' => $now, 'updated_at' => $now],
        );

        $permissionId = DB::table($tables['permissions'])
            ->where('name', self::PERMISSION)
            ->where('guard_name', 'web')
            ->value('id');
        $roleIds = DB::table($tables['roles'])
            ->whereIn('name', self::ROLES)
            ->where('guard_name', 'web')
            ->pluck('id');

        DB::table($tables['role_has_permissions'])->insertOrIgnore(
            $roleIds->map(fn ($roleId) => ['role_id' => $roleId, 'permission_id' => $permissionId])->all(),
        );

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $tables = config('permission.table_names');

        DB::table($tables['permissions'])
            ->where('name', self::PERMISSION)
            ->where('guard_name', 'web')
            ->delete();

        // Группу удаляем, только если в ней больше ничего нет
        DB::table('action_groups')
            ->where('name', self::GROUP)
            ->whereNotExists(fn ($q) => $q->from($tables['permissions'])->whereColumn('action_group_id', 'action_groups.id'))
            ->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
