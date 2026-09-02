<?php

return [
    // Роль, у которой нельзя отбирать критичные права
    'super_role' => 'admin',

    // Права, которые всегда должны оставаться у super_role
    'locked_permissions' => [
        'access_control_panel',
    ],
];
