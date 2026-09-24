<?php

declare(strict_types=1);

namespace App\Http\Requests\ControlPanel;

use App\Models\User;
use App\Rules\AssignableRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', User::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required', 'string', 'lowercase', 'email:rfc,dns',
                'max:255', Rule::unique(User::class, 'email'),
            ],
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
            'role' => ['required', 'string', new AssignableRole],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => is_string($this->input('email'))
                ? mb_strtolower(trim($this->input('email')))
                : $this->input('email'),
        ]);
    }

    public function attributes(): array
    {
        return [
            'name' => 'имя',
            'email' => 'email',
            'password' => 'пароль',
            'role' => 'роль',
        ];
    }
}
