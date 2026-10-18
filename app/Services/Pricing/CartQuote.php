<?php

declare(strict_types=1);

namespace App\Services\Pricing;

use App\Models\Promotion;

/** Расчёт корзины: строки, скидки по акциям, промокод и бесплатная доставка. */
final readonly class CartQuote
{
    /**
     * @param  list<CartLine>  $lines
     * @param  list<int>  $missing  товары, которых больше нет в продаже
     */
    public function __construct(
        public array $lines,
        public array $missing = [],
        public ?Promotion $code = null,
        public ?string $codeError = null,
        public ?Promotion $freeDelivery = null,
    ) {}

    /** Сумма по базовым ценам. */
    public function subtotal(): int
    {
        return array_sum(array_map(fn (CartLine $l) => $l->baseTotal(), $this->lines));
    }

    /** Сумма после скидок на товары, до промокода. */
    public function goodsTotal(): int
    {
        return array_sum(array_map(fn (CartLine $l) => $l->total(), $this->lines));
    }

    public function itemsDiscount(): int
    {
        return $this->subtotal() - $this->goodsTotal();
    }

    /** Промокод не может сделать заказ дешевле нуля. */
    public function codeDiscount(): int
    {
        return $this->code === null ? 0 : min((int) $this->code->value, $this->goodsTotal());
    }

    public function total(): int
    {
        return $this->goodsTotal() - $this->codeDiscount();
    }

    public function freeDeliveryFrom(): ?int
    {
        return $this->freeDelivery?->min_order_amount;
    }

    public function hasFreeDelivery(): bool
    {
        $from = $this->freeDeliveryFrom();

        return $from !== null && $this->lines !== [] && $this->total() >= $from;
    }

    public function leftToFreeDelivery(): int
    {
        $from = $this->freeDeliveryFrom();

        return $from === null ? 0 : max(0, $from - $this->total());
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'items' => array_map(fn (CartLine $l) => [
                'id' => $l->product->id,
                'name' => $l->product->name,
                'icon' => $l->product->icon ?: $l->product->category->icon,
                'qty' => $l->quantity,
                'price' => $l->price->final,
                'old_price' => $l->price->hasDiscount() ? $l->price->base : null,
                'badge' => $l->price->hasDiscount() ? $l->price->promotion?->benefit() : null,
                'total' => $l->total(),
            ], $this->lines),
            'missing' => $this->missing,
            'subtotal' => $this->subtotal(),
            'items_discount' => $this->itemsDiscount(),
            'code' => $this->code === null ? null : [
                'code' => $this->code->code,
                'title' => $this->code->title,
                'amount' => (int) $this->code->value,
                'discount' => $this->codeDiscount(),
            ],
            'code_error' => $this->codeError,
            'total' => $this->total(),
            'delivery' => $this->freeDelivery === null ? null : [
                'free' => $this->hasFreeDelivery(),
                'from' => $this->freeDeliveryFrom(),
                'left' => $this->leftToFreeDelivery(),
            ],
        ];
    }
}
