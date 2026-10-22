<?php

declare(strict_types=1);

namespace App\Http\Requests\ControlPanel;

use App\Enums\PermissionCode;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePermissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(PermissionCode::ManageAccess->value) ?? false;
    }

    // name не редактируется: код права зашит в PermissionCode
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'action_group_id' => ['nullable', 'integer', 'exists:action_groups,id'],
        ];
    }
}
