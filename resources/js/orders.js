import { send, newRequestKey } from './http';

// Открывается событием orders-open (меню под ником, корзина после оформления)
export function ordersWindow(url, reasons) {
    return {
        open: false,
        loading: false,
        error: '',
        notice: '',
        orders: [],
        expanded: null, // id раскрытого заказа

        reasons, // [{ value, label }] из ReturnReason
        returning: null, // заказ, по которому открыта форма возврата
        form: { lines: [], reason: '', comment: '', key: null },
        returnBusy: false,
        returnError: '',

        show(highlight = null) {
            this.open = true;
            this.notice = '';
            this.returning = null;
            this.expanded = highlight;
            this.load();
        },

        // Escape и клик мимо закрывают сначала форму возврата, потом окно
        close() {
            if (this.returning) {
                this.cancelReturn();
            } else {
                this.open = false;
            }
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

        startReturn(order) {
            this.returning = order;
            this.returnError = '';
            this.notice = '';
            this.form = {
                lines: order.items
                    .filter((item) => item.qty > item.returned)
                    .map((item) => ({
                        id: item.id, name: item.name, price: item.price,
                        left: item.qty - item.returned, qty: item.qty - item.returned, checked: true,
                    })),
                reason: '',
                comment: '',
                key: newRequestKey(), // тот же ключ при повторе: второй возврат не оформится
            };
        },

        cancelReturn() {
            if (!this.returnBusy) this.returning = null;
        },

        clampQty(line) {
            const qty = Math.trunc(Number(line.qty));
            line.qty = Number.isFinite(qty) ? Math.min(Math.max(qty, 1), line.left) : line.left;
        },

        get chosen() {
            return this.form.lines.filter((line) => line.checked && line.qty > 0);
        },

        get needsComment() {
            return this.form.reason === 'other';
        },

        get canSubmitReturn() {
            return !this.returnBusy
                && this.chosen.length > 0
                && this.form.reason !== ''
                && (!this.needsComment || this.form.comment.trim() !== '');
        },

        // Та же формула, что в OrderRefunder::amountFor()
        get refundPreview() {
            const order = this.returning;
            if (!order) return { amount: 0, full: false };

            const picked = new Map(this.chosen.map((line) => [line.id, line.qty]));
            let linesValue = 0;
            let returnedValue = 0;
            let full = true;
            for (const item of order.items) {
                const returned = item.returned + (picked.get(item.id) ?? 0);
                linesValue += item.price * item.qty;
                returnedValue += item.price * returned;
                full &&= returned >= item.qty;
            }

            const goodsPaid = Math.max(0, order.total - order.delivery_price);
            let goods = 0;
            if (full) {
                goods = goodsPaid;
            } else if (linesValue > 0) {
                goods = Number((BigInt(goodsPaid) * BigInt(returnedValue)) / BigInt(linesValue));
            }

            return { amount: Math.max(0, goods - order.refunded + (full ? order.delivery_price : 0)), full };
        },

        async submitReturn() {
            if (!this.canSubmitReturn) return;

            const order = this.returning;
            this.returnBusy = true;
            this.returnError = '';
            try {
                const { ok, status, data } = await send('POST', `${url}/${order.id}/returns`, {
                    items: this.chosen.map((line) => ({ id: line.id, qty: line.qty })),
                    reason: this.form.reason,
                    comment: this.form.comment.trim() || null,
                    key: this.form.key,
                });
                if (ok) {
                    this.orders = this.orders.map((o) => (o.id === data.order.id ? data.order : o));
                    this.returning = null;
                    this.expanded = data.order.id;
                    this.notice = data.refund > 0
                        ? `Возврат по заказу ${data.order.number} оформлен: ${this.money(data.refund)} вернулись на баланс.`
                        : `Возврат по заказу ${data.order.number} оформлен.`;
                    window.dispatchEvent(new CustomEvent('wallet-changed', { detail: { balance: data.balance } }));
                } else if (status === 401 || status === 419) {
                    window.location.reload(); // сессия истекла
                } else if (status === 429) {
                    this.returnError = 'Слишком много попыток. Подождите минуту.';
                } else {
                    const errors = Object.values(data?.errors ?? {}).flat();
                    this.returnError = errors[0] ?? data?.message ?? 'Не удалось оформить возврат. Попробуйте ещё раз.';
                }
            } catch {
                this.returnError = 'Нет связи с лавкой. Попробуйте ещё раз, дважды не вернём.';
            } finally {
                this.returnBusy = false;
            }
        },

        statusClass(status) {
            return {
                paid: 'bg-emerald-100 text-emerald-800',
                partially_refunded: 'bg-amber-100 text-amber-800',
                refunded: 'bg-sky-100 text-sky-800',
            }[status] ?? 'bg-stone-200 text-stone-700';
        },

        money(value) {
            return new Intl.NumberFormat('ru-RU').format(value) + ' ₽';
        },

        date(iso, withTime = true) {
            const date = new Date(iso);
            if (Number.isNaN(date.getTime())) return '';
            return date.toLocaleString('ru-RU', {
                day: '2-digit', month: '2-digit', year: 'numeric',
                ...(withTime ? { hour: '2-digit', minute: '2-digit' } : {}),
            });
        },
    };
}
