<?php

declare(strict_types=1);

use App\Enums\PermissionCode;
use App\Enums\RoleName;

return [

    'super_role' => RoleName::Admin->value,

    'locked_permissions' => [
        PermissionCode::AccessControlPanel->value,
        PermissionCode::ManageAccess->value,
    ],

    'exclusive_permissions' => [
        PermissionCode::ManageAccess->value,
    ],

    'strict_hierarchy' => true,

    'admin' => [
        'name' => env('ADMIN_NAME', 'Администратор'),
        'email' => env('ADMIN_EMAIL'),
        'password' => env('ADMIN_PASSWORD'),
    ],
];
