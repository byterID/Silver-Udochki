import { promoStage, STAGE_LABELS, timeLeft } from './promo-board';

// Мел, который читается на тёмно-зелёной доске
const PRESETS = ['#fde68a', '#f8fafc', '#fca5a5', '#f9a8d4', '#c4b5fd', '#93c5fd', '#86efac', '#fdba74'];

const CODE_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // без 0/O и 1/I

/** Форма акции в панели управления: поиск и выбор товаров, цвет, превью доски. */
export default function registerPromotionForm(Alpine) {
    Alpine.data('promotionForm', (cfg) => ({
        type: cfg.type,
        title: cfg.title ?? '',
        note: cfg.note ?? '',
        color: cfg.color,
        endsAt: cfg.endsAt ?? '',
        code: cfg.code ?? '',
        presets: PRESETS,

        selected: cfg.selected ?? [],
        query: '',
        results: [],
        more: false,
        loading: false,
        searched: false,
        error: '',
        timer: null,
        controller: null,

        now: Date.now(),
        ticker: null,

        init() {
            this.ticker = setInterval(() => { this.now = Date.now(); }, 1000);
        },

        destroy() {
            clearInterval(this.ticker);
            this.controller?.abort();
        },

        /* ---------- Товары ---------- */

        isSelected(id) {
            return this.selected.some((p) => p.id === id);
        },

        toggle(p) {
            if (p.taken) return;
            if (this.isSelected(p.id)) this.remove(p.id);
            else this.selected.push(p);
        },

        remove(id) {
            this.selected = this.selected.filter((p) => p.id !== id);
        },

        clearAll() {
            if (confirm(`Снять выбор со всех товаров (${this.selected.length})?`)) this.selected = [];
        },

        selectAllFound() {
            for (const p of this.results) {
                if (!p.taken && !this.isSelected(p.id)) this.selected.push(p);
            }
        },

        search(now = false) {
            clearTimeout(this.timer);
            this.timer = setTimeout(() => this.fetchResults(), now ? 0 : 300);
        },

        async fetchResults() {
            const q = this.query.trim();
            this.controller?.abort();
            this.error = '';

            if (q.length < 2) {
                this.results = [];
                this.more = false;
                this.searched = false;
                return;
            }

            this.controller = new AbortController();
            this.loading = true;
            try {
                const url = new URL(cfg.searchUrl, window.location.origin);
                url.searchParams.set('q', q);
                if (cfg.except) url.searchParams.set('except', cfg.except);

                const res = await fetch(url, {
                    headers: { Accept: 'application/json' },
                    signal: this.controller.signal,
                });
                if (!res.ok) throw new Error(String(res.status));

                const data = await res.json();
                this.results = data.items;
                this.more = data.more;
                this.searched = true;
            } catch (e) {
                if (e.name !== 'AbortError') this.error = 'Не удалось выполнить поиск. Попробуйте ещё раз.';
            } finally {
                this.loading = false;
            }
        },

        money(value) {
            return new Intl.NumberFormat('ru-RU').format(value) + ' ₽';
        },

        /* ---------- Промокод ---------- */

        generateCode() {
            const bytes = crypto.getRandomValues(new Uint8Array(8));
            this.code = Array.from(bytes, (b) => CODE_ALPHABET[b % CODE_ALPHABET.length]).join('');
        },

        /* ---------- Превью доски ---------- */

        get endsMs() {
            return this.endsAt ? new Date(this.endsAt).getTime() : null;
        },

        get stage() {
            return promoStage(this.endsMs, this.now);
        },

        get stageLabel() {
            return STAGE_LABELS[this.stage] ?? '';
        },

        get timerText() {
            if (!this.endsMs) return '';
            return this.stage === 'over' ? 'уже закончилась' : timeLeft(this.endsMs, this.now);
        },
    }));
}
