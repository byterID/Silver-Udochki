<?php

declare(strict_types=1);

namespace App\Http\Requests\ControlPanel;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class DeleteUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('delete', $this->targetUser()) ?? false;
    }

    public function rules(): array
    {
        return [
            'email_confirmation' => ['required', 'string'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $typed = mb_strtolower(trim((string) $this->input('email_confirmation')));

            if (! hash_equals(mb_strtolower($this->targetUser()->email), $typed)) {
                $validator->errors()->add(
                    'email_confirmation',
                    'Введённый email не совпадает с email пользователя.'
                );
            }
        });
    }

    public function targetUser(): User
    {
        return $this->route('user');
    }
}
