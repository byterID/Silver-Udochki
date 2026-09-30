export default function registerShopFront(Alpine) {
    Alpine.data('shopFront', (cfg) => ({
        options: cfg.options ?? [],
        shown: '',
        fullText: '',
        typing: false,
        timer: null,
        query: '',
        bookOpen: false,
        chapter: cfg.firstChapter ?? 'fishing',
        promoFlash: false,

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

        /** Клик по реплике: показать её целиком сразу. */
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
                    this.openBook(opt.chapter);
                    break;
                case 'promo':
                    this.say(cfg.promos?.length
                        ? `Сейчас у нас: ${cfg.promos.join('; ')}. Всё на доске у меня за спиной.`
                        : 'Акций пока нет, но загляни завтра.');
                    this.flashPromo();
                    break;
                default:
                    this.say(this.random(cfg.idle));
            }
        },

        openBook(chapter = null) {
            if (chapter) this.chapter = chapter;
            this.bookOpen = true;
        },

        flashPromo() {
            this.promoFlash = true;
            setTimeout(() => { this.promoFlash = false; }, 2500);
        },

        ringBell() {
            this.say(this.pickQuestion());
        },

        /** Свой ответ продавцу = поиск по сайту. */
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
            if (e.key === 'Escape') { this.bookOpen = false; return; }
            if (this.bookOpen || e.ctrlKey || e.metaKey || e.altKey) return;
            if (['INPUT', 'TEXTAREA', 'SELECT'].includes(e.target.tagName)) return;

            const n = Number(e.key);
            if (n >= 1 && n <= this.options.length) {
                e.preventDefault();
                this.choose(this.options[n - 1]);
            }
        },
    }));
}
