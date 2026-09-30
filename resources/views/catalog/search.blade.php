<x-app-layout>
    <x-shop-wall>
        <div class="mx-auto max-w-6xl px-4 py-8">
            <a href="{{ route('home') }}" class="text-sm hover:underline" style="color:#fde68a">← Все разделы</a>

            <x-search-form :value="$query" class="mt-4" />

            <div class="mt-6 rounded-lg p-4" style="background:rgba(28,25,23,.85);border:2px solid #78350f;color:#fffbeb">
                @if ($query === '')
                    Напиши, что ищешь, и мы поищем на складе.
                @elseif (count($products))
                    По запросу «{{ $query }}» найдено: {{ count($products) }}.
                @else
                    По запросу «{{ $query }}» ничего не нашлось. Попробуй сказать иначе или загляни в разделы.
                @endif
            </div>

            @if (count($matchedCategories))
                <div class="mt-4 flex flex-wrap gap-2">
                    @foreach ($matchedCategories as $slug => $category)
                        <a href="{{ route('catalog.category', $slug) }}"
                           class="rounded-full px-3 py-1 text-sm font-semibold hover:brightness-110"
                           style="background:#fde68a;color:#292524">
                            {{ $category['icon'] }} Раздел: {{ $category['title'] }}
                        </a>
                    @endforeach
                </div>
            @endif

            @if (count($products))
                <div class="mt-8 grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach ($products as $product)
                        <x-product-card :product="$product" />
                    @endforeach
                </div>
            @endif
        </div>
    </x-shop-wall>
</x-app-layout>
