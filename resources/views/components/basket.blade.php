{{-- Корзина в руке: правый нижний угол --}}
{{-- Картинка: public/images/basket-hand.png (прозрачный PNG, рука уходит за правый край экрана) --}}
<div
    x-data="{
        open: false,
        wobble: false,
        // [left %, top %, поворот°]: сначала нижний ряд, потом горка
        slots: [
            [30, 69, -14], [50, 71, 6], [70, 69, 16],
            [37, 61, 10], [63, 62, -8],
            [25, 55, 18], [50, 56, -4], [75, 55, -18],
            [50, 47, 8],
        ],
        slotStyle(i) {
            const [x, y, r] = this.slots[i];
            return `left:${x}%; top:${y}%; --r:${r}deg`;
        },
    }"
    x-init="$watch('$store.cart.bump', () => { wobble = false; requestAnimationFrame(() => wobble = true) })"
    @keydown.escape.window="open = false"
    class="pointer-events-none fixed bottom-0 right-0 z-40 select-none"
>
    <div class="origin-bottom-right scale-[.6] sm:scale-75 lg:scale-100">
        {{-- Чем полнее корзина, тем ниже опускается рука --}}
        <div class="relative h-[191px] w-[300px] transition-transform duration-500"
             :style="`transform: translateY(${ ({ empty: 0, few: 4, half: 9, full: 15 })[$store.cart.level] }px)`">

            <div class="relative h-full w-full"
                 :class="wobble && 'basket-wobble'"
                 @animationend.self="wobble = false">

                {{-- Товары внутри: лежат ПОД проволокой и просвечивают сквозь сетку --}}
                <div class="absolute inset-0 z-10">
                    <template x-for="(icon, i) in $store.cart.visibleIcons" :key="i">
                        <span class="basket-item absolute text-3xl leading-none" :style="slotStyle(i)">
                            <template x-if="$store.cart.isImage(icon)">
                                <img :src="icon" alt="" class="h-9 w-9 object-contain">
                            </template>
                            <template x-if="!$store.cart.isImage(icon)">
                                <span x-text="icon"></span>
                            </template>
                        </span>
                    </template>
                </div>

                {{-- Проволочная корзина в руке --}}
                <img src="{{ asset('images/basket-hand.png') }}"
                     alt=""
                     aria-hidden="true"
                     draggable="false"
                     class="pointer-events-none absolute inset-0 z-20 h-full w-full object-contain object-bottom">

                {{-- Кликабельная зона по корзине --}}
                <button type="button"
                        id="basket-drop"
                        @click="open = !open"
                        :title="({ empty: 'Корзина пуста', few: 'Кое-что уже есть', half: 'Набирается!', full: 'Полная корзина!' })[$store.cart.level]"
                        :aria-label="'Корзина: ' + $store.cart.count + ' шт.'"
                        class="pointer-events-auto absolute z-30 rounded-xl focus:outline-none focus-visible:ring-4 focus-visible:ring-amber-400"
                        style="left:19%; top:45%; width:61.5%; height:48.5%"></button>

                {{-- Не поместилось --}}
                <span x-show="$store.cart.count > 9" x-cloak
                      class="absolute left-1/2 z-30 -translate-x-1/2 rounded bg-black/45 px-1.5 text-xs font-bold text-amber-50"
                      style="bottom:12%"
                      x-text="'+' + ($store.cart.count - 9)"></span>

                {{-- Счётчик --}}
                <span x-show="$store.cart.count > 0" x-cloak x-transition.scale
                      class="absolute z-30 flex h-7 min-w-[1.75rem] items-center justify-center rounded-full bg-red-700 px-1.5 text-sm font-bold text-white shadow ring-2 ring-amber-100"
                      style="left:17%; top:42%"
                      x-text="$store.cart.count > 99 ? '99+' : $store.cart.count"></span>
            </div>
        </div>
    </div>

    {{-- Содержимое корзины --}}
    <div x-show="open" x-cloak x-transition.origin.bottom.right
         @click.outside="if (!$event.target.closest('#basket-drop')) open = false"
         class="pointer-events-auto fixed bottom-36 right-3 w-[min(22rem,calc(100vw-1.5rem))] rounded-lg border-4 border-amber-900 bg-[#f3e7c9] text-stone-800 shadow-2xl sm:bottom-44 lg:bottom-60">
        <div class="flex items-center justify-between border-b-2 border-amber-900/20 px-4 py-3">
            <h2 class="font-bold">Корзина</h2>
            <button type="button" @click="open = false" class="text-stone-500 hover:text-stone-900" aria-label="Закрыть">✕</button>
        </div>

        <p x-show="$store.cart.count === 0" class="px-4 py-6 text-sm text-stone-600">
            Пока пусто. Загляни в каталог, там найдётся всё для охоты и рыбалки.
        </p>

        <ul x-show="$store.cart.count > 0" class="max-h-72 divide-y divide-amber-900/10 overflow-y-auto">
            <template x-for="item in $store.cart.items" :key="item.id">
                <li class="flex items-center gap-3 px-4 py-2">
                    <span class="w-8 text-center text-2xl">
                        <template x-if="$store.cart.isImage(item.icon)"><img :src="item.icon" alt="" class="mx-auto h-7 w-7 object-contain"></template>
                        <template x-if="!$store.cart.isImage(item.icon)"><span x-text="item.icon"></span></template>
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-medium" x-text="item.name"></p>
                        <p class="flex flex-wrap items-baseline gap-x-1.5 text-xs">
                            <span :class="item.old_price ? 'font-semibold text-red-800' : 'text-stone-500'" x-text="$store.cart.money(item.price)"></span>
                            <s x-show="item.old_price" class="text-stone-400" x-text="$store.cart.money(item.old_price)"></s>
                            <span x-show="item.badge" class="rounded bg-red-800 px-1 text-[10px] font-bold text-amber-50" x-text="item.badge"></span>
                        </p>
                    </div>
                    <div class="flex items-center gap-1">
                        <button type="button" @click="$store.cart.dec(item.id)" class="h-7 w-7 rounded bg-amber-200 hover:bg-amber-300" aria-label="Меньше">−</button>
                        <span class="w-6 text-center text-sm font-semibold" x-text="item.qty"></span>
                        <button type="button" @click="$store.cart.inc(item.id)" class="h-7 w-7 rounded bg-amber-200 hover:bg-amber-300" aria-label="Больше">+</button>
                    </div>
                </li>
            </template>
        </ul>

        <p x-show="$store.cart.notice" x-cloak class="border-t border-amber-900/10 bg-amber-100 px-4 py-2 text-xs text-amber-900">
            <span x-text="$store.cart.notice"></span>
            <button type="button" @click="$store.cart.notice = ''" class="ml-1 underline">понятно</button>
        </p>

        <div x-show="$store.cart.count > 0" class="space-y-2 border-t-2 border-amber-900/20 px-4 py-3 text-sm">
            {{-- Скидки --}}
            <template x-if="$store.cart.itemsDiscount > 0 || $store.cart.codeDiscount > 0">
                <div class="space-y-1">
                    <div class="flex justify-between text-stone-600">
                        <span>Товары</span>
                        <span x-text="$store.cart.money($store.cart.subtotal)"></span>
                    </div>
                    <div x-show="$store.cart.itemsDiscount > 0" class="flex justify-between text-red-800">
                        <span>Скидки по акциям</span>
                        <span x-text="'−' + $store.cart.money($store.cart.itemsDiscount)"></span>
                    </div>
                    <div x-show="$store.cart.code" class="flex items-center justify-between gap-2 text-red-800">
                        <span class="min-w-0 truncate">
                            Промокод <span class="font-mono font-semibold" x-text="$store.cart.code?.code"></span>
                            <button type="button" @click="$store.cart.removeCode()" class="ml-0.5 text-stone-400 hover:text-red-800" aria-label="Убрать промокод">✕</button>
                        </span>
                        <span class="shrink-0" x-text="'−' + $store.cart.money($store.cart.codeDiscount)"></span>
                    </div>
                </div>
            </template>

            {{-- Промокод --}}
            <form x-show="!$store.cart.code" @submit.prevent="$store.cart.applyCode()" class="flex gap-1.5">
                <label for="cart-code" class="sr-only">Промокод</label>
                <input id="cart-code" x-model="$store.cart.codeInput" maxlength="32" autocomplete="off" placeholder="Промокод"
                       @input="$store.cart.codeError = ''"
                       class="min-w-0 flex-1 rounded-md border-amber-900/30 bg-white/70 py-1 font-mono text-sm uppercase placeholder:font-sans placeholder:normal-case focus:border-emerald-800 focus:ring-emerald-800">
                <button type="submit" :disabled="$store.cart.codeBusy || !$store.cart.codeInput.trim()"
                        class="rounded-md bg-amber-800 px-3 text-sm font-semibold text-amber-50 hover:bg-amber-700 disabled:opacity-50">
                    Применить
                </button>
            </form>
            <p x-show="$store.cart.codeError" x-cloak class="text-xs text-red-800" x-text="$store.cart.codeError"></p>

            {{-- Доставка --}}
            <template x-if="$store.cart.delivery">
                <div class="text-xs">
                    <p x-show="$store.cart.delivery.free" class="font-semibold text-emerald-800">🚚 Доставка бесплатно</p>
                    <div x-show="!$store.cart.delivery.free">
                        <p class="text-stone-600">
                            🚚 До бесплатной доставки: <span class="font-semibold" x-text="$store.cart.money($store.cart.delivery.left)"></span>
                        </p>
                        <div class="mt-1 h-1.5 overflow-hidden rounded-full bg-amber-900/15">
                            <div class="h-full rounded-full bg-emerald-700 transition-[width] duration-500"
                                 :style="`width: ${Math.round($store.cart.delivery.progress * 100)}%`"></div>
                        </div>
                    </div>
                </div>
            </template>

            <div class="flex justify-between text-base font-bold">
                <span>Итого</span>
                <span x-text="$store.cart.money($store.cart.total)"></span>
            </div>
            <button type="button" disabled class="w-full cursor-not-allowed rounded-md bg-emerald-800 py-2 font-semibold text-amber-50 opacity-60">
                Оформление скоро
            </button>
            <button type="button" @click="$store.cart.clear()" class="w-full text-xs text-stone-500 hover:text-red-800">
                Вытряхнуть корзину
            </button>
        </div>
    </div>
</div>
