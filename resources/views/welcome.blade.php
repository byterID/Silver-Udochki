<x-app-layout>
    <section class="shop-page">
        <div class="shop-page__backdrop" aria-hidden="true"></div>

        <div x-data="shopFront(@js($front))" @keydown.window="hotkey($event)">

            {{-- Сцена: фон лавки и всё, что на нём --}}
            <div class="shop-scene">
                <img src="{{ asset('images/shop/shop-bg.webp') }}" alt="" width="1024" height="572"
                     class="shop-scene__bg" fetchpriority="high" draggable="false">

                {{-- Доска акций --}}
                <div x-data="promoBoard(@js($promos))"
                     class="shop-scene__board flex-col font-hand text-white/90"
                     :class="promoFlash && 'is-flashing'" aria-label="Акции">
                    @include('shop.partials.promo-slides')
                </div>

                {{-- Продавец --}}
                <div class="shop-scene__seller">
                    <x-shop-seller />
                </div>

                {{-- Табличка-каталог --}}
                <button type="button" x-ref="catalogStand" @click="openCatalog()"
                        class="catalog-stand" aria-label="Открыть каталог" aria-haspopup="dialog">
                    <span class="catalog-stand__body">
                        <img src="{{ asset('images/shop/catalog-stand.png') }}" alt="" class="catalog-stand__img" draggable="false">
                        <span class="catalog-stand__screen" aria-hidden="true"></span>
                    </span>
                    <span class="catalog-stand__hit" aria-hidden="true"></span>
                </button>

                {{-- Колокольчик --}}
                <button type="button" @click="ringBell()" class="shop-scene__bell"
                        title="Позвонить: продавец спросит что-нибудь ещё" aria-label="Позвонить в колокольчик">🛎️</button>
            </div>

            {{-- Акции на телефоне и узком экране --}}
            <div x-data="promoBoard(@js($promos))"
                 class="promo-mobile mx-3 mt-3 flex-col rounded border-4 border-amber-900 bg-[#2f3b2f] px-4 py-3 font-hand text-lg text-white/90 shadow-lg transition"
                 :class="promoFlash && 'ring-4 ring-yellow-300'">
                <p class="text-center text-xl text-yellow-200">Акции</p>
                @include('shop.partials.promo-slides')
            </div>

            {{-- Диалог с продавцом (на большом экране стоит на прилавке) --}}
            <div class="shop-dock">
                <div class="relative mx-auto max-w-3xl rounded-xl border-4 border-amber-900 bg-stone-900/90 text-amber-50 shadow-2xl backdrop-blur-sm">
                    <span class="absolute -top-3.5 left-4 rounded-md border-2 border-amber-950 bg-amber-800 px-3 py-0.5 text-sm font-bold tracking-wide">
                        Михалыч, продавец
                    </span>

                    <p class="min-h-[3.25rem] cursor-pointer px-4 pt-5 leading-snug lg:text-lg" @click="skip()" aria-live="polite">
                        <span x-text="shown"></span><span x-show="typing" class="animate-pulse">▌</span>
                    </p>

                    <ol class="flex flex-wrap gap-1.5 px-4 pt-2">
                        <template x-for="(opt, i) in options" :key="i">
                            <li>
                                <button type="button" @click="choose(opt)"
                                        class="group flex items-center gap-1.5 rounded-full bg-amber-950/60 py-0.5 pl-0.5 pr-3 text-sm text-amber-200 transition hover:bg-amber-900 hover:text-white">
                                    <span class="inline-flex h-5 w-5 items-center justify-center rounded-full bg-amber-900 text-[11px] font-bold group-hover:bg-amber-600"
                                          x-text="i + 1"></span>
                                    <span x-text="opt.label"></span>
                                </button>
                            </li>
                        </template>
                    </ol>

                    <form @submit.prevent="submit()" action="{{ route('catalog.search') }}" method="GET" class="flex gap-2 px-3 py-2.5">
                        <label for="shop-query" class="sr-only">Ваш ответ продавцу</label>
                        <input id="shop-query" name="q" x-model="query" maxlength="100" autocomplete="off"
                               placeholder="Или скажи своими словами: «блесна на щуку»…"
                               class="min-w-0 flex-1 rounded-md border-2 border-amber-900 bg-stone-800 py-1.5 text-amber-50 placeholder-stone-400 focus:border-amber-500 focus:ring-amber-500">
                        <x-header-button type="submit">Сказать</x-header-button>
                    </form>
                </div>
            </div>

            @include('shop.partials.catalog-monitor')
        </div>
    </section>
</x-app-layout>
