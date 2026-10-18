<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\PromotionType;
use App\Http\Requests\ControlPanel\StorePromotionRequest;
use App\Http\Requests\ControlPanel\UpdatePromotionRequest;
use App\Models\Product;
use App\Models\Promotion;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PromotionController extends Controller
{
    private const SEARCH_LIMIT = 50;

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Promotion::class);

        $type = PromotionType::tryFrom((string) $request->query('type'));

        return view('control-panel.promotions.index', [
            'promotions' => Promotion::query()
                ->withCount('products')
                ->when($type, fn (Builder $q, PromotionType $t) => $q->where('type', $t))
                ->latest('id')
                ->paginate(20)
                ->withQueryString(),
            'type' => $type,
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Promotion::class);

        return view('control-panel.promotions.form', [
            'promotion' => new Promotion(['type' => PromotionType::Percent]),
            'selected' => $this->selectedRows(null),
        ]);
    }

    public function store(StorePromotionRequest $request): RedirectResponse
    {
        $promotion = DB::transaction(function () use ($request): Promotion {
            $promotion = Promotion::create($request->promotionData());
            $promotion->products()->sync($request->productIds());

            return $promotion;
        });

        return redirect()
            ->route($request->user()?->can('viewAny', Promotion::class)
                ? 'control-panel.promotions.index'
                : 'control-panel.promotions.create')
            ->with('status', "Акция «{$promotion->title}» создана");
    }

    public function edit(Promotion $promotion): View
    {
        $this->authorize('update', $promotion);

        return view('control-panel.promotions.form', [
            'promotion' => $promotion,
            'selected' => $this->selectedRows($promotion),
        ]);
    }

    public function update(UpdatePromotionRequest $request, Promotion $promotion): RedirectResponse
    {
        DB::transaction(function () use ($request, $promotion): void {
            $promotion->update($request->promotionData());
            $promotion->products()->sync($request->productIds());
        });

        return redirect()
            ->route('control-panel.promotions.edit', $promotion)
            ->with('status', "Акция «{$promotion->title}» сохранена");
    }

    public function destroy(Promotion $promotion): RedirectResponse
    {
        $this->authorize('delete', $promotion);

        $promotion->delete();

        return redirect()
            ->route('control-panel.promotions.index')
            ->with('status', "Акция «{$promotion->title}» удалена");
    }

    /**
     * Поиск товаров для формы: по названию, артикулу и разделу.
     * ?except=<id акции> — товары этой акции не считаются занятыми.
     */
    public function products(Request $request): JsonResponse
    {
        $this->authorize('pickProducts', Promotion::class);

        $raw = $request->query('q');
        // % и _ выкидываем: у LIKE в SQLite нет экранирования по умолчанию
        $query = Str::limit(trim(str_replace(['%', '_'], '', is_string($raw) ? $raw : '')), 100, '');
        $except = (int) $request->query('except');

        if (mb_strlen($query) < 2) {
            return response()->json(['items' => [], 'more' => false]);
        }

        $like = '%'.$query.'%';

        $products = Product::query()
            ->with(['category', 'promotions'])
            ->where(fn (Builder $q) => $q
                ->whereLike('name', $like)
                ->orWhereLike('sku', $like)
                ->orWhereHas('category', fn (Builder $c) => $c->whereLike('title', $like)))
            ->orderBy('category_id')
            ->orderBy('id')
            ->limit(self::SEARCH_LIMIT + 1)
            ->get();

        return response()->json([
            'items' => $products->take(self::SEARCH_LIMIT)
                ->map(fn (Product $p) => $this->productRow($p, $except))
                ->values(),
            'more' => $products->count() > self::SEARCH_LIMIT,
        ]);
    }

    /**
     * Выбранные товары для формы. После ошибки валидации берём их из old(),
     * иначе выбор пропал бы вместе с перезагрузкой страницы.
     *
     * @return list<array{id: int, name: string, sku: string, price: int, category: string, active: bool, taken: string|null}>
     */
    private function selectedRows(?Promotion $promotion): array
    {
        $old = old('products');

        $query = match (true) {
            is_array($old) => Product::query()->whereKey(array_map('intval', $old)),
            $promotion !== null => $promotion->products(),
            default => null,
        };

        if ($query === null) {
            return [];
        }

        return $query->with(['category', 'promotions'])->orderBy('name')->get()
            ->map(fn (Product $p) => $this->productRow($p, (int) $promotion?->id))
            ->values()
            ->all();
    }

    /** @return array{id: int, name: string, sku: string, price: int, category: string, active: bool, taken: string|null} */
    private function productRow(Product $product, int $except = 0): array
    {
        $other = $product->relationLoaded('promotions')
            ? $product->promotions->first(fn (Promotion $p) => $p->id !== $except)
            : null;

        return [
            'id' => $product->id,
            'name' => $product->name,
            'sku' => $product->sku,
            'price' => $product->price,
            'category' => $product->category->title,
            'active' => $product->is_active,
            'taken' => $other?->title,
        ];
    }
}
