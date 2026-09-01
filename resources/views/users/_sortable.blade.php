@php
    $currentSort = request('sort', 'name');
    $currentDir  = request('direction', 'asc');
    // Если кликаем по активной колонке — переключаем направление
    $newDir = ($currentSort === $field && $currentDir === 'asc') ? 'desc' : 'asc';
    $arrow  = $currentSort === $field ? ($currentDir === 'asc' ? '▲' : '▼') : '';
@endphp

<th class="px-4 py-3 text-left font-semibold text-gray-600">
    <a href="{{ request()->fullUrlWithQuery(['sort' => $field, 'direction' => $newDir]) }}"
       class="inline-flex items-center gap-1 hover:text-indigo-600">
        {{ $label }}
        <span class="text-xs text-indigo-500">{{ $arrow }}</span>
    </a>
</th>
