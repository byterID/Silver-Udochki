<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\RoleName;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call(RolesAndPermissionsSeeder::class);

        $email = config('access.admin.email');
        $password = config('access.admin.password');

        if (blank($email) || blank($password)) {
            $this->command->warn(
                'ADMIN_EMAIL / ADMIN_PASSWORD не заданы в .env — администратор не создан.'
            );

            return;
        }

        $admin = User::firstOrCreate(
            ['email' => mb_strtolower($email)],
            [
                'name' => config('access.admin.name', 'Администратор'),
                'password' => $password,
                'email_verified_at' => now(),
            ],
        );

        $admin->syncRoles([RoleName::Admin->value]);

        $this->command->info("Администратор: {$admin->email}");
    }
}
