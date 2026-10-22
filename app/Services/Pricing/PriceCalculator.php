<?php

declare(strict_types=1);

namespace App\Services\Pricing;

use App\Enums\PromotionType;
use App\Models\Product;
use App\Models\Promotion;
use Illuminate\Support\Facades\DB;

// Единственное место расчёта цен со скидками. Scoped: акции грузятся раз за запрос
class PriceCalculator
{
    public const MAX_QTY = 999;

    /** @var array<int, Promotion>|null product_id => акция */
    private ?array $percentByProduct = null;

    // false — уже искали и не нашли
    private Promotion|false|null $freeDelivery = null;

    // Скидка округляется вниз (в пользу лавки), цена не меньше 1 ₽
    public static function applyPercent(int $price, int $percent): int
    {
        if ($price <= 0 || $percent <= 0) {
            return $price;
        }

        return max(1, $price - intdiv($price * min($percent, 99), 100));
    }

    public function priceFor(Product $product): ProductPrice
    {
        $promotion = $this->percentPromotions()[$product->id] ?? null;

        if ($promotion === null) {
            return new ProductPrice($product->price, $product->price);
        }

        return new ProductPrice(
            $product->price,
            self::applyPercent($product->price, (int) $promotion->value),
            $promotion,
        );
    }

    /** @param  array<int, int>  $quantities  product_id => количество */
    public function quote(array $quantities, ?string $code = null): CartQuote
    {
        $quantities = array_filter($quantities, fn (int $qty) => $qty > 0);

        $products = $quantities === []
            ? collect()
            : Product::query()->active()->with('category')->whereKey(array_keys($quantities))->get()->keyBy('id');

        $lines = [];
        $missing = [];
        $limited = [];
        foreach ($quantities as $id => $qty) {
            $product = $products->get($id);

            if ($product === null || $product->stock < 1) {
                $missing[] = $id;

                continue;
            }
            $available = min($product->stock, self::MAX_QTY);
            if ($qty > $available) {
                $limited[] = $id;
            }
            $lines[] = new CartLine($product, min($qty, $available), $this->priceFor($product));
        }

        $promotion = null;
        $error = null;
        if (($code = Promotion::normalizeCode($code)) !== null) {
            [$promotion, $error] = $this->checkCode($code);
        }

        return new CartQuote(
            lines: $lines,
            missing: $missing,
            code: $promotion,
            codeError: $error,
            freeDelivery: $this->freeDelivery(),
            limited: $limited,
        );
    }

    /** @return array{0: Promotion|null, 1: string|null} */
    public function checkCode(string $code): array
    {
        $promotion = Promotion::query()
            ->where('type', PromotionType::Fixed)
            ->where('code', Promotion::normalizeCode($code))
            ->first();

        $error = match (true) {
            $promotion === null => 'Такого промокода нет.',
            ! $promotion->is_active => 'Промокод отключён.',
            $promotion->starts_at !== null && $promotion->starts_at->isFuture() => 'Промокод ещё не начал действовать.',
            $promotion->ends_at !== null && ! $promotion->ends_at->isFuture() => 'Срок действия промокода истёк.',
            $promotion->isExhausted() => 'Промокод уже израсходован.',
            default => null,
        };

        return $error === null ? [$promotion, null] : [null, $error];
    }

    // Из нескольких акций действует самый низкий порог
    public function freeDelivery(): ?Promotion
    {
        if ($this->freeDelivery === null) {
            $this->freeDelivery = Promotion::query()
                ->available()
                ->where('type', PromotionType::FreeDelivery)
                ->orderBy('min_order_amount')
                ->first() ?? false;
        }

        return $this->freeDelivery ?: null;
    }

    /** @return array<int, Promotion> */
    private function percentPromotions(): array
    {
        if ($this->percentByProduct !== null) {
            return $this->percentByProduct;
        }

        $promotions = Promotion::query()
            ->available()
            ->where('type', PromotionType::Percent)
            ->get()
            ->keyBy('id');

        if ($promotions->isEmpty()) {
            return $this->percentByProduct = [];
        }

        $map = [];
        $links = DB::table('promotion_product')
            ->whereIn('promotion_id', $promotions->keys())
            ->get(['promotion_id', 'product_id']);

        foreach ($links as $link) {
            $map[(int) $link->product_id] = $promotions[(int) $link->promotion_id];
        }

        return $this->percentByProduct = $map;
    }
}
