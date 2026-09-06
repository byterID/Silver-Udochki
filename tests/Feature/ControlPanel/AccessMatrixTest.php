<?php

declare(strict_types=1);

namespace Tests\Feature\ControlPanel;

use App\Enums\PermissionCode;
use App\Enums\RoleName;
use App\Models\Permission;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AccessMatrixTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function admin(): User
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->syncRoles([RoleName::Admin->value]);

        return $user->fresh();
    }

    public function test_критичное_право_нельзя_снять_с_администратора(): void
    {
        $adminRole = Role::findByName(RoleName::Admin->value);

        $this->actingAs($this->admin())
            ->put("/control-panel/access-control/{$adminRole->id}", ['permissions' => []])
            ->assertSessionHasErrors('permissions');

        $this->assertTrue(
            $adminRole->fresh()->hasPermissionTo(PermissionCode::AccessControlPanel->value)
        );
    }

    public function test_эксклюзивное_право_нельзя_выдать_покупателю(): void
    {
        $userRole = Role::findByName(RoleName::User->value);
        $exclusive = Permission::where('name', PermissionCode::ManageAccess->value)->firstOrFail();

        $this->actingAs($this->admin())
            ->put("/control-panel/access-control/{$userRole->id}", [
                'permissions' => [$exclusive->id],
            ])
            ->assertSessionHasErrors('permissions');

        $this->assertFalse(
            $userRole->fresh()->hasPermissionTo(PermissionCode::ManageAccess->value)
        );
    }
}
