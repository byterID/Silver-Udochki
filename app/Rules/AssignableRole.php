<?php

declare(strict_types=1);

namespace App\Rules;

use App\Enums\RoleName;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Auth;

class AssignableRole implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $role = RoleName::tryFrom(is_string($value) ? $value : '');

        if ($role === null) {
            $fail('Указана неизвестная роль.');

            return;
        }

        $actor = Auth::user();

        if ($actor === null) {
            $fail('Не удалось определить текущего пользователя.');

            return;
        }

        $actorLevel = $actor->highestRoleLevel();
        $strict = (bool) config('access.strict_hierarchy', true);

        $tooHigh = $strict
            ? $role->level() >= $actorLevel
            : $role->level() > $actorLevel;

        if ($tooHigh) {
            $fail(sprintf(
                'Вы не можете назначить роль «%s»: она равна вашей или выше.',
                $role->label()
            ));
        }
    }
}
