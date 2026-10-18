@extends('control-panel.layout')

@section('panel-content')
    @php
        $canUpdate = auth()->user()->can('create', App\Models\StockMovement::class);
        $money = fn (int $v) => number_format($v, 0, ',', ' ').' ₽';
    @endphp

    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-2xl font-semibold text-gray-800">Склад</h1>
        <div class="flex items-center gap-3">
            <span class="rounded-full bg-indigo-100 px-3 py-1 text-sm text-indigo-700">
                Позиций: {{ $products->total() }}
            </span>
            <a href="{{ route('control-panel.stock.movements') }}"
               class="rounded-lg bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm ring-1 ring-gray-200 transition hover:bg-gray-50">
                📜 Журнал движения
            </a>
        </div>
    </div>

    @if (session('status'))
        <div class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-green-700">
            {{ session('status') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-red-700">
            {{ $errors->first() }}
        </div>
    @endif

    {{-- Поиск и фильтр --}}
    <div class="mb-4 flex flex-wrap items-center gap-3">
        <form method="GET" action="{{ route('control-panel.stock.index') }}" class="flex gap-2">
            @if ($state)
                <input type="hidden" name="state" value="{{ $state }}">
            @endif
            <label for="stock-q" class="sr-only">Поиск</label>
            <input id="stock-q" name="q" value="{{ $query }}" maxlength="100" placeholder="Название или артикул"
                   class="w-64 rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
            <button type="submit" class="rounded-lg bg-indigo-600 px-4 text-sm font-medium text-white hover:bg-indigo-700">Найти</button>
        </form>

        <div class="flex flex-wrap gap-2 text-sm">
            <a href="{{ route('control-panel.stock.index', array_filter(['q' => $query])) }}"
               class="rounded-full px-3 py-1 {{ $state === null ? 'bg-indigo-600 text-white' : 'bg-white text-gray-600 ring-1 ring-gray-200 hover:bg-gray-50' }}">Все</a>
            @foreach (App\Http\Controllers\StockController::STATES as $key => $label)
                <a href="{{ route('control-panel.stock.index', array_filter(['q' => $query, 'state' => $key])) }}"
                   class="rounded-full px-3 py-1 {{ $state === $key ? 'bg-indigo-600 text-white' : 'bg-white text-gray-600 ring-1 ring-gray-200 hover:bg-gray-50' }}">
                    {{ $label }} <span class="opacity-70">· {{ $counts[$key] }}</span>
                </a>
            @endforeach
        </div>
    </div>

    <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50">
            <tr>
                <th class="px-4 py-3 text-left font-semibold text-gray-600">Товар</th>
                <th class="px-4 py-3 text-left font-semibold text-gray-600">Раздел</th>
                <th class="px-4 py-3 text-right font-semibold text-gray-600">Цена</th>
                <th class="px-4 py-3 text-right font-semibold text-gray-600">Остаток</th>
                @if ($canUpdate)
                    <th class="px-4 py-3 text-right font-semibold text-gray-600">Изменить</th>
                @endif
            </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
            @forelse ($products as $product)
                <tr class="hover:bg-gray-50 {{ $product->is_active ? '' : 'opacity-60' }}">
                    <td class="px-4 py-3">
                        <p class="font-medium text-gray-800">{{ $product->name }}</p>
                        <p class="font-mono text-xs text-gray-500">
                            {{ $product->sku }}
                            @unless ($product->is_active)
                                <span class="ml-1 font-sans">· снят с продажи</span>
                            @endunless
                        </p>
                    </td>
                    <td class="px-4 py-3 text-gray-600">{{ $product->category->title }}</td>
                    <td class="whitespace-nowrap px-4 py-3 text-right text-gray-600">{{ $money($product->price) }}</td>
                    <td class="whitespace-nowrap px-4 py-3 text-right">
                        <a href="{{ route('control-panel.stock.movements', ['product' => $product->id]) }}" title="Журнал по товару"
                           @class([
                               'rounded-full px-2.5 py-0.5 font-semibold hover:underline',
                               'bg-gray-100 text-gray-500' => $product->stock === 0,
                               'bg-red-100 text-red-700' => $product->isLowStock(),
                               'bg-green-100 text-green-700' => $product->stock > 0 && ! $product->isLowStock(),
                           ])>{{ $product->stock }} шт.</a>
                    </td>
                    @if ($canUpdate)
                        <td class="whitespace-nowrap px-4 py-3">
                            <form method="POST" action="{{ route('control-panel.stock.update', $product) }}"
                                  class="flex items-center justify-end gap-1.5">
                                @csrf
                                @method('PUT')
                                <label for="qty-{{ $product->id }}" class="sr-only">Количество</label>
                                <input id="qty-{{ $product->id }}" type="number" name="quantity" min="0"
                                       max="{{ App\Services\Stock\StockKeeper::MAX_STOCK }}" required placeholder="шт."
                                       class="w-20 rounded-md border-gray-300 py-1 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <button type="submit" name="reason" value="{{ App\Enums\StockReason::Restock->value }}"
                                        title="Добавить к остатку"
                                        class="rounded-md bg-green-600 px-2.5 py-1 font-medium text-white hover:bg-green-700">+ Приход</button>
                                <button type="submit" name="reason" value="{{ App\Enums\StockReason::Inventory->value }}"
                                        title="Поставить ровно столько"
                                        class="rounded-md px-2.5 py-1 font-medium text-indigo-600 ring-1 ring-indigo-200 hover:bg-indigo-50">= Пересчёт</button>
                            </form>
                        </td>
                    @endif
                </tr>
            @empty
                <tr><td colspan="{{ $canUpdate ? 5 : 4 }}" class="px-4 py-8 text-center text-gray-400">Ничего не нашлось</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $products->links() }}</div>
@endsection
