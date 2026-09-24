<?php

declare(strict_types=1);

namespace App\Http\Requests\ControlPanel;

use App\Enums\PermissionCode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreActionGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(PermissionCode::ManageAccess->value) ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('action_groups', 'name')],
        ];
    }
}
