@extends('control-panel.layout')

@section('panel-content')
    @php
        $statuses = [
            'running' => ['Действует', 'bg-green-100 text-green-700'],
            'scheduled' => ['Запланирована', 'bg-sky-100 text-sky-700'],
            'ended' => ['Завершилась', 'bg-gray-100 text-gray-500'],
            'exhausted' => ['Израсходована', 'bg-amber-100 text-amber-700'],
            'disabled' => ['Выключена', 'bg-gray-100 text-gray-500'],
        ];
        $fmt = fn ($date) => $date?->translatedFormat('d.m.Y H:i');
        $canDelete = auth()->user()->can('delete', new App\Models\Promotion);
        $canUpdate = auth()->user()->can('update', new App\Models\Promotion);
    @endphp

    <div class="mb-6 flex items-center justify-between">
        <h1 class="text-2xl font-semibold text-gray-800">Акции</h1>
        <div class="flex items-center gap-3">
            <span class="rounded-full bg-indigo-100 px-3 py-1 text-sm text-indigo-700">
                Всего: {{ $promotions->total() }}
            </span>
            @can('create', App\Models\Promotion::class)
                <a href="{{ route('control-panel.promotions.create') }}"
                   class="inline-flex items-center gap-1.5 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-indigo-700">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    Создать акцию
                </a>
            @endcan
        </div>
    </div>

    @if (session('status'))
        <div class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-green-700">
            {{ session('status') }}
        </div>
    @endif

    {{-- Фильтр по виду --}}
    <div class="mb-4 flex flex-wrap gap-2 text-sm">
        <a href="{{ route('control-panel.promotions.index') }}"
           class="rounded-full px-3 py-1 {{ $type === null ? 'bg-indigo-600 text-white' : 'bg-white text-gray-600 ring-1 ring-gray-200 hover:bg-gray-50' }}">Все</a>
        @foreach (App\Enums\PromotionType::cases() as $case)
            <a href="{{ route('control-panel.promotions.index', ['type' => $case->value]) }}"
               class="rounded-full px-3 py-1 {{ $type === $case ? 'bg-indigo-600 text-white' : 'bg-white text-gray-600 ring-1 ring-gray-200 hover:bg-gray-50' }}">{{ $case->label() }}</a>
        @endforeach
    </div>

    <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50">
            <tr>
                <th class="px-4 py-3 text-left font-semibold text-gray-600">Акция</th>
                <th class="px-4 py-3 text-left font-semibold text-gray-600">Вид</th>
                <th class="px-4 py-3 text-left font-semibold text-gray-600">Период</th>
                <th class="px-4 py-3 text-left font-semibold text-gray-600">Статус</th>
                <th class="px-4 py-3 text-left font-semibold text-gray-600">Охват</th>
                <th class="px-4 py-3"></th>
            </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
            @forelse ($promotions as $promotion)
                @php
                    $status = $promotion->status();
                    $endingSoon = $status === 'running' && $promotion->ends_at?->lte(now()->addDay());
                @endphp
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3">
                        <div class="flex items-center gap-3">
                            <span class="h-6 w-6 shrink-0 rounded-full ring-1 ring-black/10" style="background: {{ $promotion->color }}"></span>
                            <div class="min-w-0">
                                <p class="font-medium text-gray-800">{{ $promotion->title }}</p>
                                @if ($promotion->note)
                                    <p class="text-xs text-gray-500">{{ $promotion->note }}</p>
                                @endif
                            </div>
                        </div>
                    </td>
                    <td class="px-4 py-3 text-gray-600">
                        {{ $promotion->type->shortLabel() }}
                        <span class="font-semibold text-gray-800">{{ $promotion->benefit() }}</span>
                    </td>
                    <td class="whitespace-nowrap px-4 py-3 text-gray-600">
                        @if (! $promotion->starts_at && ! $promotion->ends_at)
                            бессрочно
                        @else
                            {{ $promotion->starts_at ? 'с '.$fmt($promotion->starts_at) : '' }}
                            @if ($promotion->ends_at)
                                <br>по {{ $fmt($promotion->ends_at) }}
                            @endif
                        @endif
                    </td>
                    <td class="px-4 py-3">
                        <span class="whitespace-nowrap rounded-full px-2.5 py-0.5 text-xs font-medium {{ $statuses[$status][1] }}">
                            {{ $statuses[$status][0] }}
                        </span>
                        @if ($endingSoon)
                            <span class="mt-1 block whitespace-nowrap text-xs font-medium text-red-600">⏳ меньше суток</span>
                        @endif
                    </td>
                    <td class="whitespace-nowrap px-4 py-3 text-gray-600">
                        @switch($promotion->type)
                            @case(App\Enums\PromotionType::Percent)
                                {{ $promotion->products_count }} {{ trans_choice('товар|товара|товаров', $promotion->products_count, [], 'ru') }}
                                @break
                            @case(App\Enums\PromotionType::Fixed)
                                <span class="font-mono font-semibold text-gray-800">{{ $promotion->code }}</span>
                                <br><span class="text-xs">использован {{ $promotion->used_count }}{{ $promotion->max_uses ? ' из '.$promotion->max_uses : '' }}</span>
                                @break
                            @default
                                весь заказ
                        @endswitch
                    </td>
                    <td class="whitespace-nowrap px-4 py-3 text-right">
                        @if ($canUpdate)
                            <a href="{{ route('control-panel.promotions.edit', $promotion) }}"
                               class="rounded-md px-2.5 py-1 text-indigo-600 hover:bg-indigo-50">Изменить</a>
                        @endif
                        @if ($canDelete)
                            <form method="POST" action="{{ route('control-panel.promotions.destroy', $promotion) }}" class="inline"
                                  onsubmit="return confirm(@js('Удалить акцию «'.$promotion->title.'»?'))">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="rounded-md px-2.5 py-1 text-red-600 hover:bg-red-50">Удалить</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="px-4 py-8 text-center text-gray-400">Акций пока нет</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $promotions->links() }}</div>
@endsection
