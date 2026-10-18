{{-- Окно «Мои заказы»: открывается событием orders-open --}}
<div x-data="ordersWindow(@js(route('orders.index')))"
     @orders-open.window="show($event.detail?.highlight ?? null)"
     @keydown.escape.window="open = false"
     x-show="open" x-cloak
     x-transition.opacity
     class="fixed inset-0 z-50 flex items-center justify-center p-4"
     role="dialog" aria-modal="true" aria-labelledby="orders-title">
    <div class="absolute inset-0 bg-black/50" @click="open = false"></div>

    <div class="relative flex max-h-[85vh] w-full max-w-lg flex-col rounded-lg border-4 border-amber-900 bg-[#f3e7c9] text-stone-800 shadow-2xl">
        <div class="flex items-center justify-between border-b-2 border-amber-900/20 px-4 py-3">
            <h2 id="orders-title" class="font-bold">Мои заказы</h2>
            <button type="button" @click="open = false" class="text-stone-500 hover:text-stone-900" aria-label="Закрыть">✕</button>
        </div>

        <div class="overflow-y-auto">
            <p x-show="loading && orders.length === 0" class="px-4 py-6 text-sm text-stone-500">Загружаем…</p>

            <div x-show="error" x-cloak class="px-4 py-4 text-sm text-red-800">
                <span x-text="error"></span>
                <button type="button" @click="load()" class="ml-1 underline">повторить</button>
            </div>

            <p x-show="!loading && !error && orders.length === 0" class="px-4 py-6 text-sm text-stone-600">
                Заказов пока нет. Собери корзину, и первый появится здесь.
            </p>

            <ul class="divide-y divide-amber-900/10">
                <template x-for="order in orders" :key="order.id">
                    <li>
                        <button type="button" @click="toggle(order.id)" :aria-expanded="expanded === order.id"
                                class="flex w-full items-center gap-3 px-4 py-3 text-left transition hover:bg-amber-100/60">
                            <span class="min-w-0 flex-1">
                                <span class="block text-sm font-semibold" x-text="'Заказ ' + order.number"></span>
                                <span class="block text-xs text-stone-500" x-text="date(order.created_at)"></span>
                            </span>
                            <span class="rounded px-1.5 py-0.5 text-[11px] font-semibold"
                                  :class="order.status === 'paid' ? 'bg-emerald-100 text-emerald-800' : 'bg-stone-200 text-stone-700'"
                                  x-text="order.status_label"></span>
                            <span class="w-24 shrink-0 text-right text-sm font-bold" x-text="money(order.total)"></span>
                        </button>

                        <div x-show="expanded === order.id" x-collapse>
                            <ul class="space-y-1 px-4 pb-2 text-xs">
                                <template x-for="(item, i) in order.items" :key="i">
                                    <li class="flex items-baseline gap-2">
                                        <span class="min-w-0 flex-1 truncate" x-text="item.name"></span>
                                        <span class="shrink-0 text-stone-500">
                                            <span x-text="item.qty"></span> × <span x-text="money(item.price)"></span>
                                            <s x-show="item.old_price" class="text-stone-400" x-text="money(item.old_price)"></s>
                                        </span>
                                        <span class="w-20 shrink-0 text-right font-medium" x-text="money(item.total)"></span>
                                    </li>
                                </template>
                            </ul>
                            <dl class="space-y-0.5 border-t border-amber-900/10 px-4 py-2 text-xs text-stone-600">
                                <div x-show="order.code_discount > 0" class="flex justify-between text-red-800">
                                    <dt>Промокод <span class="font-mono" x-text="order.promo_code"></span></dt>
                                    <dd x-text="'−' + money(order.code_discount)"></dd>
                                </div>
                                <div class="flex justify-between">
                                    <dt>Доставка</dt>
                                    <dd x-text="order.delivery_price > 0 ? money(order.delivery_price) : 'бесплатно'"></dd>
                                </div>
                                <div class="flex justify-between font-semibold text-stone-800">
                                    <dt>Оплачено</dt>
                                    <dd x-text="money(order.total)"></dd>
                                </div>
                            </dl>
                        </div>
                    </li>
                </template>
            </ul>
        </div>
    </div>
</div>
