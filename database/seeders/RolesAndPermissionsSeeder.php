<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        Permission::create(['name' => 'access_admin_panel']);
        Permission::create(['name' => 'create_promo']);
        Permission::create(['name' => 'export_users']);
        // ...добавь свои действия

        // 2. создать роли
        $admin = Role::create(['name' => 'admin']);
        $user  = Role::create(['name' => 'user']);
        $guest = Role::create(['name' => 'guest']);

        // 3. включить "ползунки" — раздать разрешения ролям
        $admin->givePermissionTo(Permission::all());   // админу всё
        $user->givePermissionTo(['export_users']);     // пользователю только выгрузка
    }
}
