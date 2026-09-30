@props(['value' => ''])

<form action="{{ route('catalog.search') }}" method="GET" {{ $attributes->merge(['class' => 'flex max-w-xl gap-2']) }}>
    <label for="q" class="sr-only">Поиск</label>
    <input id="q" name="q" value="{{ $value }}" maxlength="100" placeholder="Что ищем? Например: блесна на щуку"
           class="flex-1 rounded-md border-2 border-amber-900 bg-stone-800 text-amber-50 placeholder-stone-400 focus:border-amber-500 focus:ring-amber-500">
    <x-header-button type="submit">Найти</x-header-button>
</form>
