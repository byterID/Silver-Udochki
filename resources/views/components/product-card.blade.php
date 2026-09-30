@props(['product'])

@php
    $payload = [
        'id'    => $product['id'],
        'name'  => $product['name'],
        'price' => $product['price'],
        'icon'  => $product['icon'],
    ];
    $isImage = str_starts_with($product['icon'], '/') || str_starts_with($product['icon'], 'http');
@endphp

<article x-data class="group flex flex-col overflow-hidden rounded-lg border-4 border-amber-900 bg-[#f3e7c9] shadow-[0_6px_0_rgba(0,0,0,.35)]">
    <div data-product-icon class="flex h-32 select-none items-center justify-center bg-gradient-to-b from-amber-100 to-amber-200 text-6xl transition group-hover:scale-110">
        @if ($isImage)
            <img src="{{ $product['icon'] }}" alt="" class="h-24 w-24 object-contain">
        @else
            {{ $product['icon'] }}
        @endif
    </div>

    <div class="flex flex-1 flex-col p-4">
        <h3 class="font-semibold leading-snug text-stone-800">{{ $product['name'] }}</h3>

        <div class="mt-auto flex items-center justify-between gap-2 pt-4">
            <span class="text-lg font-bold text-red-900">{{ number_format($product['price'], 0, ',', ' ') }} ₽</span>
            <button type="button"
                    @click="$store.cart.add(@js($payload), $el.closest('article').querySelector('[data-product-icon]'))"
                    class="rounded-md bg-emerald-800 px-3 py-1.5 text-sm font-semibold text-amber-50 transition hover:bg-emerald-700 active:scale-95">
                В корзину
            </button>
        </div>
    </div>
</article>
