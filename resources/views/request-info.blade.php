<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Диагностика запроса
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            <p class="text-sm text-gray-600 px-4 sm:px-0">
                Путь запроса: браузер → nginx (расшифровывает HTTPS) → PHP-FPM (Laravel).
                Ниже ваш собственный запрос в том виде, в каком он дошёл до PHP.
            </p>

            {{-- 1. Сводка --}}
            <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                <h3 class="px-6 pt-6 pb-2 font-semibold text-gray-800">Сводка</h3>
                <table class="w-full text-sm">
                    <tbody>
                        @foreach ($summary as $row)
                            <tr class="border-t border-gray-100">
                                <td class="px-6 py-3 text-gray-500 w-1/4">{{ $row['label'] }}</td>
                                <td class="px-6 py-3 font-mono text-gray-900 break-all w-1/4">{{ $row['value'] }}</td>
                                <td class="px-6 py-3 text-gray-500">{{ $row['hint'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- 2. Прокси-заголовки --}}
            <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                <h3 class="px-6 pt-6 pb-2 font-semibold text-gray-800">Прокси-заголовки</h3>
                <p class="px-6 pb-2 text-xs text-gray-500">
                    Перед nginx нет прокси, поэтому здесь должно быть пусто. Если значение есть,
                    его прислал сам клиент, и Laravel его игнорирует.
                </p>
                <table class="w-full text-sm">
                    <tbody>
                        @foreach ($proxyHeaders as $name => $header)
                            <tr class="border-t border-gray-100">
                                <td class="px-6 py-3 font-mono text-gray-500 w-1/4">{{ $name }}</td>
                                <td class="px-6 py-3 font-mono break-all w-1/4 {{ $header['value'] ? 'text-amber-700' : 'text-gray-400' }}">
                                    {{ $header['value'] ?? '—' }}
                                </td>
                                <td class="px-6 py-3 text-gray-500">{{ $header['description'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- 3. Все заголовки --}}
            <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                <h3 class="px-6 pt-6 pb-2 font-semibold text-gray-800">
                    Все заголовки запроса ({{ $headers->count() }})
                </h3>
                <p class="px-6 pb-2 text-xs text-gray-500">
                    Прислал браузер, nginx передал в PHP. Заголовки с «_» в имени nginx по умолчанию отбрасывает.
                </p>
                <table class="w-full text-sm">
                    <tbody>
                        @foreach ($headers as $name => $value)
                            <tr class="border-t border-gray-100">
                                <td class="px-6 py-3 font-mono text-gray-500 w-1/4">{{ $name }}</td>
                                <td class="px-6 py-3 font-mono text-gray-900 break-all">{{ $value }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- 4. Переменные nginx --}}
            <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                <h3 class="px-6 pt-6 pb-2 font-semibold text-gray-800">Что nginx добавил от себя</h3>
                <p class="px-6 pb-2 text-xs text-gray-500">
                    Этого нет в заголовках браузера: nginx вычисляет эти значения сам и передаёт через FastCGI.
                </p>
                <table class="w-full text-sm">
                    <tbody>
                        @foreach ($nginxParams as $key => $param)
                            <tr class="border-t border-gray-100">
                                <td class="px-6 py-3 font-mono text-gray-500 w-1/4">{{ $key }}</td>
                                <td class="px-6 py-3 font-mono text-gray-900 break-all w-1/4">{{ $param['value'] }}</td>
                                <td class="px-6 py-3 text-gray-500">{{ $param['description'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

        </div>
    </div>
</x-app-layout>
