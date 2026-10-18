<?php

declare(strict_types=1);

namespace App\Http\Requests;

class ApplyPromoCodeRequest extends CartQuoteRequest
{
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'code' => ['required', 'string', 'max:32'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.required' => 'Введите промокод.',
            'code.max' => 'Слишком длинный промокод.',
        ];
    }
}
