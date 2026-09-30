<x-app-layout>
    <x-shop-wall>
        <div class="mx-auto max-w-6xl px-4 py-8">
            <a href="{{ route('home') }}" class="text-sm hover:underline" style="color:#fde68a">← Все разделы</a>

            <div class="mt-4 flex items-center gap-4">
                <span class="text-5xl">{{ $category['icon'] }}</span>
                <div>
                    <h1 class="text-3xl font-bold" style="color:#fffbeb">{{ $category['title'] }}</h1>
                    <p style="color:#fde68a">{{ $category['description'] }}</p>
                </div>
            </div>

            @if (count($products))
                <div class="mt-8 grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach ($products as $product)
                        <x-product-card :product="$product" />
                    @endforeach
                </div>
            @else
                <p class="mt-10 rounded-lg p-6" style="background:rgba(0,0,0,.3);color:#fef3c7">
                    Товары этого раздела ещё в пути. Загляни попозже.
                </p>
            @endif
        </div>
    </x-shop-wall>
</x-app-layout>
