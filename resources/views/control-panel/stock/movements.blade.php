@extends('control-panel.layout')

@section('panel-content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <div>
            <a href="{{ route('control-panel.stock.index') }}" class="text-sm text-indigo-600 hover:underline">← Склад</a>
            <h1 class="mt-1 text-2xl font-semibold text-gray-800">
                Журнал движения
                @if ($product)
                    <span class="text-gray-500">· {{ $product->name }}</span>
                @endif
            </h1>
        </div>
        @if ($product)
            <a href="{{ route('control-panel.stock.movements') }}"
               class="rounded-lg bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm ring-1 ring-gray-200 hover:bg-gray-50">
                Все товары
            </a>
        @endif
    </div>

    <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50">
            <tr>
                <th class="px-4 py-3 text-left font-semibold text-gray-600">Когда</th>
                <th class="px-4 py-3 text-left font-semibold text-gray-600">Товар</th>
                <th class="px-4 py-3 text-left font-semibold text-gray-600">Действие</th>
                <th class="px-4 py-3 text-right font-semibold text-gray-600">Изменение</th>
                <th class="px-4 py-3 text-right font-semibold text-gray-600">Стало</th>
                <th class="px-4 py-3 text-left font-semibold text-gray-600">Кто</th>
            </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
            @forelse ($movements as $movement)
                <tr class="hover:bg-gray-50">
                    <td class="whitespace-nowrap px-4 py-3 text-gray-600">{{ $movement->created_at->translatedFormat('d.m.Y H:i') }}</td>
                    <td class="px-4 py-3">
                        <a href="{{ route('control-panel.stock.movements', ['product' => $movement->product_id]) }}"
                           class="font-medium text-gray-800 hover:underline">{{ $movement->name }}</a>
                        <p class="font-mono text-xs text-gray-500">{{ $movement->sku }}</p>
                    </td>
                    <td class="px-4 py-3 text-gray-600">
                        {{ $movement->reason->label() }}
                        @if ($movement->note)
                            <p class="text-xs text-gray-500">{{ $movement->note }}</p>
                        @endif
                    </td>
                    <td class="whitespace-nowrap px-4 py-3 text-right font-semibold {{ $movement->delta > 0 ? 'text-green-700' : 'text-red-700' }}">
                        {{ $movement->delta > 0 ? '+' : '−' }}{{ abs($movement->delta) }}
                    </td>
                    <td class="whitespace-nowrap px-4 py-3 text-right text-gray-800">{{ $movement->stock_after }} шт.</td>
                    <td class="px-4 py-3 text-gray-600">{{ $movement->user?->name ?? 'система' }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="px-4 py-8 text-center text-gray-400">Движений пока не было</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $movements->links() }}</div>
@endsection
