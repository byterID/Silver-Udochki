<div class="relative min-h-[3em] flex-1" @mouseenter="paused = true" @mouseleave="paused = false">
    <template x-for="(p, k) in active" :key="p.title">
        <div x-show="k === pos"
             x-transition:enter="transition-[clip-path,opacity] duration-700 ease-out"
             x-transition:enter-start="opacity-60 [clip-path:inset(0_100%_0_0)]"
             x-transition:enter-end="opacity-100 [clip-path:inset(0_0_0_0)]"
             x-transition:leave="transition duration-300"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0 blur-[2px]"
             class="absolute inset-0 flex flex-col items-center justify-center text-center">
            <p class="chalk text-[1em] leading-tight" x-text="p.title"></p>
            <p class="mt-[.15em] text-[.62em] leading-tight text-white/60">
                <span x-show="p.note" x-text="p.note"></span>
                <span x-show="p.ends" class="text-yellow-200/90" x-text="(p.note ? ' · ' : '') + left(p)"></span>
            </p>
        </div>
    </template>
    <p x-show="!active.length" class="absolute inset-0 flex items-center justify-center text-white/60">Скоро будут…</p>
</div>

<div x-show="active.length > 1" class="mt-[.2em] flex justify-center gap-[.4em]">
    <template x-for="(p, k) in active" :key="'dot-' + p.title">
        <button type="button" @click="go(k)" :aria-label="'Акция ' + (k + 1)"
                class="h-[.35em] w-[.35em] rounded-full transition"
                :class="k === pos ? 'bg-yellow-200' : 'bg-white/30 hover:bg-white/60'"></button>
    </template>
</div>
