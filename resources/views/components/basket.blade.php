{{-- Корзина в руке: правый нижний угол --}}
<div
    x-data="{
        open: false,
        wobble: false,
        // [left %, bottom px, поворот°]: сначала нижний ряд, потом горка
        slots: [
            [28, 44, -14], [50, 46, 6], [72, 44, 16],
            [38, 56, 10], [62, 57, -8],
            [24, 64, 20], [50, 68, -4], [76, 64, -18],
            [50, 80, 8],
        ],
        slotStyle(i) {
            const [x, y, r] = this.slots[i];
            return `left:${x}%; bottom:${y}px; --r:${r}deg`;
        },
    }"
    x-init="$watch('$store.cart.bump', () => { wobble = false; requestAnimationFrame(() => wobble = true) })"
    @keydown.escape.window="open = false"
    class="pointer-events-none fixed bottom-0 right-0 z-40 select-none"
>
    <div class="origin-bottom-right scale-[.6] sm:scale-75 lg:scale-100">
        {{-- Чем полнее корзина, тем ниже опускается рука --}}
        <div class="relative h-[230px] w-[240px] transition-transform duration-500"
             :style="`transform: translateY(${ ({ empty: 0, few: 4, half: 9, full: 15 })[$store.cart.level] }px)`">

            <button type="button"
                    id="basket-drop"
                    @click="open = !open"
                    @animationend.self="wobble = false"
                    :class="wobble && 'basket-wobble'"
                    :title="({ empty: 'Корзина пуста', few: 'Кое-что уже есть', half: 'Набирается!', full: 'Полная корзина!' })[$store.cart.level]"
                    :aria-label="'Корзина: ' + $store.cart.count + ' шт.'"
                    class="pointer-events-auto absolute bottom-2.5 right-5 h-[120px] w-40 rounded-xl focus:outline-none focus-visible:ring-4 focus-visible:ring-amber-400">

                {{-- Задняя стенка и ручка --}}
                <svg viewBox="0 0 160 120" class="absolute inset-0 z-0 h-full w-full" aria-hidden="true">
                    <path d="M22 54 C22 -6 138 -6 138 54" fill="none" stroke="#6b3a17" stroke-width="8" stroke-linecap="round"/>
                    <path d="M22 54 C22 -6 138 -6 138 54" fill="none" stroke="#a0632e" stroke-width="3" stroke-linecap="round" stroke-dasharray="6 5"/>
                    <ellipse cx="80" cy="54" rx="68" ry="12" fill="#3b2412"/>
                </svg>

                {{-- Товары внутри --}}
                <template x-for="(icon, i) in $store.cart.visibleIcons" :key="i">
                    <span class="basket-item absolute z-10 text-3xl leading-none" :style="slotStyle(i)">
                        <template x-if="$store.cart.isImage(icon)">
                            <img :src="icon" alt="" class="h-9 w-9 object-contain">
                        </template>
                        <template x-if="!$store.cart.isImage(icon)">
                            <span x-text="icon"></span>
                        </template>
                    </span>
                </template>

                {{-- Передняя плетёная стенка --}}
                <svg viewBox="0 0 160 120" class="absolute inset-0 z-20 h-full w-full" aria-hidden="true">
                    <defs>
                        <pattern id="basket-wicker" width="12" height="10" patternUnits="userSpaceOnUse">
                            <rect width="12" height="10" fill="#b7793f"/>
                            <path d="M0 5 Q3 1 6 5 T12 5" stroke="#8a5528" stroke-width="2" fill="none"/>
                            <path d="M6 0 v10" stroke="#8a5528" stroke-width="1.2" opacity=".6"/>
                        </pattern>
                    </defs>
                    <path d="M12 56 Q80 70 148 56 L134 110 Q80 122 26 110 Z" fill="url(#basket-wicker)" stroke="#6b3a17" stroke-width="3" stroke-linejoin="round"/>
                    <path d="M10 55 Q80 72 150 55" fill="none" stroke="#8a5528" stroke-width="7" stroke-linecap="round"/>
                    <path d="M30 108 Q80 118 130 108" fill="none" stroke="#6b3a17" stroke-width="4"/>
                </svg>

                {{-- Не поместилось --}}
                <span x-show="$store.cart.count > 9" x-cloak
                      class="absolute bottom-5 left-1/2 z-30 -translate-x-1/2 rounded bg-black/45 px-1.5 text-xs font-bold text-amber-50"
                      x-text="'+' + ($store.cart.count - 9)"></span>

                {{-- Счётчик --}}
                <span x-show="$store.cart.count > 0" x-cloak x-transition.scale
                      class="absolute -left-1 -top-1 z-30 flex h-7 min-w-[1.75rem] items-center justify-center rounded-full bg-red-700 px-1.5 text-sm font-bold text-white shadow ring-2 ring-amber-100"
                      x-text="$store.cart.count > 99 ? '99+' : $store.cart.count"></span>
            </button>

            {{-- Рука --}}
            <svg viewBox="0 0 240 230" class="pointer-events-none absolute inset-0 z-30 h-full w-full" aria-hidden="true">
                <path d="M275 255 L166 122" stroke="#4d5b2a" stroke-width="40" stroke-linecap="round"/>
                <path d="M178 138 L160 116" stroke="#3f4a22" stroke-width="42"/>
                <rect x="124" y="92" width="36" height="28" rx="12" fill="#f1c79a" stroke="#c98f5f" stroke-width="2"/>
                <path d="M131 100 h22 M131 107 h22 M131 114 h20" stroke="#c98f5f" stroke-width="2" stroke-linecap="round"/>
                <ellipse cx="128" cy="104" rx="7" ry="9" fill="#eab98a" stroke="#c98f5f" stroke-width="2"/>
            </svg>
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
                        <p class="text-xs text-stone-500" x-text="$store.cart.money(item.price)"></p>
                    </div>
                    <div class="flex items-center gap-1">
                        <button type="button" @click="$store.cart.dec(item.id)" class="h-7 w-7 rounded bg-amber-200 hover:bg-amber-300" aria-label="Меньше">−</button>
                        <span class="w-6 text-center text-sm font-semibold" x-text="item.qty"></span>
                        <button type="button" @click="$store.cart.inc(item.id)" class="h-7 w-7 rounded bg-amber-200 hover:bg-amber-300" aria-label="Больше">+</button>
                    </div>
                </li>
            </template>
        </ul>

        <div x-show="$store.cart.count > 0" class="space-y-2 border-t-2 border-amber-900/20 px-4 py-3">
            <div class="flex justify-between font-bold">
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
