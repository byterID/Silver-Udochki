/**
 * Окно «Мои заказы». Открывается событием orders-open (из меню под ником
 * и из корзины после оформления), при каждом открытии заказы грузятся заново.
 */
export function ordersWindow(url) {
    return {
        open: false,
        loading: false,
        error: '',
        orders: [],
        expanded: null, // id раскрытого заказа

        show(highlight = null) {
            this.open = true;
            this.expanded = highlight;
            this.load();
        },

        async load() {
            this.loading = true;
            this.error = '';
            try {
                const res = await fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
                if (res.status === 401) {
                    window.location.reload(); // сессия истекла: пусть сервер отправит на вход
                    return;
                }
                if (!res.ok) throw new Error(`HTTP ${res.status}`);

                this.orders = (await res.json()).data ?? [];
                this.expanded ??= this.orders[0]?.id ?? null;
            } catch {
                this.error = 'Не удалось загрузить заказы. Попробуйте ещё раз.';
            } finally {
                this.loading = false;
            }
        },

        toggle(id) {
            this.expanded = this.expanded === id ? null : id;
        },

        money(value) {
            return new Intl.NumberFormat('ru-RU').format(value) + ' ₽';
        },

        date(iso) {
            const date = new Date(iso);
            if (Number.isNaN(date.getTime())) return '';
            return date.toLocaleString('ru-RU', {
                day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit',
            });
        },
    };
}
