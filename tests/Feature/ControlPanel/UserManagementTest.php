<?php

declare(strict_types=1);

namespace Tests\Feature\ControlPanel;

use App\Enums\RoleName;
use App\Models\User;
use App\Services\UserService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function userWithRole(RoleName $role): User
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->syncRoles([$role->value]);

        return $user->fresh();
    }

    public function test_покупатель_не_попадает_в_панель(): void
    {
        $this->actingAs($this->userWithRole(RoleName::User))
            ->get('/control-panel')
            ->assertForbidden();
    }

    public function test_менеджер_не_может_создать_администратора(): void
    {
        $this->actingAs($this->userWithRole(RoleName::Manager))
            ->post('/control-panel/users', [
                'name' => 'Взлом',
                'email' => 'hack@example.com',
                'password' => 'Sup3rSecret!Pass',
                'password_confirmation' => 'Sup3rSecret!Pass',
                'role' => RoleName::Admin->value,
            ])
            ->assertSessionHasErrors('role');

        $this->assertDatabaseMissing('users', ['email' => 'hack@example.com']);
    }

    public function test_менеджер_не_может_повысить_себя(): void
    {
        $manager = $this->userWithRole(RoleName::Manager);

        $this->actingAs($manager)
            ->put("/control-panel/users/{$manager->id}", [
                'name' => $manager->name,
                'email' => $manager->email,
                'role' => RoleName::Admin->value,
            ])
            ->assertForbidden();

        $this->assertTrue($manager->fresh()->hasRole(RoleName::Manager->value));
    }

    public function test_менеджер_не_может_редактировать_администратора(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);

        $this->actingAs($this->userWithRole(RoleName::Manager))
            ->put("/control-panel/users/{$admin->id}", [
                'name' => 'Перехват',
                'email' => 'attacker@example.com',
                'role' => RoleName::User->value,
            ])
            ->assertForbidden();

        $this->assertTrue($admin->fresh()->hasRole(RoleName::Admin->value));
    }

    public function test_последнего_администратора_нельзя_удалить(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);
        $second = $this->userWithRole(RoleName::Admin);

        // Второй админ удаляет первого — при strict_hierarchy это запрещено,
        // поэтому проверяем именно защиту «последнего» на уровне сервиса.
        $this->assertSame(2, User::role(RoleName::Admin->value)->count());

        app(UserService::class)->delete($second, $admin);

        $this->expectException(ValidationException::class);
        app(UserService::class)->delete($admin, $admin);
    }

    public function test_нельзя_удалить_себя(): void
    {
        $admin = $this->userWithRole(RoleName::Admin);

        $this->actingAs($admin)
            ->delete("/control-panel/users/{$admin->id}", [
                'email_confirmation' => $admin->email,
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }
}
