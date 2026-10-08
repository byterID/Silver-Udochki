<?php

namespace App\Http\Requests;

use App\Enums\TaskType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTaskRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // доступ проверяет middleware 'auth' на маршруте
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(TaskType::class)],
            'payload' => ['array'],
            'payload.seconds' => ['required_if:type,demo_report', 'integer', 'min:1', 'max:60'],
        ];
    }
}
