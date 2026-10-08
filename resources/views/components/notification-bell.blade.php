<div
    x-data="notificationBell(@js([
        'index'   => route('notifications.index'),
        'read'    => route('notifications.read', '__ID__'),
        'readAll' => route('notifications.read-all'),
    ]))"
    class="relative"
    @keydown.escape.window="open = false"
>
    <button type="button"
            @click="open = !open"
            :aria-expanded="open"
            aria-label="Уведомления"
            class="relative rounded-full p-2 text-amber-100 transition hover:bg-emerald-800 focus:outline-none focus:ring-2 focus:ring-amber-400">
        <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M18 8a6 6 0 10-12 0c0 7-3 9-3 9h18s-3-2-3-9"/>
            <path d="M13.73 21a2 2 0 01-3.46 0"/>
        </svg>

        <span x-show="unread > 0" x-cloak
              x-text="unread > 9 ? '9+' : unread"
              class="absolute -right-0.5 -top-0.5 flex h-5 min-w-[1.25rem] items-center justify-center rounded-full bg-amber-500 px-1 text-xs font-bold text-emerald-950"></span>
    </button>

    <div x-show="open" x-cloak x-transition
         @click.outside="open = false"
         class="absolute right-0 z-50 mt-2 w-80 overflow-hidden rounded-lg border-2 border-amber-800 bg-amber-50 shadow-xl">

        <div class="flex items-center justify-between border-b border-amber-200 px-4 py-2">
            <span class="font-semibold text-emerald-950">Уведомления</span>
            <button type="button" x-show="unread > 0" @click="markAllRead()"
                    class="text-sm text-emerald-700 hover:underline">
                Прочитать все
            </button>
        </div>

        <template x-if="items.length === 0">
            <p class="px-4 py-6 text-center text-sm text-stone-500">Пока ничего нет</p>
        </template>

        <ul class="max-h-96 divide-y divide-amber-200 overflow-y-auto">
            <template x-for="item in items" :key="item.id">
                <li>
                    <button type="button" @click="markRead(item)"
                            class="flex w-full items-start gap-2 px-4 py-3 text-left transition hover:bg-amber-100"
                            :class="item.read_at ? 'opacity-70' : 'bg-amber-100/60'">
                        <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full"
                              :class="item.read_at ? 'bg-transparent' : 'bg-emerald-600'"></span>
                        <span class="min-w-0">
                            <span class="block text-sm text-emerald-950" x-text="message(item)"></span>
                            <span class="block text-xs text-stone-500" x-text="time(item.created_at)"></span>
                        </span>
                    </button>
                </li>
            </template>
        </ul>

        <a href="{{ route('tasks.index') }}"
           class="block border-t border-amber-200 px-4 py-2 text-center text-sm text-emerald-700 hover:bg-amber-100">
            Все задачи
        </a>
    </div>
</div>
