<nav x-data="{ open: false }" class="relative z-30 border-b-4 border-amber-800 bg-gradient-to-b from-emerald-950 to-emerald-900 shadow-md">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="flex h-16 items-center justify-between gap-3">

            {{-- Слева: Лавка + Диагностика --}}
            <div class="flex items-center gap-2 sm:gap-3">
                <x-header-button :href="route('home')" :active="request()->routeIs('home')">
                    <svg class="hidden h-4 w-4 sm:block" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M3 10l9-6 9 6"/>
                        <path d="M5 9v11h14V9"/>
                        <path d="M10 20v-6h4v6"/>
                    </svg>
                    Лавка
                </x-header-button>

                <x-header-button :href="route('request-info')" :active="request()->routeIs('request-info')"
                                 title="Как ваш запрос дошёл до сервера">
                    <svg class="hidden h-4 w-4 sm:block" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <circle cx="12" cy="12" r="9"/>
                        <path d="M15.5 8.5l-2 5-5 2 2-5z" fill="currentColor"/>
                    </svg>
                    Диагностика
                </x-header-button>
            </div>

            {{-- Справа (планшет и ПК) --}}
            <div class="hidden items-center gap-3 sm:flex">
                @auth
                    <x-notification-bell />

                    <x-dropdown align="right" width="48">
                        <x-slot name="trigger">
                            <x-header-button class="!pl-1">
                                <x-dog-avatar :seed="Auth::id()" :size="30" />
                                <span class="max-w-[10rem] truncate">{{ Auth::user()->name }}</span>
                                <svg class="h-4 w-4 fill-current" viewBox="0 0 20 20" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                </svg>
                            </x-header-button>
                        </x-slot>

                        <x-slot name="content">
                            <x-dropdown-link :href="route('tasks.index')">
                                Мои задачи
                            </x-dropdown-link>

                            <x-dropdown-link :href="route('profile.edit')">
                                {{ __('Profile') }}
                            </x-dropdown-link>

                            @can('access_control_panel')
                                <x-dropdown-link :href="route('control-panel.index')">
                                    {{ __('Control panel') }}
                                </x-dropdown-link>
                            @endcan

                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <x-dropdown-link :href="route('logout')"
                                        onclick="event.preventDefault(); this.closest('form').submit();">
                                    {{ __('Log Out') }}
                                </x-dropdown-link>
                            </form>
                        </x-slot>
                    </x-dropdown>
                @else
                    <x-header-button :href="route('login')" :active="request()->routeIs('login')">
                        Войти
                    </x-header-button>
                    @if (Route::has('register'))
                        <x-header-button :href="route('register')" :active="request()->routeIs('register')">
                            Зарегистрироваться
                        </x-header-button>
                    @endif
                @endauth
            </div>

            {{-- Кнопка «Меню» (телефон) --}}
            <x-header-button class="!px-3 sm:hidden" x-on:click="open = ! open" aria-label="Меню">
                <svg x-show="! open" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true">
                    <path d="M4 6h16M4 12h16M4 18h16"/>
                </svg>
                <svg x-show="open" x-cloak class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true">
                    <path d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </x-header-button>
        </div>
    </div>

    {{-- Мобильное меню --}}
    <div x-show="open" x-cloak x-transition.origin.top class="border-t border-emerald-800 bg-emerald-950 px-4 py-4 sm:hidden">
        @auth
            <div class="mb-4 flex items-center gap-3">
                <x-dog-avatar :seed="Auth::id()" :size="40" />
                <div class="min-w-0">
                    <div class="truncate font-semibold text-amber-50">{{ Auth::user()->name }}</div>
                    <div class="truncate text-sm text-amber-200/70">{{ Auth::user()->email }}</div>
                </div>
            </div>

            <div class="grid gap-2">
                <x-header-button :href="route('tasks.index')" class="w-full justify-center">
                    Мои задачи
                </x-header-button>

                <x-header-button :href="route('profile.edit')" class="w-full justify-center">
                    {{ __('Profile') }}
                </x-header-button>

                @can('access_control_panel')
                    <x-header-button :href="route('control-panel.index')" class="w-full justify-center">
                        {{ __('Control panel') }}
                    </x-header-button>
                @endcan

                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <x-header-button type="submit" class="w-full justify-center">
                        {{ __('Log Out') }}
                    </x-header-button>
                </form>
            </div>
        @else
            <div class="grid gap-2">
                <x-header-button :href="route('login')" class="w-full justify-center">Войти</x-header-button>
                @if (Route::has('register'))
                    <x-header-button :href="route('register')" class="w-full justify-center">Зарегистрироваться</x-header-button>
                @endif
            </div>
        @endauth
    </div>
</nav>
