<x-app-layout>
    <div class="flex min-h-[calc(100vh-4rem)] items-center justify-center bg-gradient-to-b from-emerald-900 to-stone-900 px-4">
        <div class="max-w-md text-center text-amber-50">
            <p class="text-6xl">🎣</p>
            <h1 class="mt-4 text-3xl font-bold">Серебряные удочки</h1>
            <p class="mt-2 text-amber-200/80">
                Лавка для охотников и рыболовов. Скоро здесь встанет прилавок и появится продавец.
            </p>

            @auth
                <p class="mt-6 text-sm text-amber-100/70">
                    С возвращением, {{ Auth::user()->name }}!
                </p>
            @endauth
        </div>
    </div>
</x-app-layout>
