@extends('control-panel.layout')

@use('App\Enums\PromotionType')

@section('panel-content')
    @php
        $editing = $promotion->exists;
        $local = fn ($date) => $date?->format('Y-m-d\TH:i');
        $type = old('type', $promotion->type?->value ?? PromotionType::Percent->value);

        $config = [
            'type' => $type,
            'title' => old('title', $promotion->title ?? ''),
            'note' => old('note', $promotion->note ?? ''),
            'color' => old('color', $promotion->color ?? App\Models\Promotion::DEFAULT_COLOR),
            'endsAt' => old('ends_at', $local($promotion->ends_at) ?? ''),
            'code' => old('code', $promotion->code ?? ''),
            'selected' => $selected,
            'searchUrl' => route('control-panel.promotions.products'),
            'except' => $promotion->id,
        ];

        $input = 'w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-400 focus:ring-indigo-400';
        $label = 'mb-1 block text-sm font-medium text-gray-700';
    @endphp

    <div x-data="promotionForm(@js($config))">
        <div class="mb-6 flex items-center justify-between gap-4">
            <div>
                <a href="{{ route('control-panel.promotions.index') }}" class="text-sm text-indigo-600 hover:underline">← Все акции</a>
                <h1 class="mt-1 text-2xl font-semibold text-gray-800">
                    {{ $editing ? 'Акция «'.$promotion->title.'»' : 'Новая акция' }}
                </h1>
            </div>

            @if ($editing)
                @can('delete', $promotion)
                    <form method="POST" action="{{ route('control-panel.promotions.destroy', $promotion) }}"
                          onsubmit="return confirm(@js('Удалить акцию «'.$promotion->title.'»?'))">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="rounded-lg px-4 py-2 text-sm font-medium text-red-600 ring-1 ring-red-200 hover:bg-red-50">
                            Удалить акцию
                        </button>
                    </form>
                @endcan
            @endif
        </div>

        @if (session('status'))
            <div class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-green-700">
                {{ session('status') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                Акция не сохранена: проверьте поля, отмеченные красным.
            </div>
        @endif

        <form method="POST"
              action="{{ $editing ? route('control-panel.promotions.update', $promotion) : route('control-panel.promotions.store') }}"
              class="grid gap-6 xl:grid-cols-[1fr_20rem]">
            @csrf
            @if ($editing)
                @method('PUT')
            @endif

            <div class="flex flex-col gap-6">
                {{-- Вид акции --}}
                <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                    <h2 class="mb-3 font-semibold text-gray-800">Вид акции</h2>
                    <div class="grid gap-3 sm:grid-cols-3">
                        @foreach ([
                            [PromotionType::Percent, '％', 'Скидка в процентах на выбранные товары. Действует сама.'],
                            [PromotionType::Fixed, '🎟️', 'Сумма со всего заказа, если покупатель ввёл промокод.'],
                            [PromotionType::FreeDelivery, '🚚', 'Бесплатная доставка, когда заказ не меньше порога.'],
                        ] as [$case, $icon, $hint])
                            <label class="flex cursor-pointer gap-3 rounded-lg border p-3 transition"
                                   :class="type === @js($case->value) ? 'border-indigo-500 bg-indigo-50 ring-1 ring-indigo-500' : 'border-gray-200 hover:bg-gray-50'">
                                <input type="radio" name="type" value="{{ $case->value }}" x-model="type" class="mt-1 text-indigo-600 focus:ring-indigo-500">
                                <span>
                                    <span class="block font-medium text-gray-800">{{ $icon }} {{ $case->label() }}</span>
                                    <span class="mt-0.5 block text-xs text-gray-500">{{ $hint }}</span>
                                </span>
                            </label>
                        @endforeach
                    </div>
                    @error('type') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
                </section>

                {{-- Основное --}}
                <section class="grid gap-4 rounded-xl border border-gray-200 bg-white p-5 shadow-sm sm:grid-cols-2">
                    <h2 class="font-semibold text-gray-800 sm:col-span-2">Что видит покупатель</h2>

                    <div class="sm:col-span-2">
                        <label for="title" class="{{ $label }}">Название на доске</label>
                        <input id="title" type="text" name="title" x-model="title" required maxlength="255"
                               placeholder="например: −15% на все блёсны" class="{{ $input }}">
                        @error('title') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div class="sm:col-span-2">
                        <label for="note" class="{{ $label }}">Подпись <span class="font-normal text-gray-400">(необязательно)</span></label>
                        <input id="note" type="text" name="note" x-model="note" maxlength="255"
                               placeholder="например: только по утрам" class="{{ $input }}">
                        @error('note') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    {{-- Размер выгоды --}}
                    <div x-show="type === 'percent'">
                        <label for="value-percent" class="{{ $label }}">Скидка, %</label>
                        <input id="value-percent" type="number" name="value" min="1" max="99" step="1"
                               value="{{ old('value', $promotion->type === PromotionType::Percent ? $promotion->value : '') }}"
                               :disabled="type !== 'percent'" class="{{ $input }}">
                    </div>
                    <div x-show="type === 'fixed'" x-cloak>
                        <label for="value-fixed" class="{{ $label }}">Скидка, ₽</label>
                        <input id="value-fixed" type="number" name="value" min="1" max="1000000" step="1"
                               value="{{ old('value', $promotion->type === PromotionType::Fixed ? $promotion->value : '') }}"
                               :disabled="type !== 'fixed'" class="{{ $input }}">
                    </div>
                    <div x-show="type === 'free_delivery'" x-cloak>
                        <label for="min_order_amount" class="{{ $label }}">Заказ от, ₽</label>
                        <input id="min_order_amount" type="number" name="min_order_amount" min="0" step="1"
                               value="{{ old('min_order_amount', $promotion->min_order_amount) }}"
                               :disabled="type !== 'free_delivery'" class="{{ $input }}">
                        @error('min_order_amount') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    @error('value') <p class="text-sm text-red-600 sm:col-span-2">{{ $message }}</p> @enderror

                    {{-- Промокод --}}
                    <template x-if="type === 'fixed'">
                        <div class="grid gap-4 sm:col-span-2 sm:grid-cols-2">
                            <div>
                                <label for="code" class="{{ $label }}">Промокод</label>
                                <div class="flex gap-2">
                                    <input id="code" type="text" name="code" x-model="code" maxlength="32"
                                           @input="code = code.toUpperCase()"
                                           placeholder="RYBAK500" class="{{ $input }} font-mono uppercase">
                                    <button type="button" @click="generateCode()"
                                            class="shrink-0 rounded-md px-3 text-sm text-indigo-600 ring-1 ring-indigo-200 hover:bg-indigo-50"
                                            title="Придумать случайный">🎲</button>
                                </div>
                                <p class="mt-1 text-xs text-gray-500">Латиница, цифры, «-» и «_». Регистр не важен.</p>
                            </div>
                            <div>
                                <label for="max_uses" class="{{ $label }}">Лимит использований <span class="font-normal text-gray-400">(пусто — без лимита)</span></label>
                                <input id="max_uses" type="number" name="max_uses" min="1" step="1"
                                       value="{{ old('max_uses', $promotion->max_uses) }}" class="{{ $input }}">
                                @if ($editing && $promotion->type === PromotionType::Fixed)
                                    <p class="mt-1 text-xs text-gray-500">Уже использован: {{ $promotion->used_count }}</p>
                                @endif
                            </div>
                        </div>
                    </template>
                    @error('code') <p class="text-sm text-red-600 sm:col-span-2">{{ $message }}</p> @enderror
                    @error('max_uses') <p class="text-sm text-red-600 sm:col-span-2">{{ $message }}</p> @enderror
                </section>

                {{-- Товары --}}
                <section x-show="type === 'percent'" class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                    <div class="mb-3 flex flex-wrap items-baseline justify-between gap-2">
                        <h2 class="font-semibold text-gray-800">Товары со скидкой</h2>
                        <span class="text-sm text-gray-500">
                            Выбрано: <span class="font-semibold text-gray-800" x-text="selected.length"></span>
                            <button type="button" x-show="selected.length" @click="clearAll()" class="ml-2 text-red-600 hover:underline">снять все</button>
                        </span>
                    </div>

                    <template x-if="type === 'percent'">
                        <div>
                            <template x-for="p in selected" :key="p.id">
                                <input type="hidden" name="products[]" :value="p.id">
                            </template>
                        </div>
                    </template>

                    {{-- Выбранные --}}
                    <div x-show="selected.length" class="mb-4 flex max-h-48 flex-wrap gap-1.5 overflow-y-auto rounded-lg bg-gray-50 p-2">
                        <template x-for="p in selected" :key="p.id">
                            <span class="inline-flex items-center gap-1 rounded-full bg-white py-0.5 pl-2.5 pr-1 text-xs ring-1"
                                  :class="p.taken ? 'ring-red-300 text-red-700' : 'ring-gray-200 text-gray-700'"
                                  :title="p.taken ? 'Уже в акции «' + p.taken + '»' : p.category">
                                <span x-text="p.name"></span>
                                <button type="button" @click="remove(p.id)" class="h-4 w-4 rounded-full leading-4 text-gray-400 hover:bg-gray-200 hover:text-gray-700"
                                        :aria-label="'Убрать ' + p.name">×</button>
                            </span>
                        </template>
                    </div>

                    {{-- Поиск --}}
                    <div class="relative">
                        <label for="product-search" class="sr-only">Поиск товаров</label>
                        <input id="product-search" type="search" x-model="query" @input="search()" @keydown.enter.prevent="search(true)"
                               autocomplete="off" placeholder="Название, артикул или раздел: «блесна», «SU-ROD», «Удочки»…"
                               class="{{ $input }} pr-10">
                        <span x-show="loading" class="absolute right-3 top-2 animate-spin text-gray-400">◌</span>
                    </div>

                    <div x-show="results.length" class="mt-3 overflow-hidden rounded-lg border border-gray-200">
                        <div class="flex items-center justify-between bg-gray-50 px-3 py-2 text-xs text-gray-500">
                            <span>
                                Найдено: <span x-text="results.length"></span><span x-show="more">+ (уточните запрос)</span>
                            </span>
                            <button type="button" @click="selectAllFound()" class="font-medium text-indigo-600 hover:underline">
                                Выбрать все найденные
                            </button>
                        </div>
                        <ul class="max-h-80 divide-y divide-gray-100 overflow-y-auto">
                            <template x-for="p in results" :key="p.id">
                                <li>
                                    <label class="flex items-center gap-3 px-3 py-2 text-sm"
                                           :class="p.taken ? 'cursor-not-allowed bg-gray-50 text-gray-400' : 'cursor-pointer hover:bg-indigo-50/50'">
                                        <input type="checkbox" :checked="isSelected(p.id)" :disabled="!!p.taken" @change="toggle(p)"
                                               class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                        <span class="min-w-0 flex-1">
                                            <span class="block truncate" :class="!p.taken && 'text-gray-800'" x-text="p.name"></span>
                                            <span class="block text-xs text-gray-400">
                                                <span class="font-mono" x-text="p.sku"></span> · <span x-text="p.category"></span>
                                                <span x-show="!p.active" class="text-amber-600"> · снят с продажи</span>
                                                <span x-show="p.taken" class="text-red-500" x-text="' · уже в акции «' + p.taken + '»'"></span>
                                            </span>
                                        </span>
                                        <span class="shrink-0 text-gray-600" x-text="money(p.price)"></span>
                                    </label>
                                </li>
                            </template>
                        </ul>
                    </div>
                    <p x-show="searched && !loading && !results.length" class="mt-3 text-sm text-gray-400">Ничего не нашлось.</p>
                    <p x-show="error" x-text="error" class="mt-3 text-sm text-red-600"></p>

                    @error('products') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
                </section>

                {{-- Период --}}
                <section class="grid gap-4 rounded-xl border border-gray-200 bg-white p-5 shadow-sm sm:grid-cols-2">
                    <h2 class="font-semibold text-gray-800 sm:col-span-2">Когда действует</h2>
                    <div>
                        <label for="starts_at" class="{{ $label }}">Начало <span class="font-normal text-gray-400">(пусто — сразу)</span></label>
                        <input id="starts_at" type="datetime-local" name="starts_at"
                               value="{{ old('starts_at', $local($promotion->starts_at)) }}" class="{{ $input }}">
                        @error('starts_at') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="ends_at" class="{{ $label }}">Окончание <span class="font-normal text-gray-400">(пусто — бессрочно)</span></label>
                        <input id="ends_at" type="datetime-local" name="ends_at" x-model="endsAt" class="{{ $input }}">
                        @error('ends_at') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <label class="flex items-center gap-2 text-sm text-gray-700 sm:col-span-2">
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $promotion->is_active ?? true))
                               class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                        Акция включена
                        <span class="text-gray-400">(выключенная не показывается и не действует)</span>
                    </label>
                </section>
            </div>

            {{-- Справа: цвет и превью --}}
            <aside class="flex flex-col gap-6">
                <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                    <h2 class="mb-3 font-semibold text-gray-800">Цвет мела</h2>
                    <div class="flex flex-wrap gap-2">
                        <template x-for="c in presets" :key="c">
                            <button type="button" @click="color = c" :aria-label="'Цвет ' + c"
                                    class="h-8 w-8 rounded-full ring-1 ring-black/10 transition"
                                    :class="color === c && 'ring-2 ring-indigo-500 ring-offset-2'"
                                    :style="{ background: c }"></button>
                        </template>
                        <label class="relative h-8 w-8 cursor-pointer overflow-hidden rounded-full ring-1 ring-black/10" title="Свой цвет">
                            <input type="color" name="color" x-model="color" class="absolute -inset-2 h-12 w-12 cursor-pointer">
                        </label>
                    </div>
                    @error('color') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
                </section>

                <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                    <h2 class="mb-3 font-semibold text-gray-800">Так будет на доске</h2>
                    <div class="flex min-h-28 flex-col items-center justify-center rounded border-4 border-amber-900 bg-[#2f3b2f] px-4 py-3 text-center font-hand text-xl text-white/90"
                         :class="'promo-stage-' + stage">
                        <p class="promo-title chalk leading-tight" :style="{ color }" x-text="title || 'Название акции'"></p>
                        <p class="mt-1 text-sm leading-tight text-white/60">
                            <span x-show="note" x-text="note"></span>
                            <span x-show="endsAt" class="promo-timer" x-text="(note ? ' · ' : '') + timerText"></span>
                        </p>
                        <p x-show="stageLabel" class="promo-alarm mt-1 text-xs" x-text="stageLabel"></p>
                    </div>
                    <p class="mt-3 text-xs text-gray-500">
                        За 3 дня до конца таймер желтеет, в последние сутки акция пульсирует и краснеет,
                        в последний час мигает чаще.
                    </p>
                </section>

                <div class="flex items-center gap-3">
                    <button type="submit" class="flex-1 rounded-lg bg-indigo-600 px-5 py-2.5 text-sm font-medium text-white shadow-sm hover:bg-indigo-700">
                        {{ $editing ? 'Сохранить' : 'Создать акцию' }}
                    </button>
                    <a href="{{ route('control-panel.promotions.index') }}" class="text-sm text-gray-500 hover:text-gray-700">Отмена</a>
                </div>
            </aside>
        </form>
    </div>
@endsection
