<?php

declare(strict_types=1);

namespace App\Services\Stock;

use App\Enums\StockReason;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use Closure;
use Illuminate\Support\Facades\DB;

/**
 * Единственное место, где меняется products.stock. Каждое изменение —
 * строка в журнале stock_movements с дельтой и остатком после неё.
 */
class StockKeeper
{
    public const MAX_STOCK = 1_000_000;

    /** Поступление: остаток растёт на $quantity. */
    public function restock(Product $product, int $quantity, ?User $by = null, ?string $note = null): StockMovement
    {
        if ($quantity < 1) {
            throw new StockException('Поступление должно быть не меньше одной штуки.');
        }

        /** @var StockMovement */
        return $this->change($product, fn (int $stock) => $stock + $quantity, StockReason::Restock, $by, $note);
    }

    /** Инвентаризация: остаток ставится точным числом. null — число не изменилось. */
    public function setCount(Product $product, int $count, ?User $by = null, ?string $note = null): ?StockMovement
    {
        return $this->change($product, fn () => $count, StockReason::Inventory, $by, $note);
    }

    /** @param Closure(int): int $next новый остаток из текущего */
    private function change(Product $product, Closure $next, StockReason $reason, ?User $by, ?string $note): ?StockMovement
    {
        $movement = DB::transaction(function () use ($product, $next, $reason, $by, $note): ?StockMovement {
            // Блокировка строки: два одновременных изменения не затрут друг друга,
            // а остаток считается от свежего значения, а не от того, что в $product
            $locked = Product::query()->lockForUpdate()->findOrFail($product->id);
            $after = $next($locked->stock);

            if ($after < 0) {
                throw new StockException("Остаток «{$locked->name}» не может стать отрицательным.");
            }
            if ($after > self::MAX_STOCK) {
                throw new StockException('Слишком большой остаток: не больше '.number_format(self::MAX_STOCK, 0, ',', ' ').' шт.');
            }

            $delta = $after - $locked->stock;
            if ($delta === 0) {
                return null;
            }

            $locked->update(['stock' => $after]);

            return StockMovement::create([
                'product_id' => $locked->id,
                'sku' => $locked->sku,
                'name' => $locked->name,
                'delta' => $delta,
                'stock_after' => $after,
                'reason' => $reason,
                'user_id' => $by?->id,
                'note' => $note,
            ]);
        });

        // Вызывающий код продолжает работать со своим экземпляром товара
        if ($movement !== null) {
            $product->stock = $movement->stock_after;
            $product->syncOriginalAttribute('stock');
        }

        return $movement;
    }
}
