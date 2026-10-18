<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Заказ для окна «Мои заказы» и ответа на оформление.
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
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn (OrderItem $item) => [
                'name' => $item->name,
                'sku' => $item->sku,
                'qty' => $item->quantity,
                'price' => $item->unit_price - $item->unit_discount,
                'old_price' => $item->unit_discount > 0 ? $item->unit_price : null,
                'total' => $item->lineTotal(),
            ])->all()),
        ];
    }
}
