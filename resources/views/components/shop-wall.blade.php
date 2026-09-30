<div {{ $attributes->merge(['class' => 'min-h-[calc(100vh-4rem)] pb-44']) }}
     style="background-color:#3b2412;
            background-image:
                repeating-linear-gradient(90deg, rgba(0,0,0,.25) 0 2px, transparent 2px 120px),
                repeating-linear-gradient(0deg, rgba(255,255,255,.03) 0 1px, transparent 1px 7px),
                linear-gradient(180deg, #5a3a1e 0%, #3b2412 100%);">
    {{ $slot }}
</div>
