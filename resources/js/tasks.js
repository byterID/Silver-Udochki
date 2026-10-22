import { send } from './http';

const MIN_DELAY = 1500;
const MAX_DELAY = 10000;
const MAX_DELAY_OFFLINE = 30000;
// Если нотификатор лежит, не опрашиваем вечно: уведомление подхватит плановый опрос колокольчика
const NOTIFY_WAIT_LIMIT = 30000;

const BELL_POLL_IDLE = 30000;
const BELL_POLL_ACTIVE = 5000;

export function taskStatus(initial, statusUrl) {
    return {
        task: initial,
        offline: false,
        delay: MIN_DELAY,
        timer: null,
        finishedSeenAt: initial.finished ? Date.now() : null,
        remaining: initial.expires_in,
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

        // Опрос нужен, пока задача не завершена или пока не пришло уведомление
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
            if (document.hidden) {
                this.schedule();
                return;
            }

            try {
                const res = await fetch(statusUrl, { headers: { Accept: 'application/json' } });
                if (res.status === 401 || res.status === 403) {
                    window.location.reload(); // сессия истекла: сервер отправит на вход
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

        // Реагируем только на переходы, иначе события летели бы на каждом опросе
        apply(fresh) {
            const prev = this.task;
            this.task = fresh;

            if (fresh.finished && !prev.finished) {
                this.finishedSeenAt = Date.now();
                this.delay = MIN_DELAY;
                this.remaining = fresh.expires_in;
                this.startCountdown();
            }

            if (fresh.notified && !prev.notified) {
                // На window: колокольчик живёт в навигации и не является предком этого блока
                window.dispatchEvent(new CustomEvent('notifications:refresh'));
            }
        },

        startCountdown() {
            if (this.remaining === null || this.countdown) return;

            this.countdown = setInterval(() => {
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

        scheduleNext() {
            clearTimeout(this.pollTimer);
            const delay = this.pendingTasks ? BELL_POLL_ACTIVE : BELL_POLL_IDLE;
            this.pollTimer = setTimeout(() => {
                if (document.hidden) this.scheduleNext();
                else this.load();
            }, delay);
        },

        async load() {
            // Не пропускаем, а ставим в очередь: идущий запрос мог уйти до появления уведомления
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

        async post(url) {
            try {
                await send('POST', url);
            } catch {
                // Нет сети: следующий load() вернёт правду с сервера
            }
        },

        async markRead(item) {
            if (!item.read_at) {
                item.read_at = new Date().toISOString();
                this.unread = Math.max(0, this.unread - 1);
                // Уходим со страницы только после ответа, иначе браузер оборвёт POST
                await this.post(urls.read.replace('__ID__', item.id));
            }

            if (item.url) window.location.href = item.url;
        },

        async markAllRead() {
            this.items.forEach((item) => { item.read_at ??= new Date().toISOString(); });
            this.unread = 0;
            await this.post(urls.readAll);
        },

        // Список показывает последние 10: после очистки на место прочитанных встанут более старые
        async clearRead() {
            this.items = this.items.filter((item) => !item.read_at);
            await this.post(urls.clearRead);
            this.load();
        },

        message(item) {
            return item.message ?? 'Статус задачи изменился';
        },

        time(iso) {
            const date = new Date(iso);
            if (Number.isNaN(date.getTime())) return '';
            return date.toLocaleString('ru-RU', {
                day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit',
            });
        },
    };
}
