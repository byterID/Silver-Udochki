@php
    $allCategories = config('shop.categories', []);
    $shopChapters = config('shop.chapters', []);
@endphp

<x-app-layout>
    <x-shop-wall>
        <div class="mx-auto max-w-6xl px-4 py-8">
            <h1 class="text-3xl font-bold" style="color:#fffbeb">Серебряные удочки</h1>
            <p class="mt-1" style="color:#fde68a">Лавка для охотников и рыболовов</p>

            <x-search-form class="mt-6" />

            @if (empty($shopChapters))
                <p class="mt-10 rounded-lg p-6" style="background:#fee2e2;color:#7f1d1d">
                    Laravel не видит файл config/shop.php. Проверь, что он лежит в папке config,
                    и выполни: docker compose exec app php artisan optimize:clear
                </p>
            @endif

            @foreach ($shopChapters as $chapter)
                <h2 class="mt-10 flex items-center gap-2 text-2xl font-bold" style="color:#fef3c7">
                    <span>{{ $chapter['icon'] }}</span> {{ $chapter['title'] }}
                </h2>

                <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($chapter['categories'] as $slug)
                        @continue(! isset($allCategories[$slug]))
                        @php($category = $allCategories[$slug])

                        <a href="{{ route('catalog.category', $slug) }}"
                           class="group flex items-center gap-4 rounded-lg p-4 transition hover:-translate-y-1"
                           style="background:#f3e7c9;border:4px solid #78350f;box-shadow:0 6px 0 rgba(0,0,0,.35)">
                            <span class="text-4xl transition group-hover:scale-110">{{ $category['icon'] }}</span>
                            <span>
                                <span class="block font-semibold" style="color:#292524">{{ $category['title'] }}</span>
                                <span class="block text-sm" style="color:#57534e">{{ $category['description'] }}</span>
                            </span>
                        </a>
                    @endforeach
                </div>
            @endforeach
        </div>
    </x-shop-wall>
</x-app-layout>
