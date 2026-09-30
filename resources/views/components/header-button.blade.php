{{-- Единая кнопка шапки. С href — ссылка, без href — обычная кнопка --}}
@props(['href' => null, 'active' => false])

@php
    $classes = 'inline-flex h-10 items-center gap-2 whitespace-nowrap rounded-md border border-amber-950 '
        .'bg-gradient-to-b from-amber-700 to-amber-900 px-4 text-sm font-semibold text-amber-50 '
        .'shadow-[inset_0_1px_0_rgba(255,255,255,.25),0_2px_0_#451a03] transition '
        .'hover:brightness-110 active:translate-y-px focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-300'
        .($active ? ' ring-2 ring-amber-300' : '');
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</a>
@else
    <button {{ $attributes->merge(['type' => 'button', 'class' => $classes]) }}>{{ $slot }}</button>
@endif
