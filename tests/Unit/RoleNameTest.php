<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\PermissionCode;
use App\Enums\RoleName;
use PHPUnit\Framework\TestCase;

class RoleNameTest extends TestCase
{
    public function test_admin_is_the_highest_level(): void
    {
        $levels = array_map(fn (RoleName $r) => $r->level(), RoleName::cases());

        $this->assertSame(RoleName::Admin->level(), max($levels));
        $this->assertGreaterThan(RoleName::Manager->level(), RoleName::Admin->level());
        $this->assertGreaterThan(RoleName::User->level(), RoleName::Manager->level());
    }

    public function test_only_admin_and_manager_are_staff(): void
    {
        $this->assertSame(['admin', 'manager'], RoleName::staffValues());
        $this->assertFalse(RoleName::User->isStaff());
        $this->assertFalse(RoleName::Guest->isStaff());
    }

    public function test_admin_gets_every_permission(): void
    {
        $this->assertSame(PermissionCode::values(), RoleName::Admin->defaultPermissions());
    }

    public function test_customers_get_no_permissions(): void
    {
        $this->assertSame([], RoleName::User->defaultPermissions());
        $this->assertSame([], RoleName::Guest->defaultPermissions());
    }

    public function test_manager_cannot_manage_access(): void
    {
        $this->assertNotContains(
            PermissionCode::ManageAccess->value,
            RoleName::Manager->defaultPermissions(),
        );
    }

    public function test_staff_and_customer_sets_do_not_overlap(): void
    {
        $this->assertSame(
            [],
            array_intersect(RoleName::staffValues(), RoleName::customerValues()),
        );
    }
}
