<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Services\Pricing\PriceCalculator;
use Illuminate\Foundation\Http\FormRequest;

class CartQuoteRequest extends FormRequest
{
    public const MAX_ITEMS = 100;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'items' => ['present', 'array', 'max:'.self::MAX_ITEMS],
            'items.*.id' => ['required', 'integer', 'min:1'],
            'items.*.qty' => ['required', 'integer', 'min:1', 'max:'.PriceCalculator::MAX_QTY],
        ];
    }

    /** @return array<int, int> product_id => количество, повторы строк сложены */
    public function quantities(): array
    {
        $result = [];
        /** @var list<array{id: int|string, qty: int|string}> $items */
        $items = $this->validated('items', []);

        foreach ($items as $item) {
            $id = (int) $item['id'];
            $result[$id] = ($result[$id] ?? 0) + (int) $item['qty'];
        }

        return $result;
    }
}
