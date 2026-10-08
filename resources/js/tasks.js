const MIN_DELAY = 1500;
const MAX_DELAY = 10000;
const MAX_DELAY_OFFLINE = 30000;
// Сколько ждём нотификатор после завершения задачи. Если он лежит,
// не долбим сервер вечно: колокольчик подхватит уведомление плановым опросом.
const NOTIFY_WAIT_LIMIT = 30000;

const BELL_POLL_IDLE = 30000;   // задач нет — редко
const BELL_POLL_ACTIVE = 5000;  // ждём уведомление — часто

export function taskStatus(initial, statusUrl) {
    return {
        task: initial,
        offline: false,
        delay: MIN_DELAY,
        timer: null,
        finishedSeenAt: initial.finished ? Date.now() : null,
        remaining: initial.expires_in,   // секунды до удаления файла
        countdown: null,

        init() {
            this.startCountdown();
            if (!this.isSettled()) this.schedule();

            document.addEventListener('visibilitychange', () => {
                if (!document.hidden && !this.isSettled()) {
                    this.delay = MIN_DELAY;
                    this.schedule(0);
                }
            });
        },

        // Опрос нужен, пока задача не завершена ИЛИ пока не пришло уведомление.
        isSettled() {
            if (!this.task.finished) return false;
            if (this.task.notified) return true;
            return Date.now() - this.finishedSeenAt > NOTIFY_WAIT_LIMIT;
        },

        schedule(ms = this.delay) {
            clearTimeout(this.timer);
            this.timer = setTimeout(() => this.poll(), ms);
        },

        async poll() {
            if (document.hidden) {           // вкладка в фоне: не тратим запросы
                this.schedule();
                return;
            }

            try {
                const res = await fetch(statusUrl, { headers: { Accept: 'application/json' } });
                if (res.status === 401 || res.status === 403) {
                    window.location.reload(); // сессия истекла: пусть сервер отправит на логин
                    return;
                }
                if (!res.ok) throw new Error(`HTTP ${res.status}`);

                this.apply(await res.json());
                this.offline = false;
                this.delay = Math.min(this.delay * 1.5, MAX_DELAY);
            } catch {
                this.offline = true;
                this.delay = Math.min(this.delay * 2, MAX_DELAY_OFFLINE);
            }

            if (!this.isSettled()) this.schedule();
        },

        // Сравниваем старое и новое состояние: реагируем только на переходы,
        // иначе событие улетало бы при каждом опросе.
        apply(fresh) {
            const prev = this.task;
            this.task = fresh;

            if (fresh.finished && !prev.finished) {
                this.finishedSeenAt = Date.now();
                this.delay = MIN_DELAY;      // уведомление вот-вот появится, спрашиваем чаще
                this.remaining = fresh.expires_in;
                this.startCountdown();
            }

            if (fresh.notified && !prev.notified) {
                // На window, а не на элементе: колокольчик живёт в навигации,
                // он не родитель этого блока, и всплытие до него могло не дойти.
                window.dispatchEvent(new CustomEvent('notifications:refresh'));
            }
        },

        startCountdown() {
            if (this.remaining === null || this.countdown) return;

            this.countdown = setTimeout(() => {
                this.remaining = Math.max(0, this.remaining - 1);
                if (this.remaining === 0) {
                    clearInterval(this.countdown);
                    this.task.download_url = null;
                    this.task.expired = true;
                }
            }, 1000);
        },

        get remainingLabel() {
            const m = Math.floor(this.remaining / 60);
            const s = String(this.remaining % 60).padStart(2, '0');
            return `${m}:${s}`;
        },
    };
}

const BELL_POLL_INTERVAL = 30000;

// CSRF-токен из <meta name="csrf-token"> в layouts/app.blade.php.
// Без него Laravel отвечает на любой POST ошибкой 419.
function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
}

export function notificationBell(urls) {
    return {
        open: false,
        items: [],
        unread: 0,
        loading: false,
        reloadQueued: false,
        pendingTasks: false,
        pollTimer: null,

        init() {
            this.load();

            window.addEventListener('notifications:refresh', () => this.load());

            document.addEventListener('visibilitychange', () => {
                if (!document.hidden) this.load();
            });
        },

        // Следующий опрос планируется после каждого load(): интервал
        // выбирается по свежему ответу сервера.
        scheduleNext() {
            clearTimeout(this.pollTimer);
            const delay = this.pendingTasks ? BELL_POLL_ACTIVE : BELL_POLL_IDLE;
            this.pollTimer = setTimeout(() => {
                if (document.hidden) this.scheduleNext();  // вкладка в фоне — пропускаем
                else this.load();
            }, delay);
        },

        async load() {
            // Если запрос уже идёт, новый не запускаем, а ставим в очередь.
            // Просто пропустить нельзя: плановый запрос мог уйти за миг до
            // появления уведомления, а сигнал refresh тогда потерялся бы.
            if (this.loading) {
                this.reloadQueued = true;
                return;
            }

            this.loading = true;
            try {
                const res = await fetch(urls.index, { headers: { Accept: 'application/json' } });
                if (res.ok) {
                    const data = await res.json();
                    this.items = data.items ?? [];
                    this.unread = data.unread ?? 0;
                    this.pendingTasks = data.pending_tasks ?? false;
                }
            } catch {
            } finally {
                this.loading = false;
                if (this.reloadQueued) {
                    this.reloadQueued = false;
                    this.load();
                } else {
                    this.scheduleNext();
                }
            }
        },

        post(url) {
            return fetch(url, {
                method: 'POST',
                headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken() },
            });
        },

        async markRead(item) {
            if (!item.read_at) {
                // Оптимистичное обновление: сначала меняем интерфейс, потом
                // сообщаем серверу. Кнопка реагирует мгновенно, без ожидания сети.
                item.read_at = new Date().toISOString();
                this.unread = Math.max(0, this.unread - 1);
                try {
                    await this.post(urls.read.replace('__ID__', item.id));
                } catch {
                    // Не страшно: при следующем load() придёт правда с сервера.
                }
            }

            // Переходим только после await: если уйти со страницы раньше,
            // браузер может оборвать POST, и отметка «прочитано» не сохранится.
            const url = item.url;           // ← поправьте ключ, если у вас другой
            if (url) window.location.href = url;
        },

        async markAllRead() {
            this.items.forEach((item) => { item.read_at ??= new Date().toISOString(); });
            this.unread = 0;
            try {
                await this.post(urls.readAll);
            } catch {}
        },

        message(item) {
            return item.message ?? 'Статус задачи изменился';
        },

        time(iso) {
            const date = new Date(iso);
            // Защита на будущее: битая дата не должна показываться пользователю как "Invalid Date"
            if (Number.isNaN(date.getTime())) return '';
            return date.toLocaleString('ru-RU', {
                day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit',
            });
        },
    };
}
