<?php

declare(strict_types=1);

namespace App\Http\Requests;

/**
 * Оформление: та же корзина, что и для пересчёта, плюс сумма, которую видел
 * покупатель, и ключ попытки. Цены по-прежнему считает сервер.
 */
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
