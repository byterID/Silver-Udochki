<?php

namespace App\Http\Requests;

use App\Enums\TaskType;
use App\Http\Controllers\StockController;
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
        // Вход проверяет middleware 'auth' на маршруте, а здесь — право на сам тип задачи.
        // Неизвестный тип пропускаем дальше: его отклонит валидация с понятной ошибкой.
        $type = TaskType::tryFrom((string) $this->input('type'));

        return $type === null || $type->allows($this->user());
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
            // Отчёт об остатках можно сузить тем же фильтром, что и список на складе
            'payload.state' => ['nullable', Rule::in(array_keys(StockController::STATES))],
        ];
    }
}
