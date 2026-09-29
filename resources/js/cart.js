const STORAGE_KEY = 'su_cart_v1';

const isImage = (icon) => /^(\/|https?:)/.test(icon ?? '');

/** Анимация: иконка товара летит по дуге в корзину. */
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

export default function registerCart(Alpine) {
    Alpine.store('cart', {
        items: [], // { id, name, price, icon, qty }
        bump: 0,   // счётчик для анимации «покачивания»

        init() {
            this.items = this.read();
            // Синхронизация между вкладками
            window.addEventListener('storage', (e) => {
                if (e.key === STORAGE_KEY) this.items = this.read();
            });
        },

        read() {
            try {
                const data = JSON.parse(localStorage.getItem(STORAGE_KEY));
                return Array.isArray(data) ? data : [];
            } catch {
                return [];
            }
        },

        save() {
            localStorage.setItem(STORAGE_KEY, JSON.stringify(this.items));
        },

        add(product, sourceEl = null) {
            const found = this.items.find((i) => i.id === product.id);
            if (found) {
                found.qty++;
            } else {
                this.items.push({
                    id: product.id,
                    name: product.name,
                    price: Number(product.price),
                    icon: product.icon,
                    qty: 1,
                });
            }
            this.save();
            flyToBasket(sourceEl, product.icon);
            // Корзина качнётся, когда товар «долетит»
            setTimeout(() => this.bump++, sourceEl ? 650 : 0);
        },

        inc(id) {
            const item = this.items.find((i) => i.id === id);
            if (item) { item.qty++; this.save(); }
        },

        dec(id) {
            const item = this.items.find((i) => i.id === id);
            if (!item) return;
            item.qty--;
            if (item.qty <= 0) this.items = this.items.filter((i) => i.id !== id);
            this.save();
        },

        clear() {
            this.items = [];
            this.save();
        },

        get count() {
            return this.items.reduce((sum, i) => sum + i.qty, 0);
        },

        get total() {
            return this.items.reduce((sum, i) => sum + i.qty * i.price, 0);
        },

        /** Уровень наполнения: от него зависят подсказка и «тяжесть» руки. */
        get level() {
            const c = this.count;
            if (c === 0) return 'empty';
            if (c <= 2) return 'few';
            if (c <= 5) return 'half';
            return 'full';
        },

        /** Иконки, которые видны в корзине (не больше 9). */
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
