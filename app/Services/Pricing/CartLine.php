<?php

declare(strict_types=1);

namespace App\Services\Pricing;

use App\Models\Product;

final readonly class CartLine
{
    public function __construct(
        public Product $product,
        public int $quantity,
        public ProductPrice $price,
    ) {}

    public function baseTotal(): int
    {
        return $this->price->base * $this->quantity;
    }

    public function total(): int
    {
        return $this->price->final * $this->quantity;
    }
}
