<?php

declare(strict_types=1);

namespace App\Tasks;

use App\Enums\StockReason;
use App\Http\Controllers\StockController;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Task;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\PermissionRegistrar;

/**
 * Снимок склада в CSV: остаток, его стоимость по текущей цене и движение за последние дни.
 * Формат как у демо-отчёта: «;», BOM, чтобы русский Excel открыл файл без настройки.
 */
final class StockReportRunner implements TaskRunner
{
    private const string SEP = ';';

    /** За сколько дней считать продажи и возвраты. */
    public const int DAYS = 30;

    public function run(Task $task): string
    {
        // Воркер живёт долго, а права в памяти не обновляются сами. Сбрасываем копию
        // в процессе (не общий кеш): право могли отозвать, пока задача ждала в очереди.
        app(PermissionRegistrar::class)->clearPermissionsCollection();

        if (! $task->type->allows($task->user)) {
            throw new AuthorizationException('У автора задачи больше нет права на отчёт об остатках.');
        }

        // Фильтр проверен в StoreTaskRequest, но задачу можно создать и в обход формы
        $state = $task->payload['state'] ?? null;
        $state = is_string($state) && array_key_exists($state, StockController::STATES) ? $state : null;
        $low = (int) config('shop.low_stock');

        // Продажи и возвраты за период одним подзапросом. У продажи дельта отрицательная
        $moves = StockMovement::query()
            ->toBase()
            ->select('product_id')
            ->selectRaw('SUM(CASE WHEN reason = ? THEN -delta ELSE 0 END) AS sold', [StockReason::Sale->value])
            ->selectRaw('SUM(CASE WHEN reason = ? THEN delta ELSE 0 END) AS returned', [StockReason::Returned->value])
            ->whereIn('reason', [StockReason::Sale->value, StockReason::Returned->value])
            ->where('created_at', '>=', now()->subDays(self::DAYS))
            ->groupBy('product_id');

        // Один SELECT вместо постраничного чтения: все строки отчёта из одного снимка БД,
        // даже если покупатели сейчас оформляют заказы
        $rows = Product::query()
            ->toBase()
            ->join('categories', 'categories.id', '=', 'products.category_id')
            ->join('chapters', 'chapters.id', '=', 'categories.chapter_id')
            ->leftJoinSub($moves, 'moves', 'moves.product_id', '=', 'products.id')
            ->select([
                'products.sku', 'products.name', 'products.price', 'products.stock', 'products.is_active',
                'chapters.title as chapter', 'categories.title as category',
            ])
            ->selectRaw('COALESCE(moves.sold, 0) AS sold, COALESCE(moves.returned, 0) AS returned')
            ->when($state === 'low', fn (Builder $q) => $q->whereBetween('products.stock', [1, $low]))
            ->when($state === 'out', fn (Builder $q) => $q->where('products.stock', 0))
            ->orderBy('chapters.sort')
            ->orderBy('categories.sort')
            ->orderBy('products.id')
            ->cursor();

        $stream = fopen('php://temp', 'r+');
        fwrite($stream, "\xEF\xBB\xBF"); // BOM: Excel поймёт, что файл в UTF-8

        $this->put($stream, [
            'Артикул', 'Товар', 'Раздел', 'Категория', 'В продаже', 'Цена, ₽', 'Остаток, шт.',
            'Стоимость остатка, ₽', 'Состояние', 'Продано за '.self::DAYS.' дн., шт.', 'Возвращено за '.self::DAYS.' дн., шт.',
        ]);

        $count = $units = $value = 0;
        foreach ($rows as $row) {
            $price = (int) $row->price;
            $stock = (int) $row->stock;

            $this->put($stream, [
                $row->sku,
                $row->name,
                $row->chapter,
                $row->category,
                $row->is_active ? 'да' : 'нет',
                $price,
                $stock,
                $price * $stock,
                match (true) {
                    $stock === 0 => 'Нет в наличии',
                    $stock <= $low => 'Осталось мало',
                    default => 'В наличии',
                },
                (int) $row->sold,
                (int) $row->returned,
            ]);

            $count++;
            $units += $stock;
            $value += $price * $stock;
        }

        $this->put($stream, []);
        $this->put($stream, ['Итого позиций', $count, '', '', '', '', $units, $value]);
        $this->put($stream, ['Отбор', $state === null ? 'весь склад' : StockController::STATES[$state]]);
        $this->put($stream, ['Сформирован', now()->format('d.m.Y H:i:s T')]);

        rewind($stream); // иначе Storage прочитает пустоту

        $path = "results/{$task->id}.csv";
        Storage::disk('local')->put($path, $stream);
        fclose($stream);

        return $path;
    }

    /**
     * @param  resource  $stream
     * @param  list<string|int>  $row
     */
    private function put($stream, array $row): void
    {
        // escape передаём явно: в PHP 8.4 значение по умолчанию устарело
        fputcsv($stream, $row, self::SEP, '"', '');
    }
}
