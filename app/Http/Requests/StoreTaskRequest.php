<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\StockState;
use App\Enums\TaskType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Неизвестный тип пропускаем: его отклонит валидация с понятной ошибкой
        $type = TaskType::tryFrom((string) $this->input('type'));

        return $type === null || $type->allows($this->user());
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(TaskType::class)],
            'payload' => ['array'],
            'payload.seconds' => ['required_if:type,demo_report', 'integer', 'min:1', 'max:60'],
            'payload.state' => ['nullable', Rule::enum(StockState::class)],
        ];
    }
}
