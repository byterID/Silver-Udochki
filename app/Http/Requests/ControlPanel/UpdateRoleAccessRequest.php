<?php

declare(strict_types=1);

namespace App\Http\Requests\ControlPanel;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRoleAccessRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // уровень роли проверяет AccessControlController
    }

    public function rules(): array
    {
        return [
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['integer', 'exists:permissions,id'],
        ];
    }
}
