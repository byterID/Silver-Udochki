{{-- Михалыч: картинка, поверх неё веки и рот. typing приходит из shopFront --}}
<div x-data="seller" class="mikhalych" :class="typing && 'is-talking'" aria-hidden="true">
    <div class="mikhalych__body">
        <img src="{{ asset('images/shop/mikhalych.webp') }}" alt="" width="600" height="804"
             class="mikhalych__img" draggable="false">
        <span class="mikhalych__mouth"></span>
        <span class="mikhalych__lid mikhalych__lid--l" :class="blink && 'is-closed'"></span>
        <span class="mikhalych__lid mikhalych__lid--r" :class="blink && 'is-closed'"></span>
    </div>
</div>