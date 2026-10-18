@php($money = fn (int $v) => number_format($v, 0, ',', ' ').' ₽')

<x-app-layout>
    <div class="mx-auto max-w-6xl px-4 py-6 sm:px-6 lg:px-8"
         x-data="duckGame(@js($setup))" @keydown.window="onKey($event)">

        <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
            <div>
                <h1 class="text-2xl font-semibold text-emerald-950">🦆 Утиная зорька</h1>
                <p class="text-sm text-stone-600">Подстрелите уток — получите виртуальные рубли на кошелёк.</p>
            </div>
            <p class="rounded-full bg-amber-100 px-4 py-1.5 text-sm text-amber-900">
                Можно заработать за сутки:
                <span class="font-semibold" x-text="$store.cart.money(dailyLeft)">{{ $money($setup['dailyLeft']) }}</span>
                из {{ $money($dailyLimit) }}
            </p>
        </div>

        <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_17rem]">
            <section>
                {{-- Приборы: время, патроны, добыча --}}
                <div class="mb-2 flex flex-wrap items-center gap-x-5 gap-y-2 rounded-t-xl bg-emerald-950 px-4 py-2 text-sm text-amber-50">
                    <span class="flex items-center gap-2">
                        ⏱ <span class="w-8 font-mono font-semibold" x-text="phase === 'playing' ? seconds + ' с' : '—'">—</span>
                        <span class="h-2 w-24 overflow-hidden rounded-full bg-emerald-800 sm:w-36" aria-hidden="true">
                            <span class="block h-full bg-amber-400 transition-[width] duration-100" :style="`width: ${(1 - progress) * 100}%`"></span>
                        </span>
                    </span>

                    <span class="flex items-center gap-1" :aria-label="`Патронов: ${shells}`">
                        <template x-for="i in magazine" :key="i">
                            <span class="inline-block h-4 w-2.5 rounded-sm border border-red-300"
                                  :class="i <= shells && !reloading ? 'bg-red-600' : 'bg-transparent opacity-50'"></span>
                        </template>
                        <span x-show="reloading" x-cloak class="ml-1 text-amber-300">перезарядка…</span>
                    </span>

                    <span>Подбито: <span class="font-semibold" x-text="`${hits} из ${ducks}`">0 из 0</span></span>
                    <span>Очки: <span class="font-semibold" x-text="score">0</span></span>

                    <button type="button" @click="reload()" x-show="phase === 'playing'" x-cloak
                            class="ml-auto rounded-md bg-amber-700 px-3 py-1 font-medium hover:bg-amber-600">
                        Перезарядить <kbd class="hidden text-xs opacity-75 sm:inline">R</kbd>
                    </button>
                </div>

                <div class="relative overflow-hidden rounded-b-xl bg-emerald-950 shadow-lg">
                    <canvas x-ref="canvas" class="block w-full cursor-none touch-none select-none"
                            style="aspect-ratio: 16 / 9"
                            role="img" aria-label="Озеро на рассвете: утки взлетают из камыша, ретривер ждёт на берегу"></canvas>

                    {{-- Перед раундом --}}
                    <div x-show="phase === 'idle' || phase === 'starting'"
                         class="absolute inset-0 flex items-center justify-center bg-emerald-950/45 p-4">
                        <div class="max-w-sm rounded-xl bg-amber-50/95 p-5 text-center shadow-xl">
                            <p class="text-lg font-semibold text-emerald-950">Раунд — {{ config('game.duration') }} секунд, {{ config('game.ducks') }} уток</p>
                            <p class="mt-1 text-sm text-stone-600">
                                Клик или тап — выстрел. В двустволке два патрона: перезарядка правой кнопкой, клавишей R или сама, когда патроны кончатся.
                            </p>
                            <button type="button" @click="start()" :disabled="phase === 'starting'"
                                    class="mt-4 rounded-lg bg-emerald-800 px-6 py-2.5 font-semibold text-amber-50 shadow hover:bg-emerald-700 disabled:opacity-60">
                                <span x-text="phase === 'starting' ? 'Утки поднимаются…' : 'На зорьку!'">На зорьку!</span>
                            </button>
                            <p x-show="error" x-cloak x-text="error" class="mt-3 text-sm text-red-700"></p>
                        </div>
                    </div>

                    {{-- Пока сервер считает --}}
                    <div x-show="phase === 'judging'" x-cloak
                         class="absolute inset-x-0 bottom-4 flex justify-center">
                        <p class="rounded-full bg-emerald-950/80 px-4 py-2 text-sm text-amber-50">Михалыч считает уток…</p>
                    </div>

                    {{-- Итог раунда --}}
                    <div x-show="phase === 'result'" x-cloak
                         class="absolute inset-0 flex items-center justify-center bg-emerald-950/45 p-4">
                        <div class="w-full max-w-sm rounded-xl bg-amber-50/95 p-5 text-center shadow-xl">
                            <template x-if="result">
                                <div>
                                    <p class="text-lg font-semibold text-emerald-950" x-text="`Подбито ${result.hits} из ${result.ducks}`"></p>
                                    <p class="text-sm text-stone-600" x-text="`Очков: ${result.score}`"></p>
                                    <p class="mt-3 text-2xl font-bold"
                                       :class="result.reward > 0 ? 'text-emerald-700' : 'text-stone-500'"
                                       x-text="result.reward > 0 ? '+' + $store.cart.money(result.reward) : 'Без денег'"></p>
                                    <p x-show="capped" class="mt-1 text-xs text-amber-800">
                                        Упёрлись в суточный лимит: остальные очки засчитаны без денег.
                                    </p>
                                </div>
                            </template>
                            <p x-show="error" x-text="error" class="text-sm text-red-700"></p>

                            <div class="mt-4 flex justify-center gap-2">
                                <button type="button" x-show="canRetry" @click="retry()"
                                        class="rounded-lg bg-amber-700 px-4 py-2 font-semibold text-amber-50 hover:bg-amber-600">
                                    Отправить ещё раз
                                </button>
                                <button type="button" @click="start()"
                                        class="rounded-lg bg-emerald-800 px-5 py-2 font-semibold text-amber-50 hover:bg-emerald-700">
                                    Ещё раунд
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <aside class="space-y-4">
                {{-- Михалыч комментирует из-за плеча --}}
                <div class="flex items-start gap-3">
                    <img src="{{ asset('images/shop/mikhalych.webp') }}" alt="Продавец Михалыч" width="72" height="72"
                         class="h-[72px] w-[72px] shrink-0 rounded-full bg-amber-100 object-cover object-top ring-2 ring-amber-400/70">
                    <p class="relative rounded-xl bg-white px-3 py-2 text-sm text-stone-700 shadow-sm ring-1 ring-stone-200"
                       aria-live="polite" x-text="quip"></p>
                </div>

                <div class="rounded-xl bg-white p-4 text-sm shadow-sm ring-1 ring-stone-200">
                    <h2 class="mb-2 font-semibold text-emerald-950">Добыча</h2>
                    <ul class="space-y-1">
                        @foreach ($kinds as $kind)
                            <li class="flex justify-between gap-2">
                                <span>{{ $kind['label'] }}</span>
                                <span class="text-stone-500">{{ $kind['points'] }} оч. · {{ $money($kind['reward']) }}</span>
                            </li>
                        @endforeach
                    </ul>
                    <p class="mt-2 text-xs text-stone-500">
                        Чем мельче и быстрее утка, тем дороже. Попадания засчитывает сервер.
                    </p>
                </div>

                <div class="rounded-xl bg-white p-4 text-sm shadow-sm ring-1 ring-stone-200">
                    <h2 class="mb-2 font-semibold text-emerald-950">Последние зорьки</h2>
                    <p x-show="recent.length === 0" class="text-stone-500">Пока ни одной. Самое время начать.</p>
                    <ul class="divide-y divide-stone-100">
                        <template x-for="(r, i) in recent" :key="i">
                            <li class="flex justify-between gap-2 py-1.5">
                                <span class="text-stone-500" x-text="r.at"></span>
                                <span x-text="`${r.hits}/${r.ducks}`"></span>
                                <span class="font-medium" :class="r.reward > 0 ? 'text-emerald-700' : 'text-stone-400'"
                                      x-text="r.reward > 0 ? '+' + $store.cart.money(r.reward) : '0 ₽'"></span>
                            </li>
                        </template>
                    </ul>
                </div>
            </aside>
        </div>
    </div>
</x-app-layout>
