<?php

declare(strict_types=1);

namespace App\Services\Pricing;

use App\Models\Promotion;

// Та же формула продублирована в геттерах cart.js
final readonly class CartQuote
{
    /**
     * @param  list<CartLine>  $lines
     * @param  list<int>  $missing  сняты с продажи или закончились
     * @param  list<int>  $limited  количество урезано до остатка
     */
    public function __construct(
        public array $lines,
        public array $missing = [],
        public ?Promotion $code = null,
        public ?string $codeError = null,
        public ?Promotion $freeDelivery = null,
        public array $limited = [],
    ) {}

    public function subtotal(): int
    {
        return array_sum(array_map(fn (CartLine $l) => $l->baseTotal(), $this->lines));
    }

    public function goodsTotal(): int
    {
        return array_sum(array_map(fn (CartLine $l) => $l->total(), $this->lines));
    }

    public function itemsDiscount(): int
    {
        return $this->subtotal() - $this->goodsTotal();
    }

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

    public function deliveryPrice(): int
    {
        return $this->lines === [] || $this->hasFreeDelivery() ? 0 : self::deliveryFee();
    }

    public function toPay(): int
    {
        return $this->total() + $this->deliveryPrice();
    }

    public static function deliveryFee(): int
    {
        return max(0, (int) config('shop.delivery_price'));
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
                'stock' => $l->product->stock,
                'price' => $l->price->final,
                'old_price' => $l->price->hasDiscount() ? $l->price->base : null,
                'badge' => $l->price->hasDiscount() ? $l->price->promotion?->benefit() : null,
                'total' => $l->total(),
            ], $this->lines),
            'missing' => $this->missing,
            'limited' => $this->limited,
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
            'delivery_fee' => self::deliveryFee(),
            'delivery_price' => $this->deliveryPrice(),
            'to_pay' => $this->toPay(),
        ];
    }
}
