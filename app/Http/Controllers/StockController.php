<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\StockReason;
use App\Enums\StockState;
use App\Http\Requests\ControlPanel\UpdateStockRequest;
use App\Models\Product;
use App\Models\StockMovement;
use App\Services\Stock\StockException;
use App\Services\Stock\StockKeeper;
use App\Support\SearchTerm;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class StockController extends Controller
{
    public function __construct(private readonly StockKeeper $stock) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', StockMovement::class);

        $query = SearchTerm::from($request->query('q'));
        $state = StockState::tryFrom((string) $request->query('state'));

        $products = Product::query()
            ->with('category')
            ->when($query !== '', fn (Builder $q) => $q->where(fn (Builder $w) => $w
                ->whereLike('name', '%'.$query.'%')
                ->orWhereLike('sku', '%'.$query.'%')))
            // В отфильтрованном списке первыми те, что кончаются быстрее
            ->when($state, fn (Builder $q, StockState $s) => $q->inStockState($s)->orderBy('stock'))
            ->orderBy('category_id')
            ->orderBy('id')
            ->paginate(30)
            ->withQueryString();

        return view('control-panel.stock.index', [
            'products' => $products,
            'query' => $query,
            'state' => $state,
            'counts' => collect(StockState::cases())
                ->mapWithKeys(fn (StockState $s) => [$s->value => Product::query()->inStockState($s)->count()])
                ->all(),
        ]);
    }

    public function update(UpdateStockRequest $request, Product $product): RedirectResponse
    {
        try {
            $movement = match ($request->reason()) {
                StockReason::Restock => $this->stock->restock($product, $request->quantity(), $request->user(), $request->note()),
                StockReason::Inventory => $this->stock->setCount($product, $request->quantity(), $request->user(), $request->note()),
                // UpdateStockRequest их не пропускает
                StockReason::Sale, StockReason::Returned => throw new StockException('Продажи и возвраты проводят только заказы.'),
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
}
