@php
    $chapters = $catalogNav['chapters'] ?? [];
    $categories = $catalogNav['categories'] ?? [];
@endphp

<nav x-data="{ catalogOpen: false, mobileSearch: false, chapter: @js(array_key_first($chapters)) }"
     @keydown.escape.window="catalogOpen = false"
     class="sticky top-0 z-40 border-b-4 border-amber-800 bg-emerald-950 shadow">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="flex h-16 items-center gap-2 sm:gap-4">

            {{-- Логотип → главная --}}
            <a href="{{ route('home') }}" class="flex shrink-0 items-center gap-2 text-amber-50" aria-label="На главную">
                <span class="text-2xl leading-none" aria-hidden="true">🎣</span>
                <span class="hidden text-lg font-semibold tracking-wide md:inline">Silver Udochki</span>
            </a>

            {{-- Каталог --}}
            <button type="button"
                    @click="catalogOpen = !catalogOpen; mobileSearch = false"
                    :aria-expanded="catalogOpen"
                    class="inline-flex h-10 shrink-0 items-center gap-2 rounded-lg bg-amber-700 px-3 text-sm font-semibold text-amber-50 transition hover:bg-amber-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-300 sm:px-4">
                <svg x-show="!catalogOpen" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true">
                    <path d="M4 6h16M4 12h16M4 18h16"/>
                </svg>
                <svg x-show="catalogOpen" x-cloak class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true">
                    <path d="M6 18L18 6M6 6l12 12"/>
                </svg>
                <span class="hidden sm:inline">Каталог</span>
            </button>

            {{-- Поиск (планшет и ПК) --}}
            <form action="{{ route('catalog.search') }}" method="GET" role="search" class="hidden flex-1 sm:flex">
                <div class="flex w-full overflow-hidden rounded-lg border-2 border-amber-700 bg-white focus-within:border-amber-500">
                    <input type="search" name="q" value="{{ request()->routeIs('catalog.search') ? request('q') : '' }}"
                           maxlength="100" placeholder="Искать удочки, блёсны, манки…"
                           class="w-full border-0 px-4 text-sm font-medium bg-orange-100 text-amber-600 placeholder:text-amber-600 focus:ring-0">
                    <button type="submit" class="bg-amber-700 px-4 text-amber-50 transition hover:bg-amber-600" aria-label="Найти">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true">
                            <circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/>
                        </svg>
                    </button>
                </div>
            </form>

            <div class="ml-auto flex shrink-0 items-center gap-1 sm:ml-0 sm:gap-2">
                {{-- Поиск (телефон) --}}
                <button type="button" @click="mobileSearch = !mobileSearch; catalogOpen = false"
                        class="rounded-full p-2 text-amber-100 transition hover:bg-emerald-800 sm:hidden" aria-label="Поиск">
                    <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true">
                        <circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/>
                    </svg>
                </button>

                @auth
                    <x-notification-bell />
                @endauth

                {{-- Профиль --}}
                <x-dropdown align="right" width="w-56">
                    <x-slot name="trigger">
                        <button type="button"
                                class="flex items-center gap-2 rounded-lg px-1.5 py-1 text-amber-50 transition hover:bg-emerald-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-300">
                            @auth
                                <x-dog-avatar :seed="Auth::id()" :size="32" />
                                <span class="hidden max-w-[9rem] truncate text-sm font-medium lg:inline">{{ Auth::user()->name }}</span>
                            @else
                                <svg class="h-7 w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true">
                                    <circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0116 0"/>
                                </svg>
                                <span class="hidden text-sm font-medium lg:inline">Войти</span>
                            @endauth
                            <svg class="h-4 w-4 fill-current" viewBox="0 0 20 20" aria-hidden="true">
                                <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                            </svg>
                        </button>
                    </x-slot>

                    <x-slot name="content">
                        @auth
                            <div class="border-b border-gray-100 px-4 py-2">
                                <div class="truncate text-sm font-semibold text-gray-800">{{ Auth::user()->name }}</div>
                                <div class="truncate text-xs text-gray-500">{{ Auth::user()->email }}</div>
                            </div>

                            <x-dropdown-link :href="route('tasks.index')">Мои задачи</x-dropdown-link>
                            <x-dropdown-link :href="route('profile.edit')">{{ __('Profile') }}</x-dropdown-link>

                            @can('access_control_panel')
                                <x-dropdown-link :href="route('control-panel.index')">{{ __('Control panel') }}</x-dropdown-link>
                            @endcan
                        @else
                            <x-dropdown-link :href="route('login')">Войти</x-dropdown-link>
                            @if (Route::has('register'))
                                <x-dropdown-link :href="route('register')">Зарегистрироваться</x-dropdown-link>
                            @endif
                        @endauth

                        <x-dropdown-link :href="route('request-info')" title="Как ваш запрос дошёл до сервера">
                            Диагностика
                        </x-dropdown-link>

                        @auth
                            <form method="POST" action="{{ route('logout') }}" class="border-t border-gray-100">
                                @csrf
                                <x-dropdown-link :href="route('logout')"
                                        onclick="event.preventDefault(); this.closest('form').submit();">
                                    {{ __('Log Out') }}
                                </x-dropdown-link>
                            </form>
                        @endauth
                    </x-slot>
                </x-dropdown>
            </div>
        </div>

        {{-- Поиск (телефон), раскрывается по лупе --}}
        <form x-show="mobileSearch" x-cloak action="{{ route('catalog.search') }}" method="GET" role="search" class="pb-3 sm:hidden">
            <input type="search" name="q" maxlength="100" placeholder="Поиск по лавке…"
                   x-effect="mobileSearch && $nextTick(() => $el.focus())"
                   class="w-full rounded-lg border-2 border-amber-700 px-4 text-sm text-gray-800 focus:border-amber-500 focus:ring-0">
        </form>
    </div>

    {{-- Затемнение под каталогом --}}
    <div x-show="catalogOpen" x-cloak x-transition.opacity
         @click="catalogOpen = false"
         class="fixed inset-x-0 bottom-0 top-16 bg-black/40"></div>

    {{-- Экран каталога --}}
    <div x-show="catalogOpen" x-cloak x-transition.origin.top
         class="absolute inset-x-0 top-full max-h-[calc(100vh-4rem)] overflow-y-auto border-t border-amber-800 bg-white shadow-xl">
        <div class="mx-auto flex max-w-7xl flex-col sm:flex-row">

            {{-- Разделы --}}
            <ul class="flex shrink-0 gap-1 overflow-x-auto border-b border-gray-200 p-3 sm:w-60 sm:flex-col sm:border-b-0 sm:border-r">
                @foreach ($chapters as $key => $ch)
                    <li>
                        <button type="button"
                                @mouseenter="chapter = @js($key)" @click="chapter = @js($key)"
                                :class="chapter === @js($key) ? 'bg-amber-100 text-amber-900' : 'text-gray-700 hover:bg-gray-100'"
                                class="flex w-full items-center gap-3 whitespace-nowrap rounded-lg px-3 py-2 text-left text-sm font-medium transition">
                            <span class="text-lg" aria-hidden="true">{{ $ch['icon'] }}</span>
                            {{ $ch['title'] }}
                        </button>
                    </li>
                @endforeach
            </ul>

            {{-- Категории выбранного раздела --}}
            <div class="flex-1 p-4 sm:p-6">
                @foreach ($chapters as $key => $ch)
                    <div x-show="chapter === @js($key)" @if (! $loop->first) x-cloak @endif
                         class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach ($ch['categories'] as $slug)
                            @php $cat = $categories[$slug]; @endphp
                            <a href="{{ $cat['url'] }}"
                               class="flex items-start gap-3 rounded-lg border border-gray-200 p-3 transition hover:border-amber-400 hover:bg-amber-50">
                                <span class="text-2xl leading-none" aria-hidden="true">{{ $cat['icon'] }}</span>
                                <span class="min-w-0">
                                    <span class="block text-sm font-semibold text-gray-900">{{ $cat['title'] }}</span>
                                    <span class="block text-xs text-gray-500">{{ $cat['description'] }}</span>
                                </span>
                            </a>
                        @endforeach
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</nav>
