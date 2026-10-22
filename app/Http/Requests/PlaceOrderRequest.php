<?php

declare(strict_types=1);

namespace App\Http\Requests;

class PlaceOrderRequest extends CartQuoteRequest
{
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'items' => ['required', 'array', 'min:1', 'max:'.self::MAX_ITEMS],
            'total' => ['required', 'integer', 'min:0'],
            'key' => ['required', 'uuid'],
        ];
    }

    public function messages(): array
    {
        return [
            'items.required' => 'Корзина пуста.',
            'items.min' => 'Корзина пуста.',
        ];
    }
}
