<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\StockReason;
use App\Http\Requests\ControlPanel\UpdateStockRequest;
use App\Models\Product;
use App\Models\StockMovement;
use App\Services\Stock\StockException;
use App\Services\Stock\StockKeeper;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/** Склад в панели управления: остатки, поступление, инвентаризация и журнал. */
class StockController extends Controller
{
    /** Фильтры списка: ключ из ?state= */
    public const STATES = [
        'low' => 'Осталось мало',
        'out' => 'Нет в наличии',
    ];

    public function __construct(private readonly StockKeeper $stock) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', StockMovement::class);

        $query = $this->searchQuery($request);
        $state = array_key_exists((string) $request->query('state'), self::STATES) ? (string) $request->query('state') : null;
        $low = (int) config('shop.low_stock');

        $products = Product::query()
            ->with('category')
            ->when($query !== '', fn (Builder $q) => $q->where(fn (Builder $w) => $w
                ->whereLike('name', '%'.$query.'%')
                ->orWhereLike('sku', '%'.$query.'%')))
            ->when($state === 'low', fn (Builder $q) => $q->whereBetween('stock', [1, $low]))
            ->when($state === 'out', fn (Builder $q) => $q->where('stock', 0))
            // В отфильтрованных списках первыми те, что кончаются быстрее
            ->when($state !== null, fn (Builder $q) => $q->orderBy('stock'))
            ->orderBy('category_id')
            ->orderBy('id')
            ->paginate(30)
            ->withQueryString();

        return view('control-panel.stock.index', [
            'products' => $products,
            'query' => $query,
            'state' => $state,
            'counts' => [
                'low' => Product::query()->whereBetween('stock', [1, $low])->count(),
                'out' => Product::query()->where('stock', 0)->count(),
            ],
        ]);
    }

    public function update(UpdateStockRequest $request, Product $product): RedirectResponse
    {
        try {
            $movement = match ($request->reason()) {
                StockReason::Restock => $this->stock->restock($product, $request->quantity(), $request->user(), $request->note()),
                StockReason::Inventory => $this->stock->setCount($product, $request->quantity(), $request->user(), $request->note()),
            };
        } catch (StockException $e) {
            return back()->withErrors(['quantity' => $e->getMessage()]);
        }

        $status = $movement === null
            ? "«{$product->name}»: остаток не изменился ({$product->stock} шт.)"
            : sprintf('«%s»: %s %+d шт., теперь %d шт.', $product->name, mb_strtolower($movement->reason->label()), $movement->delta, $movement->stock_after);

        return back()->with('status', $status);
    }

    public function movements(Request $request): View
    {
        $this->authorize('viewAny', StockMovement::class);

        $product = $request->filled('product')
            ? Product::query()->find((int) $request->query('product'))
            : null;

        return view('control-panel.stock.movements', [
            'movements' => StockMovement::query()
                ->with('user')
                ->when($product, fn (Builder $q, Product $p) => $q->where('product_id', $p->id))
                ->latest('id')
                ->paginate(50)
                ->withQueryString(),
            'product' => $product,
        ]);
    }

    private function searchQuery(Request $request): string
    {
        $raw = $request->query('q');

        // % и _ выкидываем: у LIKE в SQLite нет экранирования по умолчанию
        return Str::limit(trim(str_replace(['%', '_'], '', is_string($raw) ? $raw : '')), 100, '');
    }
}
