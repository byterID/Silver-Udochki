@props(['name'])

<div x-data="{ show: false }"
     x-on:open-modal.window="if ($event.detail === '{{ $name }}') show = true"
     x-on:close-modal.window="if ($event.detail === '{{ $name }}') show = false"
     x-show="show" x-cloak
     class="fixed inset-0 z-50 flex items-center justify-center p-4"
     x-transition.opacity>
    <div class="absolute inset-0 bg-black/50" @click="show = false"></div>
    <div class="relative w-full max-w-lg rounded-xl bg-white p-6 shadow-xl"
         @keydown.escape.window="show = false" x-transition>
        {{ $slot }}
    </div>
</div>
