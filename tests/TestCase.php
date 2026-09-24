<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Пароль для тестовых фикстур. Проходит Password::defaults():
     * 12+ символов, буквы и цифры. Не используется нигде вне тестов.
     */
    public const VALID_PASSWORD = 'Str0ng-Passw0rd-2026';
}
