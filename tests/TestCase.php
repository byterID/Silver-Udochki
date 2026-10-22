<?php

declare(strict_types=1);

namespace Tests;

use App\Enums\RoleName;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    // Проходит Password::defaults(): 12+ символов, буквы и цифры
    public const VALID_PASSWORD = 'Str0ng-Passw0rd-2026';

    // Роли должны быть засеяны RolesAndPermissionsSeeder
    protected function userWithRole(RoleName $role): User
    {
        $user = User::factory()->create();
        $user->syncRoles([$role->value]);

        return $user->fresh() ?? $user;
    }

    /**
     * @param  array<int, int>  $quantities  id => qty
     * @return list<array{id: int, qty: int}>
     */
    protected function cartItems(array $quantities): array
    {
        return collect($quantities)->map(fn (int $qty, int $id) => ['id' => $id, 'qty' => $qty])->values()->all();
    }
}
