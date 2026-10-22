<?php

declare(strict_types=1);

namespace App\Http\Requests\ControlPanel;

use App\Models\User;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateUserRequest extends StoreUserRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->targetUser()) ?? false;
    }

    public function rules(): array
    {
        return [
            ...parent::rules(),
            'email' => [
                'required', 'string', 'lowercase', 'email:rfc,dns', 'max:255',
                Rule::unique(User::class, 'email')->ignore($this->targetUser()->id),
            ],
            // Пустой пароль — не менять
            'password' => ['nullable', 'string', 'confirmed', Password::defaults()],
        ];
    }

    public function targetUser(): User
    {
        return $this->route('user');
    }
}
