<?php

declare(strict_types=1);

namespace App\Services\Pricing;

use App\Models\Promotion;

/** Цена товара сейчас: базовая, итоговая и акция, которая её снизила. */
final readonly class ProductPrice
{
    public function __construct(
        public int $base,
        public int $final,
        public ?Promotion $promotion = null,
    ) {}

    public function discount(): int
    {
        return $this->base - $this->final;
    }

    public function hasDiscount(): bool
    {
        return $this->final < $this->base;
    }
}
