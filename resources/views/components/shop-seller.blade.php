{{-- Продавец Михалыч. Класс seller-talking включается, пока он «печатает» реплику --}}
<svg viewBox="0 0 200 240" class="seller seller-idle h-auto w-full drop-shadow-[0_10px_10px_rgba(0,0,0,.4)]"
     :class="typing && 'seller-talking'" aria-hidden="true">
    {{-- туловище --}}
    <path d="M30 240 C30 182 60 162 100 162 C140 162 170 182 170 240 Z" fill="#7c2d12"/>
    <path d="M30 240 C30 182 60 162 100 162 C140 162 170 182 170 240 Z" fill="none" stroke="#5b1f0c" stroke-width="2"/>
    {{-- жилет --}}
    <path d="M40 240 C42 192 62 172 88 167 L92 240 Z" fill="#4d5b2a"/>
    <path d="M160 240 C158 192 138 172 112 167 L108 240 Z" fill="#4d5b2a"/>
    <rect x="52" y="200" width="22" height="16" rx="3" fill="#3f4a22"/>
    <rect x="126" y="200" width="22" height="16" rx="3" fill="#3f4a22"/>
    {{-- шея и уши --}}
    <rect x="88" y="146" width="24" height="22" fill="#e8b88a"/>
    <circle cx="62" cy="112" r="8" fill="#e8b88a"/>
    <circle cx="138" cy="112" r="8" fill="#e8b88a"/>
    {{-- голова --}}
    <ellipse cx="100" cy="110" rx="38" ry="44" fill="#f1c79a"/>
    <circle cx="80" cy="122" r="6" fill="#f4a3a3" opacity=".5"/>
    <circle cx="120" cy="122" r="6" fill="#f4a3a3" opacity=".5"/>
    {{-- борода --}}
    <path d="M64 112 C64 160 82 180 100 180 C118 180 136 160 136 112 C128 132 116 138 100 138 C84 138 72 132 64 112 Z" fill="#9ca3af"/>
    {{-- рот --}}
    <path d="M92 141 Q100 145 108 141" stroke="#7f1d1d" stroke-width="2.5" fill="none" stroke-linecap="round"/>
    <ellipse class="mouth-open" cx="100" cy="143" rx="6" ry="4.5" fill="#7f1d1d"/>
    {{-- усы и нос --}}
    <path d="M80 133 C88 124 97 128 100 131 C103 128 112 124 120 133 C111 136 105 136 100 133 C95 136 89 136 80 133 Z" fill="#6b7280"/>
    <ellipse cx="100" cy="119" rx="7" ry="6" fill="#e8a87c"/>
    {{-- брови и глаза --}}
    <path d="M78 96 Q86 91 94 96" stroke="#6b7280" stroke-width="4" fill="none" stroke-linecap="round"/>
    <path d="M106 96 Q114 91 122 96" stroke="#6b7280" stroke-width="4" fill="none" stroke-linecap="round"/>
    <g class="seller-eyes">
        <circle cx="86" cy="104" r="3.5" fill="#1c1917"/>
        <circle cx="114" cy="104" r="3.5" fill="#1c1917"/>
    </g>
    {{-- шляпа с пером --}}
    <path d="M64 76 C64 42 80 30 100 30 C120 30 136 42 136 76 Z" fill="#78716c"/>
    <rect x="64" y="62" width="72" height="9" fill="#3f3f46"/>
    <path d="M130 66 C150 42 160 30 166 22 C158 42 147 58 134 70 Z" fill="#b45309"/>
    <ellipse cx="100" cy="76" rx="60" ry="12" fill="#57534e"/>
</svg>
