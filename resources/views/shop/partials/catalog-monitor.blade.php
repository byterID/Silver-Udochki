<div x-show="catalogOpen" x-cloak
     x-transition:enter="transition duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
     x-transition:leave="transition duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
     @click.self="closeCatalog()"
     class="fixed inset-0 z-50 flex items-center justify-center bg-black/75 p-2 backdrop-blur-sm sm:p-6"
     role="dialog" aria-modal="true" aria-labelledby="pc-title">

    <div class="flex w-full max-w-5xl flex-col items-center" @click.self="closeCatalog()">
        <div class="pc-bezel w-full">
            <div class="pc-screen" x-ref="pcScreen" tabindex="-1">
                <div class="pc-screen__content flex h-full flex-col">

                    {{-- Заголовок окна --}}
                    <header class="flex items-center gap-3 bg-[#2f4a3a] px-4 py-2 text-amber-50">
                        <span class="text-lg" aria-hidden="true">🐟</span>
                        <h2 id="pc-title" class="truncate font-semibold tracking-wide">Серебряные удочки · Каталог</h2>
                        <span class="ml-auto font-mono text-sm text-amber-100/70" x-text="clock"></span>
                        <button type="button" @click="closeCatalog()"
                                class="rounded bg-red-800/80 px-2 leading-6 hover:bg-red-700" aria-label="Закрыть каталог">✕</button>
                    </header>

                    {{-- Панель: назад, путь, поиск --}}
                    <div class="flex flex-wrap items-center gap-2 border-b border-amber-900/20 bg-[#eadcb8] px-4 py-2">
                        <button type="button" x-show="category" @click="category = null"
                                class="rounded-full border border-amber-900/30 px-3 py-1 text-sm hover:bg-amber-100">← Назад</button>
                        <nav class="min-w-0 truncate text-sm text-stone-600" aria-label="Путь">
                            <button type="button" @click="category = null" class="hover:underline"
                                    x-text="catalog.chapters[chapter]?.title"></button>
                            <template x-if="currentCategory">
                                <span> / <span class="font-semibold text-stone-800" x-text="currentCategory.title"></span></span>
                            </template>
                        </nav>
                        <form action="{{ route('catalog.search') }}" method="GET" role="search" class="flex w-full gap-2 sm:ml-auto sm:w-auto">
                            <label for="pc-search" class="sr-only">Поиск по лавке</label>
                            <input id="pc-search" name="q" maxlength="100" autocomplete="off" placeholder="Поиск по лавке…"
                                   class="min-w-0 flex-1 rounded-full border-amber-900/30 bg-white/70 px-4 py-1.5 text-sm focus:border-[#2f4a3a] focus:ring-[#2f4a3a] sm:w-56">
                            <button type="submit" class="pc-btn">Найти</button>
                        </form>
                    </div>

                    {{-- Главы и содержимое --}}
                    <div class="grid min-h-0 flex-1 grid-rows-[auto_1fr] sm:grid-cols-[12rem_1fr] sm:grid-rows-1">
                        <aside class="flex gap-1 overflow-x-auto border-b border-amber-900/20 bg-[#efe3c4] p-2 sm:flex-col sm:border-b-0 sm:border-r">
                            <template x-for="(ch, key) in catalog.chapters" :key="key">
                                <button type="button" @click="pickChapter(key)"
                                        class="flex shrink-0 items-center gap-2 rounded-lg px-3 py-2 text-left font-semibold transition"
                                        :class="chapter === key ? 'bg-[#2f4a3a] text-amber-50 shadow-inner' : 'text-stone-700 hover:bg-amber-200/70'">
                                    <span class="text-xl" x-text="ch.icon"></span>
                                    <span x-text="ch.title"></span>
                                </button>
                            </template>
                        </aside>

                        <section x-ref="pcMain" class="min-h-0 overflow-y-auto p-4 sm:p-5">
                            {{-- Категории главы --}}
                            <template x-if="!currentCategory">
                                <div>
                                    <h3 class="font-hand text-3xl text-[#2f4a3a]" x-text="catalog.chapters[chapter]?.title"></h3>
                                    <div class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                                        <template x-for="c in chapterCategories" :key="c.slug">
                                            <button type="button" @click="pickCategory(c.slug)"
                                                    class="group rounded-xl border border-amber-900/20 bg-white/60 p-4 text-left shadow-sm transition hover:-translate-y-0.5 hover:border-[#2f4a3a]/50 hover:bg-white hover:shadow-md">
                                                <span class="block text-4xl transition group-hover:scale-110" x-text="c.icon"></span>
                                                <span class="mt-2 block font-semibold" x-text="c.title"></span>
                                                <span class="mt-1 block text-sm text-stone-600" x-text="c.description"></span>
                                                <span class="mt-3 block text-xs font-semibold uppercase tracking-wider text-amber-800"
                                                      x-text="c.products.length + ' ' + plural(c.products.length, ['товар', 'товара', 'товаров'])"></span>
                                            </button>
                                        </template>
                                    </div>
                                </div>
                            </template>

                            {{-- Товары категории --}}
                            <template x-if="currentCategory">
                                <div>
                                    <h3 class="flex items-center gap-2 font-hand text-3xl text-[#2f4a3a]">
                                        <span x-text="currentCategory.icon"></span>
                                        <span x-text="currentCategory.title"></span>
                                    </h3>

                                    <ul x-show="currentCategory.products.length"
                                        class="mt-3 divide-y divide-amber-900/15 overflow-hidden rounded-xl border border-amber-900/20 bg-white/60">
                                        <template x-for="p in currentCategory.products" :key="p.id">
                                            <li class="flex items-center gap-3 px-3 py-2.5 hover:bg-white">
                                                <span class="flex w-10 shrink-0 justify-center">
                                                    <template x-if="$store.cart.isImage(p.icon)">
                                                        <img :src="p.icon" alt="" class="h-10 w-10 object-contain">
                                                    </template>
                                                    <template x-if="!$store.cart.isImage(p.icon)">
                                                        <span class="text-3xl" x-text="p.icon"></span>
                                                    </template>
                                                </span>
                                                <span class="min-w-0 flex-1 leading-snug" x-text="p.name"></span>
                                                <span class="shrink-0 font-semibold text-[#2f4a3a]" x-text="$store.cart.money(p.price)"></span>
                                                <button type="button" class="pc-btn shrink-0" @click="$store.cart.add(p, $el)">
                                                    <span class="hidden sm:inline">В корзину</span><span class="sm:hidden">＋</span>
                                                </button>
                                            </li>
                                        </template>
                                    </ul>

                                    <p x-show="!currentCategory.products.length" class="mt-4 font-hand text-xl text-stone-500">
                                        Пока пусто. Михалыч обещал завезти.
                                    </p>

                                    <a :href="currentCategory.url" class="mt-4 inline-block font-semibold text-amber-800 hover:underline">
                                        Открыть раздел полностью →
                                    </a>
                                </div>
                            </template>
                        </section>
                    </div>

                    {{-- Статус-бар --}}
                    <footer class="flex items-center gap-4 bg-[#2f4a3a] px-4 py-1.5 text-xs text-amber-100">
                        <span class="shrink-0">
                            🛒 <span x-text="$store.cart.count"></span> шт. ·
                            <span x-text="$store.cart.money($store.cart.total)"></span>
                        </span>
                        <div class="min-w-0 flex-1 overflow-hidden" x-show="promoTitles.length" aria-hidden="true">
                            <div class="pc-ticker__track">
                                <template x-for="t in promoTitles" :key="t">
                                    <span>✦ <span x-text="t"></span></span>
                                </template>
                            </div>
                        </div>
                    </footer>
                </div>

                <div class="pc-glare" aria-hidden="true"></div>
            </div>

            <div class="mt-1.5 flex items-center justify-between px-2 text-[10px] tracking-[.3em] text-stone-400">
                <span>SILVER·UDOCHKI</span>
                <span class="flex items-center gap-2">PWR
                    <span class="h-2 w-2 rounded-full bg-emerald-400 shadow-[0_0_8px_2px_rgba(52,211,153,.8)]"></span>
                </span>
            </div>
        </div>
        <div class="pc-neck hidden sm:block" aria-hidden="true"></div>
        <div class="pc-foot hidden sm:block" aria-hidden="true"></div>
    </div>
</div>
