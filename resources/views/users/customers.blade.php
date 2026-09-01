@extends('control-panel.layout')

@section('panel-content')
    <div class="mb-6 flex items-center justify-between">
        <h1 class="text-2xl font-semibold text-gray-800">Покупатели</h1>
        <span class="rounded-full bg-indigo-100 px-3 py-1 text-sm text-indigo-700">
            Всего: {{ $users->total() }}
        </span>
    </div>

    @if (session('status'))
        <div class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-green-700">
            {{ session('status') }}
        </div>
    @endif

    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50">
            <tr>
                @include('users._sortable', ['label' => 'Имя', 'field' => 'name'])
                @include('users._sortable', ['label' => 'Email', 'field' => 'email'])
                @include('users._sortable', ['label' => 'Дата регистрации', 'field' => 'created_at'])
            </tr>
            <tr class="bg-white">
                <form method="GET" action="{{ route('control-panel.customers') }}">
                    <th class="px-4 py-2">
                        <input type="text" name="name" value="{{ request('name') }}"
                               placeholder="Поиск по имени"
                               class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-400 focus:ring-indigo-400">
                    </th>
                    <th class="px-4 py-2">
                        <input type="text" name="email" value="{{ request('email') }}"
                               placeholder="Поиск по email"
                               class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-400 focus:ring-indigo-400">
                    </th>
                    <th class="px-4 py-2">
                        <button type="submit"
                                class="rounded-md bg-indigo-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-indigo-700">
                            Найти
                        </button>
                        <a href="{{ route('control-panel.customers') }}"
                           class="ml-1 text-sm text-gray-500 hover:text-gray-700">Сброс</a>
                    </th>
                </form>
            </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
            @forelse ($users as $user)
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 font-medium text-gray-800">{{ $user->name }}</td>
                    <td class="px-4 py-3 text-gray-600">{{ $user->email }}</td>
                    <td class="px-4 py-3 text-gray-600">{{ $user->created_at?->format('d.m.Y') }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="3" class="px-4 py-8 text-center text-gray-400">Ничего не найдено</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $users->links() }}
    </div>
@endsection
