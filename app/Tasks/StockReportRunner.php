<?php

declare(strict_types=1);

namespace App\Tasks;

use App\Enums\StockReason;
use App\Enums\StockState;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Task;
use Generator;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Permission\PermissionRegistrar;

// Остатки, их стоимость по текущей цене и продажи с возвратами за DAYS дней
final class StockReportRunner implements TaskRunner
{
    public const int DAYS = 30;

    public function run(Task $task): string
    {
        // Воркер живёт долго, и права в его памяти устаревают: право могли отозвать, пока задача ждала
        app(PermissionRegistrar::class)->clearPermissionsCollection();

        if (! $task->type->allows($task->user)) {
            throw new AuthorizationException('У автора задачи больше нет права на отчёт об остатках.');
        }

        // Задачу можно создать и в обход StoreTaskRequest, поэтому фильтр проверяем снова
        $state = StockState::tryFrom((string) ($task->payload['state'] ?? ''));

        return CsvResult::save($task, $this->rows($state));
    }

    /** @return Generator<int, list<string|int>> */
    private function rows(?StockState $state): Generator
    {
        $low = (int) config('shop.low_stock');

        $moves = StockMovement::query()
            ->toBase()
            ->select('product_id')
            ->selectRaw('SUM(CASE WHEN reason = ? THEN -delta ELSE 0 END) AS sold', [StockReason::Sale->value])
            ->selectRaw('SUM(CASE WHEN reason = ? THEN delta ELSE 0 END) AS returned', [StockReason::Returned->value])
            ->whereIn('reason', [StockReason::Sale->value, StockReason::Returned->value])
            ->where('created_at', '>=', now()->subDays(self::DAYS))
            ->groupBy('product_id');

        // Один SELECT вместо постраничного чтения: все строки из одного снимка БД
        $products = Product::query()
            ->when($state, fn (Builder $q, StockState $s) => $q->inStockState($s))
            ->toBase()
            ->join('categories', 'categories.id', '=', 'products.category_id')
            ->join('chapters', 'chapters.id', '=', 'categories.chapter_id')
            ->leftJoinSub($moves, 'moves', 'moves.product_id', '=', 'products.id')
            ->select([
                'products.sku', 'products.name', 'products.price', 'products.stock', 'products.is_active',
                'chapters.title as chapter', 'categories.title as category',
            ])
            ->selectRaw('COALESCE(moves.sold, 0) AS sold, COALESCE(moves.returned, 0) AS returned')
            ->orderBy('chapters.sort')
            ->orderBy('categories.sort')
            ->orderBy('products.id')
            ->cursor();

        yield [
            'Артикул', 'Товар', 'Раздел', 'Категория', 'В продаже', 'Цена, ₽', 'Остаток, шт.',
            'Стоимость остатка, ₽', 'Состояние', 'Продано за '.self::DAYS.' дн., шт.', 'Возвращено за '.self::DAYS.' дн., шт.',
        ];

        $count = $units = $value = 0;
        foreach ($products as $row) {
            $price = (int) $row->price;
            $stock = (int) $row->stock;

            yield [
                $row->sku,
                $row->name,
                $row->chapter,
                $row->category,
                $row->is_active ? 'да' : 'нет',
                $price,
                $stock,
                $price * $stock,
                match (true) {
                    $stock === 0 => StockState::Out->label(),
                    $stock <= $low => StockState::Low->label(),
                    default => 'В наличии',
                },
                (int) $row->sold,
                (int) $row->returned,
            ];

            $count++;
            $units += $stock;
            $value += $price * $stock;
        }

        yield [];
        yield ['Итого позиций', $count, '', '', '', '', $units, $value];
        yield ['Отбор', $state === null ? 'весь склад' : $state->label()];
        yield ['Сформирован', now()->format('d.m.Y H:i:s T')];
    }
}
