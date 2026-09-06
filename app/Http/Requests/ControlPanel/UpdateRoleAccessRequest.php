<?php

declare(strict_types=1);

namespace App\Http\Requests\ControlPanel;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRoleAccessRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // проверка уровня роли — в контроллере, там доступен объект Role
    }

    public function rules(): array
    {
        return [
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['integer', 'exists:permissions,id'],
        ];
    }
}
