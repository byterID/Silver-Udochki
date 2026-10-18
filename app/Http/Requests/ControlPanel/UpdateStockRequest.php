<?php

declare(strict_types=1);

namespace App\Http\Requests\ControlPanel;

use App\Enums\StockReason;
use App\Models\StockMovement;
use App\Services\Stock\StockKeeper;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Поступление (+N) или инвентаризация (ровно N) по одному товару. */
class UpdateStockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', StockMovement::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', Rule::enum(StockReason::class)->only(StockReason::manual())],
            'quantity' => [
                'required', 'integer',
                'min:'.($this->input('reason') === StockReason::Restock->value ? 1 : 0),
                'max:'.StockKeeper::MAX_STOCK,
            ],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function attributes(): array
    {
        return [
            'reason' => 'действие',
            'quantity' => 'количество',
            'note' => 'комментарий',
        ];
    }

    public function reason(): StockReason
    {
        return StockReason::from($this->string('reason')->toString());
    }

    public function quantity(): int
    {
        return $this->integer('quantity');
    }

    public function note(): ?string
    {
        $note = trim($this->string('note')->toString());

        return $note === '' ? null : $note;
    }
}
