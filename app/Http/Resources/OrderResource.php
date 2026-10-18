<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Заказ для окна «Мои заказы» и ответов на оформление и возврат.
 * Заказ загружается через Order::withReturns().
 *
 * @mixin Order
 */
final class OrderResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number(),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'created_at' => $this->created_at->toIso8601String(),
            'subtotal' => $this->subtotal,
            'items_discount' => $this->items_discount,
            'code_discount' => $this->code_discount,
            'promo_code' => $this->promo_code,
            'delivery_price' => $this->delivery_price,
            'total' => $this->total,
            'refunded' => $this->refundedAmount(),
            'returnable' => $this->isReturnable(),
            'return_until' => $this->returnDeadline()->toIso8601String(),
            'items' => $this->items->map(fn (OrderItem $item) => [
                'id' => $item->id,
                'name' => $item->name,
                'sku' => $item->sku,
                'qty' => $item->quantity,
                'returned' => $item->returnedQuantity(),
                'price' => $item->unitTotal(),
                'old_price' => $item->unit_discount > 0 ? $item->unit_price : null,
                'total' => $item->lineTotal(),
            ])->all(),
        ];
    }
}
