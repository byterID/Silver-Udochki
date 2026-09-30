@props(['seed' => 0, 'size' => 36])

@php
    // Окрас собаки выбирается по id пользователя, чтобы аватар не менялся между визитами.
    $coats = [
        ['head' => '#a0632e', 'ears' => '#6b3f1d'], // рыжий спаниель
        ['head' => '#3f3a36', 'ears' => '#1f1b18'], // чёрный лабрадор
        ['head' => '#d9a066', 'ears' => '#8a5a2b'], // палевый ретривер
    ];
    $coat = $coats[abs((int) $seed) % count($coats)];
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex shrink-0 overflow-hidden rounded-full ring-2 ring-amber-400/70 bg-amber-100']) }}
      style="width: {{ (int) $size }}px; height: {{ (int) $size }}px;">
    <svg viewBox="0 0 64 64" class="h-full w-full" aria-hidden="true">
        <circle cx="32" cy="32" r="32" fill="#e7d7b1"/>
        {{-- уши --}}
        <path d="M14 20c-6 6-7 20-3 28 2 4 7 4 9 0 3-7 3-18 1-26-1-4-4-5-7-2z" fill="{{ $coat['ears'] }}"/>
        <path d="M50 20c6 6 7 20 3 28-2 4-7 4-9 0-3-7-3-18-1-26 1-4 4-5 7-2z" fill="{{ $coat['ears'] }}"/>
        {{-- голова --}}
        <path d="M20 22c0-8 5-13 12-13s12 5 12 13v12c0 9-5 16-12 16s-12-7-12-16z" fill="{{ $coat['head'] }}"/>
        <path d="M30 12h4l2 20h-8z" fill="#f3e6cc" opacity=".9"/>
        {{-- морда --}}
        <ellipse cx="32" cy="41" rx="9" ry="7" fill="#f3e6cc"/>
        <ellipse cx="32" cy="37" rx="3.5" ry="2.5" fill="#2b1a0e"/>
        <path d="M32 40v3m0 0c-1.5 2-4 2-5 1m5-1c1.5 2 4 2 5 1" stroke="#2b1a0e" stroke-width="1.4" fill="none" stroke-linecap="round"/>
        {{-- глаза --}}
        <circle cx="26" cy="27" r="2.2" fill="#2b1a0e"/>
        <circle cx="38" cy="27" r="2.2" fill="#2b1a0e"/>
        <circle cx="26.7" cy="26.3" r=".7" fill="#fff"/>
        <circle cx="38.7" cy="26.3" r=".7" fill="#fff"/>
        {{-- ошейник --}}
        <path d="M22 50c6 4 14 4 20 0l2 6c-8 5-16 5-24 0z" fill="#b91c1c"/>
        <circle cx="32" cy="55" r="2" fill="#facc15"/>
    </svg>
</span>
