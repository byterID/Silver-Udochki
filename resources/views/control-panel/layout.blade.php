<x-app-layout>
    <div class="flex min-h-[75vh] bg-gray-50">

        <aside class="w-60 shrink-0 border-r border-gray-200 bg-white p-5">
            <h3 class="mb-4 text-xs font-semibold uppercase tracking-wider text-gray-400">Разделы</h3>
            <nav class="flex flex-col gap-1">
                {{-- Выпадающее меню "Пользователи" --}}
                <div x-data="{ open: {{ request()->routeIs('control-panel.staff', 'control-panel.customers') ? 'true' : 'false' }} }">
                    <button type="button" @click="open = !open"
                            class="flex w-full items-center justify-between rounded-lg px-3 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-100">
                        <span>👤 Пользователи</span>
                        <svg class="h-4 w-4 transition-transform" :class="open ? 'rotate-180' : ''"
                             fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>

                    <div x-show="open" x-collapse class="ml-3 mt-1 flex flex-col gap-1 border-l border-gray-200 pl-3">
                        <a href="{{ route('control-panel.staff') }}"
                           class="rounded-lg px-3 py-2 text-sm font-medium transition
                                  {{ request()->routeIs('control-panel.staff')
                                     ? 'bg-indigo-50 text-indigo-700'
                                     : 'text-gray-700 hover:bg-gray-100' }}">
                            👥 Сотрудники
                        </a>
                        <a href="{{ route('control-panel.customers') }}"
                           class="rounded-lg px-3 py-2 text-sm font-medium transition
                                  {{ request()->routeIs('control-panel.customers')
                                     ? 'bg-indigo-50 text-indigo-700'
                                     : 'text-gray-700 hover:bg-gray-100' }}">
                            🛒 Покупатели
                        </a>
                    </div>
                </div>
            </nav>
        </aside>

        <main class="flex-1 p-6">
            @yield('panel-content')
        </main>

    </div>
</x-app-layout>
