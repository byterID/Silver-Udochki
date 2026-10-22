import { send, newRequestKey } from './http';

const STORAGE_KEY = 'su_cart_v1';

const QUOTE_URL = '/cart/quote';
const MAX_QTY = 999; // PriceCalculator::MAX_QTY
const CODE_URL = '/cart/promo-code';
const ORDERS_URL = '/orders';
const LOGIN_URL = '/login';

const isImage = (icon) => /^(\/|https?:)/.test(icon ?? '');

function flyToBasket(sourceEl, icon) {
    const target = document.getElementById('basket-drop');
    if (!sourceEl || !target || window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

    const from = sourceEl.getBoundingClientRect();
    const to = target.getBoundingClientRect();
    const startX = from.left + from.width / 2;
    const startY = from.top + from.height / 2;
    const dx = to.left + to.width / 2 - startX;
    const dy = to.top + to.height * 0.4 - startY;

    let el;
    if (isImage(icon)) {
        el = document.createElement('img');
        el.src = icon;
        el.style.width = '48px';
    } else {
        el = document.createElement('span');
        el.textContent = icon;
        el.style.fontSize = '2.25rem';
    }
    el.setAttribute('aria-hidden', 'true');
    Object.assign(el.style, {
        position: 'fixed', left: `${startX}px`, top: `${startY}px`,
        zIndex: 9999, pointerEvents: 'none', transform: 'translate(-50%, -50%)',
    });
    document.body.appendChild(el);

    el.animate([
        { transform: 'translate(-50%, -50%) scale(1)', opacity: 1 },
        { transform: `translate(calc(-50% + ${dx * 0.5}px), calc(-50% + ${dy * 0.5 - 140}px)) scale(1.3)`, opacity: 1, offset: 0.5 },
        { transform: `translate(calc(-50% + ${dx}px), calc(-50% + ${dy}px)) scale(.6)`, opacity: 0.3 },
    ], { duration: 700, easing: 'cubic-bezier(.45,0,.55,1)' }).onfinish = () => el.remove();
}

// Цены в localStorage — только кеш для мгновенной отрисовки: после каждого изменения
// сервер пересчитывает корзину (POST /cart/quote), и его ответ главнее
export default function registerCart(Alpine) {
    Alpine.store('cart', {
        items: [], // price — уже со скидкой
        bump: 0,

        quote: null,
        version: 0, // ответы на устаревшие версии корзины отбрасываем
        timer: null,
        syncing: false,
        notice: '',

        codeInput: '',
        codeError: '',
        codeBusy: false,

        deliveryFee: 0,
        checkoutKey: null, // один на попытку: повтор после обрыва сети не оформит второй заказ
        checkoutBusy: false,
        checkoutError: '',
        placed: null,

        init() {
            this.items = this.read();
            this.refresh(0);
            window.addEventListener('storage', (e) => {
                if (e.key === STORAGE_KEY) {
                    this.items = this.read();
                    this.refresh();
                }
            });
        },

        read() {
            try {
                const data = JSON.parse(localStorage.getItem(STORAGE_KEY));
                return Array.isArray(data) ? data.filter((i) => Number.isInteger(i?.id) && i.qty > 0) : [];
            } catch {
                return [];
            }
        },

        persist() {
            try {
                localStorage.setItem(STORAGE_KEY, JSON.stringify(this.items));
            } catch {
                // Приватный режим или нет места: корзина проживёт до перезагрузки
            }
        },

        changed() {
            this.version++;
            this.checkoutKey = null;
            this.checkoutError = '';
            this.placed = null;
            this.persist();
            this.refresh();
        },

        qtyOf(id) {
            return this.items.find((i) => i.id === id)?.qty ?? 0;
        },

        // Остаток со страницы — подсказка, окончательно количество урежет сервер
        limitOf(item) {
            return Math.min(MAX_QTY, item.stock ?? MAX_QTY);
        },

        canAdd(product) {
            return this.qtyOf(product.id) < this.limitOf(product);
        },

        add(product, sourceEl = null) {
            if (!this.canAdd(product)) return;

            const found = this.items.find((i) => i.id === product.id);
            if (found) {
                found.qty++;
            } else {
                this.items.push({
                    id: product.id,
                    name: product.name,
                    price: Number(product.price),
                    old_price: product.old_price ?? null,
                    badge: product.badge ?? null,
                    icon: product.icon,
                    stock: product.stock ?? null,
                    qty: 1,
                });
            }
            this.changed();
            flyToBasket(sourceEl, product.icon);
            setTimeout(() => this.bump++, sourceEl ? 650 : 0);
        },

        inc(id) {
            const item = this.items.find((i) => i.id === id);
            if (item && item.qty < this.limitOf(item)) { item.qty++; this.changed(); }
        },

        dec(id) {
            const item = this.items.find((i) => i.id === id);
            if (!item) return;
            item.qty--;
            if (item.qty <= 0) this.items = this.items.filter((i) => i.id !== id);
            this.changed();
        },

        clear() {
            this.items = [];
            this.changed();
        },

        payload() {
            return { items: this.items.map((i) => ({ id: i.id, qty: i.qty })) };
        },

        refresh(delay = 300) {
            clearTimeout(this.timer);
            this.timer = setTimeout(() => this.sync(), delay);
        },

        async sync() {
            if (this.items.length === 0) return;

            const version = this.version;
            this.syncing = true;
            try {
                const { ok, data } = await send('POST', QUOTE_URL, this.payload());
                if (ok && data && version === this.version) this.apply(data);
            } catch {
                // Нет сети: остаёмся на кешированных ценах
            } finally {
                this.syncing = false;
            }
        },

        apply(data) {
            const fresh = new Map(data.items.map((i) => [i.id, i]));
            const missing = new Set(data.missing ?? []);
            const notices = [];

            if (missing.size) {
                this.items = this.items.filter((i) => !missing.has(i.id));
                notices.push('Часть товаров закончилась, мы убрали их из корзины.');
            }
            if (data.limited?.length) {
                notices.push('Столько нет на складе: положили, сколько осталось.');
            }
            if (notices.length) this.notice = notices.join(' ');

            for (const item of this.items) {
                const f = fresh.get(item.id);
                if (!f) continue;
                Object.assign(item, {
                    name: f.name, icon: f.icon, price: f.price, old_price: f.old_price, badge: f.badge,
                    stock: f.stock, qty: f.qty,
                });
            }
            this.persist();

            this.quote = data;
            if (data.delivery_fee != null) this.deliveryFee = data.delivery_fee;
            if (data.code_error) this.codeError = data.code_error;
        },

        async checkout() {
            if (this.checkoutBusy || this.count === 0) return;

            this.checkoutBusy = true;
            this.checkoutError = '';
            this.checkoutKey ??= newRequestKey();
            try {
                // Если сервер насчитает не ту сумму, что видит покупатель, заказ не пройдёт (409)
                const { ok, status, data } = await send('POST', ORDERS_URL, {
                    ...this.payload(), total: this.toPay, key: this.checkoutKey,
                });
                if (ok) {
                    this.items = [];
                    this.changed();
                    this.quote = null;
                    this.placed = data.order;
                    window.dispatchEvent(new CustomEvent('wallet-changed', { detail: { balance: data.balance } }));
                } else if (status === 409 && data?.quote) {
                    this.apply(data.quote);
                    this.checkoutError = data.message;
                } else if (status === 401) {
                    window.location.href = LOGIN_URL;
                } else if (status === 419) {
                    window.location.reload(); // сессия истекла: корзина в localStorage переживёт перезагрузку
                } else if (status === 429) {
                    this.checkoutError = 'Слишком много попыток. Подождите минуту.';
                } else {
                    this.checkoutError = data?.message ?? 'Не удалось оформить заказ. Попробуйте ещё раз.';
                }
            } catch {
                // Ключ тот же: если заказ всё-таки прошёл, повтор вернёт его, а не спишет дважды
                this.checkoutError = 'Нет связи с лавкой. Попробуйте ещё раз, дважды не спишем.';
            } finally {
                this.checkoutBusy = false;
            }
        },

        async applyCode() {
            const code = this.codeInput.trim();
            if (!code || this.codeBusy) return;

            this.codeBusy = true;
            this.codeError = '';
            try {
                const { ok, status, data } = await send('POST', CODE_URL, { ...this.payload(), code });
                if (ok) {
                    this.apply(data);
                    this.codeInput = '';
                } else if (status === 429) {
                    this.codeError = 'Слишком много попыток. Подождите минуту.';
                } else {
                    this.codeError = data?.message ?? data?.errors?.code?.[0] ?? 'Не удалось применить промокод.';
                    if (data?.items) this.apply({ ...data, code_error: null });
                }
            } catch {
                this.codeError = 'Нет связи с лавкой. Попробуйте ещё раз.';
            } finally {
                this.codeBusy = false;
            }
        },

        async removeCode() {
            if (this.quote) this.quote = { ...this.quote, code: null };
            this.codeError = '';
            try {
                await send('DELETE', CODE_URL);
            } finally {
                this.refresh(0);
            }
        },

        // Суммы считаются по той же формуле, что CartQuote на сервере
        get count() {
            return this.items.reduce((sum, i) => sum + i.qty, 0);
        },

        get subtotal() {
            return this.items.reduce((sum, i) => sum + i.qty * (i.old_price ?? i.price), 0);
        },

        get goods() {
            return this.items.reduce((sum, i) => sum + i.qty * i.price, 0);
        },

        get itemsDiscount() {
            return this.subtotal - this.goods;
        },

        get code() {
            return this.quote?.code ?? null;
        },

        get codeDiscount() {
            return this.code ? Math.min(this.code.amount, this.goods) : 0;
        },

        get total() {
            return this.goods - this.codeDiscount;
        },

        // null — акции на доставку сейчас нет
        get delivery() {
            const from = this.quote?.delivery?.from;
            if (from == null) return null;
            const left = Math.max(0, from - this.total);
            return { from, left, free: this.count > 0 && left === 0, progress: from ? Math.min(1, this.total / from) : 1 };
        },

        get deliveryPrice() {
            return this.count === 0 || this.delivery?.free ? 0 : this.deliveryFee;
        },

        get toPay() {
            return this.total + this.deliveryPrice;
        },

        // От уровня зависят подсказка и «тяжесть» руки продавца
        get level() {
            const c = this.count;
            if (c === 0) return 'empty';
            if (c <= 2) return 'few';
            if (c <= 5) return 'half';
            return 'full';
        },

        get visibleIcons() {
            const out = [];
            for (const item of this.items) {
                for (let k = 0; k < item.qty && out.length < 9; k++) out.push(item.icon);
            }
            return out;
        },

        isImage,

        money(value) {
            return new Intl.NumberFormat('ru-RU').format(value) + ' ₽';
        },
    });
}
