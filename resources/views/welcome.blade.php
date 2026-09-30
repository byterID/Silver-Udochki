<x-app-layout>
    <x-shop-wall class="relative overflow-hidden">
        <div x-data="shopFront(@js($front))" @keydown.window="hotkey($event)">

            {{-- свет лампы --}}
            <div class="pointer-events-none absolute inset-x-0 top-0 h-80 bg-[radial-gradient(ellipse_at_top,rgba(255,221,150,.35),transparent_70%)]"></div>

            <div class="relative mx-auto max-w-6xl px-4 pt-6">

                {{-- Задняя стена: полки, продавец, доска акций --}}
                <div class="relative grid grid-cols-1 items-end gap-4 md:grid-cols-[1fr_auto_1fr]">

                    {{-- Полки с товаром (декор) --}}
                    <div class="hidden flex-col gap-10 self-start pt-8 md:flex" aria-hidden="true">
                        @foreach ([['🎣', '🪝', '🧵', '🥄'], ['🦆', '🥾', '🔦', '🎒']] as $shelf)
                            <div>
                                <div class="flex justify-around px-2 text-3xl drop-shadow">
                                    @foreach ($shelf as $item)<span>{{ $item }}</span>@endforeach
                                </div>
                                <div class="h-3 rounded-sm bg-gradient-to-b from-amber-600 to-amber-900 shadow-[0_6px_8px_rgba(0,0,0,.4)]"></div>
                            </div>
                        @endforeach
                    </div>

                    {{-- Продавец --}}
                    <div class="relative z-0 mx-auto -mb-10 w-44 sm:w-60">
                        <x-shop-seller />
                    </div>

                    {{-- Доска акций --}}
                    <div class="order-first self-start md:order-none md:pt-2">
                        <div class="mx-auto max-w-xs rotate-1 rounded border-8 border-amber-900 bg-[#2f3b2f] p-4 font-hand text-white/90 shadow-xl transition duration-300"
                             :class="promoFlash && 'scale-105 ring-4 ring-yellow-300'">
                            <p class="text-center text-2xl tracking-wide text-yellow-200">Акции</p>
                            <ul class="mt-2 space-y-1 text-lg leading-snug">
                                @forelse ($promos as $promo)
                                    <li>
                                        — {{ $promo['title'] }}
                                        @if (!empty($promo['note']))
                                            <span class="text-sm text-white/60">({{ $promo['note'] }})</span>
                                        @endif
                                    </li>
                                @empty
                                    <li class="text-white/60">Скоро будут…</li>
                                @endforelse
                            </ul>
                        </div>
                    </div>
                </div>

                {{-- Прилавок --}}
                <div class="counter-planks relative z-10 h-24 rounded-t-lg border-t-8 border-amber-600 shadow-[0_-6px_20px_rgba(0,0,0,.4)] sm:h-28">

                    {{-- Книга-каталог --}}
                    <button type="button" @click="openBook()"
                            class="group absolute -top-20 left-3 h-28 w-20 -rotate-6 transition hover:-translate-y-2 hover:-rotate-2 sm:-top-28 sm:left-10 sm:h-36 sm:w-28"
                            aria-label="Открыть каталог">
                        <span class="absolute inset-0 rounded-l-sm rounded-r-md border-l-[10px] border-red-950 bg-gradient-to-br from-red-800 to-red-950 shadow-[6px_8px_0_rgba(0,0,0,.35)]"></span>
                        <span class="relative flex h-full flex-col items-center justify-center pl-2 text-amber-300">
                            <span class="text-2xl sm:text-3xl">🐟</span>
                            <span class="mt-1 text-[10px] font-bold tracking-[.2em] sm:text-xs">КАТАЛОГ</span>
                            <span class="mt-1 h-px w-10 bg-amber-300/60"></span>
                        </span>
                    </button>

                    {{-- Колокольчик --}}
                    <button type="button" @click="ringBell()"
                            class="absolute -top-9 right-[30%] text-4xl transition hover:-rotate-12 active:scale-90"
                            title="Позвонить: продавец спросит что-нибудь ещё" aria-label="Позвонить в колокольчик">
                        🛎️
                    </button>
                </div>

                {{-- Диалог --}}
                <div class="relative z-20 mx-auto mt-8 max-w-3xl rounded-xl border-4 border-amber-900 bg-stone-900/95 text-amber-50 shadow-2xl">
                    <span class="absolute -top-4 left-4 rounded-md border-2 border-amber-950 bg-amber-800 px-3 py-0.5 text-sm font-bold tracking-wide">
                        Михалыч, продавец
                    </span>

                    <p class="min-h-[4rem] cursor-pointer px-5 pt-6 text-lg leading-snug" @click="skip()" aria-live="polite">
                        <span x-text="shown"></span><span x-show="typing" class="animate-pulse">▌</span>
                    </p>

                    <ol class="grid gap-x-6 gap-y-1 px-5 pt-3 sm:grid-cols-2">
                        <template x-for="(opt, i) in options" :key="i">
                            <li>
                                <button type="button" @click="choose(opt)"
                                        class="group flex w-full items-center gap-2 py-1 text-left text-amber-200 hover:text-white">
                                    <span class="inline-flex h-6 w-6 items-center justify-center rounded bg-amber-900/70 text-xs font-bold group-hover:bg-amber-600"
                                          x-text="i + 1"></span>
                                    <span x-text="opt.label"></span>
                                </button>
                            </li>
                        </template>
                    </ol>

                    <form @submit.prevent="submit()" action="{{ route('catalog.search') }}" method="GET" class="flex gap-2 p-4">
                        <label for="shop-query" class="sr-only">Ваш ответ продавцу</label>
                        <input id="shop-query" name="q" x-model="query" maxlength="100" autocomplete="off"
                               placeholder="Или скажи своими словами: «блесна на щуку»…"
                               class="flex-1 rounded-md border-2 border-amber-900 bg-stone-800 text-amber-50 placeholder-stone-400 focus:border-amber-500 focus:ring-amber-500">
                        <x-header-button type="submit">Сказать</x-header-button>
                    </form>
            </div>

            {{-- Открытая книга-каталог --}}
            <div x-show="bookOpen" x-cloak x-transition.opacity
                 @click.self="bookOpen = false"
                 class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4"
                 role="dialog" aria-modal="true" aria-label="Каталог">
                <div x-show="bookOpen"
                     x-transition:enter="transition duration-300 ease-out"
                     x-transition:enter-start="opacity-0 scale-90 rotate-[-4deg]"
                     x-transition:enter-end="opacity-100 scale-100 rotate-0"
                     class="relative grid max-h-[90vh] w-full max-w-4xl overflow-y-auto rounded-lg border-8 border-red-950 bg-[#f3e7c9] text-stone-800 shadow-2xl md:grid-cols-2">

                    {{-- корешок --}}
                    <div class="pointer-events-none absolute inset-y-0 left-1/2 hidden w-10 -translate-x-1/2 bg-gradient-to-r from-transparent via-black/20 to-transparent md:block"></div>

                    <button type="button" @click="bookOpen = false" class="absolute right-3 top-2 z-10 text-2xl text-stone-500 hover:text-stone-900" aria-label="Закрыть">✕</button>

                    {{-- Левая страница: главы --}}
                    <div class="p-6 sm:p-8">
                        <h2 class="font-hand text-4xl text-red-950">Каталог лавки</h2>
                        <p class="mt-1 text-sm text-stone-600">Выбери главу, а дальше нужный раздел.</p>

                        <nav class="mt-6 space-y-2">
                            @foreach ($chapters as $key => $chapter)
                                <button type="button" @click="chapter = '{{ $key }}'"
                                        :class="chapter === '{{ $key }}' ? 'translate-x-2 bg-red-900 text-amber-50' : 'bg-amber-100 hover:bg-amber-200'"
                                        class="flex w-full items-center gap-3 rounded-r-full px-4 py-3 text-left font-semibold transition">
                                    <span class="text-2xl">{{ $chapter['icon'] }}</span>
                                    {{ $chapter['title'] }}
                                </button>
                            @endforeach
                        </nav>

                        <p class="mt-8 font-hand text-lg text-stone-500">Не нашёл? Спроси у Михалыча, он найдёт.</p>
                    </div>

                    {{-- Правая страница: разделы --}}
                    <div class="border-t border-amber-900/20 p-6 sm:p-8 md:border-t-0">
                        @foreach ($chapters as $key => $chapter)
                            <div x-show="chapter === '{{ $key }}'" x-transition.opacity.duration.200ms>
                                <h3 class="border-b-2 border-amber-900/30 pb-2 font-hand text-3xl">{{ $chapter['title'] }}</h3>
                                <ul class="mt-2 divide-y divide-amber-900/15">
                                    @foreach ($chapter['categories'] as $slug => $category)
                                        <li>
                                            <a href="{{ route('catalog.category', $slug) }}" class="group flex gap-3 py-3">
                                                <span class="text-3xl">{{ $category['icon'] }}</span>
                                                <span>
                                                    <span class="block font-semibold group-hover:text-red-900 group-hover:underline">{{ $category['title'] }}</span>
                                                    <span class="block text-sm text-stone-600">{{ $category['description'] }}</span>
                                                </span>
                                                <span class="ml-auto self-center text-stone-400 transition group-hover:translate-x-1">→</span>
                                            </a>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </x-shop-wall>
</x-app-layout>
