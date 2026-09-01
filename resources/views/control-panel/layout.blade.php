<x-app-layout>
    <div style="display: flex; min-height: 70vh;">

        <aside style="width: 220px; border-right: 1px solid #ccc; padding: 16px;">
            <h3>Разделы</h3>
            <nav style="display: flex; flex-direction: column; gap: 8px;">
                <a href="{{ route('control-panel.users') }}">Пользователи</a>
            </nav>
        </aside>

        <main style="flex: 1; padding: 16px;">
            @yield('panel-content')
        </main>

    </div>
</x-app-layout>
