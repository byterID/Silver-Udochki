import { plural } from './promo-board';

export default function registerShopFront(Alpine) {
    Alpine.data('shopFront', (cfg) => ({
        options: cfg.options ?? [],
        catalog: cfg.catalog ?? { chapters: {}, categories: {} },
        promoTitles: cfg.promos ?? [],
        shown: '',
        fullText: '',
        typing: false,
        timer: null,
        query: '',
        promoFlash: false,

        // Монитор-каталог
        catalogOpen: false,
        chapter: cfg.firstChapter ?? 'fishing',
        category: null,
        clock: '',
        clockTimer: null,

        plural,

        init() {
            this.say(`${cfg.greeting} ${this.pickQuestion()}`);
        },

        /** Случайный вопрос, но не такой же, как в прошлый раз. */
        pickQuestion() {
            const list = cfg.questions ?? [];
            if (list.length === 0) return 'Чем помочь?';

            const key = 'su_last_question';
            const last = Number(localStorage.getItem(key) ?? -1);
            let i = Math.floor(Math.random() * list.length);
            if (list.length > 1 && i === last) {
                i = (i + 1 + Math.floor(Math.random() * (list.length - 1))) % list.length;
            }
            localStorage.setItem(key, String(i));
            return list[i];
        },

        /** Эффект печатной машинки. */
        say(text) {
            clearInterval(this.timer);
            this.fullText = text;

            if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
                this.shown = text;
                this.typing = false;
                return;
            }

            const chars = Array.from(text);
            let k = 0;
            this.shown = '';
            this.typing = true;
            this.timer = setInterval(() => {
                k++;
                this.shown = chars.slice(0, k).join('');
                if (k >= chars.length) {
                    clearInterval(this.timer);
                    this.typing = false;
                }
            }, 28);
        },

        skip() {
            if (!this.typing) return;
            clearInterval(this.timer);
            this.shown = this.fullText;
            this.typing = false;
        },

        random(list) {
            return list?.length ? list[Math.floor(Math.random() * list.length)] : '…';
        },

        choose(opt) {
            switch (opt.type) {
                case 'link':
                    this.say(opt.reply);
                    setTimeout(() => { window.location.href = opt.url; }, 900);
                    break;
                case 'book':
                    this.say(opt.reply);
                    this.openCatalog(opt.chapter);
                    break;
                case 'promo':
                    this.say(this.promoTitles.length
                        ? `Сейчас у нас: ${this.promoTitles.join('; ')}. Всё на доске, вон там, слева.`
                        : 'Акций пока нет, но загляни завтра.');
                    this.flashPromo();
                    break;
                default:
                    this.say(this.random(cfg.idle));
            }
        },

        /* ---------- Монитор-каталог ---------- */

        get chapterCategories() {
            return (this.catalog.chapters[this.chapter]?.categories ?? [])
                .map((slug) => ({ slug, ...this.catalog.categories[slug] }));
        },

        get currentCategory() {
            return this.category ? this.catalog.categories[this.category] ?? null : null;
        },

        openCatalog(chapter = null) {
            if (chapter) this.chapter = chapter;
            this.category = null;
            this.catalogOpen = true;
            this.tickClock();
            clearInterval(this.clockTimer);
            this.clockTimer = setInterval(() => this.tickClock(), 15000);
            document.body.classList.add('overflow-hidden');
            this.$nextTick(() => this.$refs.pcScreen?.focus({ preventScroll: true }));
        },

        // Старое имя, чтобы ничего не сломалось
        openBook(chapter = null) {
            this.openCatalog(chapter);
        },

        closeCatalog() {
            this.catalogOpen = false;
            clearInterval(this.clockTimer);
            document.body.classList.remove('overflow-hidden');
            this.$nextTick(() => this.$refs.catalogStand?.focus({ preventScroll: true }));
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

        /* ---------- Прочее ---------- */

        flashPromo() {
            this.promoFlash = true;
            setTimeout(() => { this.promoFlash = false; }, 2500);
        },

        ringBell() {
            this.say(this.pickQuestion());
        },

        submit() {
            const q = this.query.trim();
            if (!q) {
                this.say('Ты скажи словами, а я уж поищу.');
                return;
            }
            this.say(`«${q}»? Сейчас гляну на складе…`);
            setTimeout(() => {
                window.location.href = `${cfg.searchUrl}?q=${encodeURIComponent(q)}`;
            }, 800);
        },

        /** Клавиши 1–5 выбирают ответ, Esc закрывает каталог. */
        hotkey(e) {
            if (e.key === 'Escape') {
                if (this.catalogOpen) this.closeCatalog();
                return;
            }
            if (this.catalogOpen || e.ctrlKey || e.metaKey || e.altKey) return;
            if (['INPUT', 'TEXTAREA', 'SELECT'].includes(e.target.tagName)) return;

            const n = Number(e.key);
            if (n >= 1 && n <= this.options.length) {
                e.preventDefault();
                this.choose(this.options[n - 1]);
            }
        },
    }));
}
