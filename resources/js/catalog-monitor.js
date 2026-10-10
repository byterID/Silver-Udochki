import { plural } from './promo-board';

const reducedMotion = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;

// Полное время закрытия: шторка 120 мс + исчезновение монитора 160 мс (см. app.css, .catalog-monitor)
const CLOSE_MS = 300;

export default function registerCatalogMonitor(Alpine) {
    // Открыт ли монитор: лавке нужно, чтобы не ловить горячие клавиши под ним
    Alpine.store('catalogMonitor', { open: false });

    Alpine.data('catalogMonitor', (cfg) => ({
        catalog: cfg.catalog ?? { chapters: {}, categories: {} },
        promoTitles: cfg.promos ?? [],
        open: false,  // монитор в DOM (display)
        lit: false,   // монитор раскрыт и экран горит; анимации — CSS-переходы в app.css
        closing: false,
        closeTimer: null,
        opener: null, // кнопка, которой открыли: вернём на неё фокус
        chapter: cfg.firstChapter ?? null,
        category: null,
        clock: '',
        clockTimer: null,

        plural,

        get chapterCategories() {
            return (this.catalog.chapters[this.chapter]?.categories ?? [])
                .map((slug) => ({ slug, ...this.catalog.categories[slug] }));
        },

        get currentCategory() {
            return this.category ? this.catalog.categories[this.category] ?? null : null;
        },

        show({ chapter = null, from = null } = {}) {
            if (this.open && !this.closing) return;

            // Открыли заново, пока закрывался: просто снова зажигаем
            clearTimeout(this.closeTimer);
            this.closing = false;

            if (chapter && this.catalog.chapters[chapter]) this.chapter = chapter;
            this.category = null;
            this.opener = from instanceof HTMLElement ? from : document.activeElement;

            this.open = true;
            this.$store.catalogMonitor.open = true;
            document.body.classList.add('overflow-hidden');

            this.tickClock();
            clearInterval(this.clockTimer);
            this.clockTimer = setInterval(() => this.tickClock(), 15000);

            // Первый кадр браузер рисует в тёмном состоянии из CSS,
            // и только потом включаем переход — поэтому вспышки нет
            this.$nextTick(() => {
                requestAnimationFrame(() => requestAnimationFrame(() => {
                    this.lit = true;
                    this.$refs.pcScreen?.focus({ preventScroll: true });
                }));
            });
        },

        close() {
            if (!this.open || this.closing) return;

            this.closing = true;
            this.lit = false; // шторка закрывает экран, затем исчезает монитор

            this.closeTimer = setTimeout(() => {
                this.open = false;
                this.closing = false;
                this.$store.catalogMonitor.open = false;
                clearInterval(this.clockTimer);
                document.body.classList.remove('overflow-hidden');
                this.opener?.focus?.({ preventScroll: true });
            }, reducedMotion() ? 0 : CLOSE_MS);
        },

        pickChapter(key) {
            this.chapter = key;
            this.category = null;
        },

        pickCategory(slug) {
            this.category = slug;
            this.$refs.pcMain?.scrollTo({ top: 0 });
        },

        tickClock() {
            this.clock = new Intl.DateTimeFormat('ru-RU', { hour: '2-digit', minute: '2-digit' }).format(new Date());
        },
    }));
}
