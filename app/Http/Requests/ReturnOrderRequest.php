<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\ReturnReason;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Форма «Оформить возврат»: какие строки заказа и сколько штук, причина,
 * комментарий и ключ попытки. Сумму к возврату считает сервер.
 */
class ReturnOrderRequest extends FormRequest
{
    public const MAX_ITEMS = 100;

    public const MAX_COMMENT = 1000;

    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1', 'max:'.self::MAX_ITEMS],
            'items.*.id' => ['required', 'integer', 'min:1', 'distinct'],
            'items.*.qty' => ['required', 'integer', 'min:1', 'max:999'],
            'reason' => ['required', Rule::enum(ReturnReason::class)],
            'comment' => [
                Rule::requiredIf(fn () => $this->reason()?->needsComment() === true),
                'nullable', 'string', 'max:'.self::MAX_COMMENT,
            ],
            'key' => ['required', 'uuid'],
        ];
    }

    public function messages(): array
    {
        return [
            'items.required' => 'Отметьте, что возвращаете.',
            'items.min' => 'Отметьте, что возвращаете.',
            'reason.required' => 'Выберите причину возврата.',
            'comment.required' => 'Опишите причину возврата.',
        ];
    }

    /** @return array<int, int> order_item_id => сколько штук */
    public function quantities(): array
    {
        /** @var list<array{id: int|string, qty: int|string}> $items */
        $items = $this->validated('items');

        $quantities = [];
        foreach ($items as $item) {
            $quantities[(int) $item['id']] = (int) $item['qty'];
        }

        return $quantities;
    }

    public function reason(): ?ReturnReason
    {
        return ReturnReason::tryFrom($this->string('reason')->toString());
    }

    public function comment(): ?string
    {
        $comment = trim($this->string('comment')->toString());

        return $comment === '' ? null : $comment;
    }
}
