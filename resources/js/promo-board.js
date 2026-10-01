const DAY = 86_400_000;

export function plural(n, [one, few, many]) {
    const a = Math.abs(n) % 100;
    const b = a % 10;
    if (a > 10 && a < 20) return many;
    if (b > 1 && b < 5) return few;
    if (b === 1) return one;
    return many;
}

const pad = (x) => String(x).padStart(2, '0');

/** Доска акций: листает предложения, считает время до конца, прячет истёкшие. */
export default function registerPromoBoard(Alpine) {
    Alpine.data('promoBoard', (items = []) => ({
        items,
        i: 0,
        now: Date.now(),
        paused: false,
        ticker: null,
        rotator: null,

        init() {
            this.ticker = setInterval(() => { this.now = Date.now(); }, 1000);
            this.restart();
        },

        destroy() {
            clearInterval(this.ticker);
            clearInterval(this.rotator);
        },

        restart() {
            clearInterval(this.rotator);
            this.rotator = setInterval(() => {
                if (!this.paused && this.active.length > 1) {
                    this.i = (this.i + 1) % this.active.length;
                }
            }, 6000);
        },

        get active() {
            return this.items.filter((p) => !p.ends || p.ends > this.now);
        },

        get pos() {
            return this.active.length ? this.i % this.active.length : 0;
        },

        go(k) {
            this.i = k;
            this.restart();
        },

        left(p) {
            const ms = Math.max(0, p.ends - this.now);
            const d = Math.floor(ms / DAY);
            const h = Math.floor((ms % DAY) / 3_600_000);
            if (d >= 1) return `ещё ${d} ${plural(d, ['день', 'дня', 'дней'])} ${h} ч`;
            const m = Math.floor((ms % 3_600_000) / 60_000);
            const s = Math.floor((ms % 60_000) / 1000);
            return `осталось ${pad(h)}:${pad(m)}:${pad(s)}`;
        },
    }));
}
