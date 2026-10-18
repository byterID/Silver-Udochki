{{-- Окно «Мои заказы»: открывается событием orders-open. Поверх него — форма возврата --}}
<div x-data="ordersWindow(@js(route('orders.index')), @js(App\Enums\ReturnReason::options()))"
     @orders-open.window="show($event.detail?.highlight ?? null)"
     @keydown.escape.window="close()"
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
            <p x-show="notice" x-cloak class="mx-4 mt-3 rounded border border-emerald-700/30 bg-emerald-50 px-3 py-2 text-sm text-emerald-900"
               role="status" x-text="notice"></p>

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
                                  :class="statusClass(order.status)"
                                  x-text="order.status_label"></span>
                            <span class="w-24 shrink-0 text-right text-sm font-bold" x-text="money(order.total)"></span>
                        </button>

                        <div x-show="expanded === order.id" x-collapse>
                            <ul class="space-y-1 px-4 pb-2 text-xs">
                                <template x-for="item in order.items" :key="item.id">
                                    <li class="flex items-baseline gap-2">
                                        <span class="min-w-0 flex-1 truncate">
                                            <span x-text="item.name"></span>
                                            <span x-show="item.returned > 0" class="ml-1 text-sky-800"
                                                  x-text="item.returned >= item.qty ? '· возвращено' : '· возвращено ' + item.returned + ' шт.'"></span>
                                        </span>
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
                                <div x-show="order.refunded > 0" class="flex justify-between font-semibold text-sky-800">
                                    <dt>Возвращено на баланс</dt>
                                    <dd x-text="money(order.refunded)"></dd>
                                </div>
                            </dl>
                            <div x-show="order.returnable" class="flex items-center justify-between gap-3 px-4 pb-3">
                                <span class="text-[11px] text-stone-500" x-text="'Вернуть можно до ' + date(order.return_until, false)"></span>
                                <button type="button" @click="startReturn(order)"
                                        class="rounded border-2 border-amber-900 bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-950 transition hover:bg-amber-200">
                                    Оформить возврат
                                </button>
                            </div>
                        </div>
                    </li>
                </template>
            </ul>
        </div>
    </div>

    {{-- Форма возврата --}}
    <template x-if="returning">
        <div class="fixed inset-0 z-[60] flex items-center justify-center p-4"
             role="dialog" aria-modal="true" aria-labelledby="return-title">
            <div class="absolute inset-0 bg-black/40" @click="cancelReturn()"></div>

            <form @submit.prevent="submitReturn()"
                  class="relative flex max-h-[90vh] w-full max-w-md flex-col rounded-lg border-4 border-amber-900 bg-[#f3e7c9] text-stone-800 shadow-2xl">
                <div class="flex items-center justify-between border-b-2 border-amber-900/20 px-4 py-3">
                    <h2 id="return-title" class="font-bold">
                        Возврат по заказу <span x-text="returning.number"></span>
                        <span class="block text-xs font-normal text-stone-500" x-text="'от ' + date(returning.created_at)"></span>
                    </h2>
                    <button type="button" @click="cancelReturn()" class="text-stone-500 hover:text-stone-900" aria-label="Закрыть">✕</button>
                </div>

                <div class="space-y-4 overflow-y-auto px-4 py-3 text-sm">
                    <fieldset>
                        <legend class="mb-1 text-xs font-semibold uppercase tracking-wide text-stone-600">Что возвращаете</legend>
                        <ul class="divide-y divide-amber-900/10 rounded border border-amber-900/20 bg-white/40">
                            <template x-for="line in form.lines" :key="line.id">
                                <li class="flex items-center gap-2 px-2 py-2">
                                    <input type="checkbox" x-model="line.checked" :id="'return-line-' + line.id"
                                           class="rounded border-amber-900/40 text-amber-800 focus:ring-amber-700">
                                    <label :for="'return-line-' + line.id" class="min-w-0 flex-1">
                                        <span class="block truncate" x-text="line.name"></span>
                                        <span class="block text-xs text-stone-500" x-text="money(line.price) + ' за шт.'"></span>
                                    </label>
                                    <input type="number" min="1" :max="line.left" x-model.number="line.qty" @change="clampQty(line)"
                                           :disabled="!line.checked" :aria-label="'Сколько штук вернуть: ' + line.name"
                                           class="w-16 rounded border-amber-900/30 px-2 py-1 text-right text-sm disabled:opacity-40">
                                    <span class="w-12 shrink-0 text-xs text-stone-500" x-text="'из ' + line.left"></span>
                                </li>
                            </template>
                        </ul>
                    </fieldset>

                    <div>
                        <label for="return-reason" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-stone-600">Причина возврата</label>
                        <select id="return-reason" x-model="form.reason" required
                                class="w-full rounded border-amber-900/30 bg-white/70 text-sm focus:border-amber-700 focus:ring-amber-700">
                            <option value="" disabled>Выберите причину</option>
                            <template x-for="reason in reasons" :key="reason.value">
                                <option :value="reason.value" x-text="reason.label"></option>
                            </template>
                        </select>
                    </div>

                    <div>
                        <label for="return-comment" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-stone-600">
                            Комментарий <span x-show="!needsComment" class="font-normal normal-case text-stone-400">(необязательно)</span>
                        </label>
                        <textarea id="return-comment" x-model="form.comment" rows="3" maxlength="1000" :required="needsComment"
                                  placeholder="Что случилось с товаром"
                                  class="w-full rounded border-amber-900/30 bg-white/70 text-sm focus:border-amber-700 focus:ring-amber-700"></textarea>
                    </div>

                    <div class="rounded border border-amber-900/20 bg-white/40 px-3 py-2">
                        <div class="flex items-baseline justify-between font-semibold">
                            <span>Вернётся на баланс</span>
                            <span class="text-emerald-800" x-text="money(refundPreview.amount)"></span>
                        </div>
                        <p class="mt-1 text-xs text-stone-500"
                           x-text="refundPreview.full && returning.delivery_price > 0
                               ? 'Возвращаете весь заказ, поэтому доставка тоже возвращается.'
                               : 'Скидка по промокоду делится между товарами. Доставка возвращается, только если вернуть весь заказ.'"></p>
                        <p class="mt-1 text-xs text-stone-500">Деньги придут на кошелёк сразу, товар вернётся на склад.</p>
                    </div>

                    <p x-show="returnError" class="text-sm text-red-800" role="alert" x-text="returnError"></p>
                </div>

                <div class="flex justify-end gap-2 border-t-2 border-amber-900/20 px-4 py-3">
                    <button type="button" @click="cancelReturn()" :disabled="returnBusy"
                            class="rounded px-3 py-1.5 text-sm text-stone-700 hover:bg-amber-100 disabled:opacity-50">Отмена</button>
                    <button type="submit" :disabled="!canSubmitReturn"
                            class="rounded border-2 border-amber-900 bg-amber-800 px-3 py-1.5 text-sm font-semibold text-amber-50 transition hover:bg-amber-900 disabled:cursor-not-allowed disabled:opacity-50"
                            x-text="returnBusy ? 'Оформляем…' : 'Оформить возврат'"></button>
                </div>
            </form>
        </div>
    </template>
</div>
