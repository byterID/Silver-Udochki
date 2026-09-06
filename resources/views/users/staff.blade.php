@extends('control-panel.layout')

@section('panel-content')
    <div x-data="{
        showCreate: false,
        showEdit: false,
        confirmingDelete: false,
        editUser: { id: null, name: '', email: '', role: '' },
        openEdit(user) {
            this.editUser = user;
            this.confirmingDelete = false;
            this.showEdit = true;
        }
     }">

        <div class="mb-6 flex items-center justify-between">
            <h1 class="text-2xl font-semibold text-gray-800">Сотрудники</h1>
            <div class="flex items-center gap-3">
            <span class="rounded-full bg-indigo-100 px-3 py-1 text-sm text-indigo-700">
                Всего: {{ $users->total() }}
            </span>
                @can('create', App\Models\User::class)
                <button type="button" @click="showCreate = true"
                        class="inline-flex items-center gap-1.5 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-indigo-700">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    Создать пользователя
                </button>
                @endcan
            </div>
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
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Роль</th>
                </tr>
                <tr class="bg-white">
                    <form method="GET" action="{{ route('control-panel.staff') }}">
                        <th class="px-4 py-2">
                            <input type="text" name="name" value="{{ request('name') }}" placeholder="Поиск по имени"
                                   class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-400 focus:ring-indigo-400">
                        </th>
                        <th class="px-4 py-2">
                            <input type="text" name="email" value="{{ request('email') }}" placeholder="Поиск по email"
                                   class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-400 focus:ring-indigo-400">
                        </th>
                        <th class="px-4 py-2 flex items-center gap-2">
                            <input type="text" name="role" value="{{ request('role') }}" placeholder="Поиск по роли"
                                   class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-400 focus:ring-indigo-400">
                            <button type="submit" class="rounded-md bg-indigo-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-indigo-700">Найти</button>
                        </th>
                    </form>
                </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                @forelse ($users as $user)
                    @can('update', $user)
                    <tr class="cursor-pointer hover:bg-gray-50"
                        @click="openEdit({
                            id: {{ $user->id }},
                            name: @js($user->name),
                            email: @js($user->email),
                            role: @js($user->getRoleNames()->first())
                        })">
                        <td class="px-4 py-3 font-medium text-gray-800">{{ $user->name }}</td>
                        <td class="px-4 py-3 text-gray-600">{{ $user->email }}</td>
                        <td class="px-4 py-3">
                            <span class="rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-700">
                                {{ $user->getRoleNames()->first() ?? 'нет роли' }}
                            </span>
                        </td>
                    </tr>
                    @endcan
                @empty
                    <tr><td colspan="3" class="px-4 py-8 text-center text-gray-400">Ничего не найдено</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $users->links() }}</div>

        {{-- Модалки внутри общего x-data --}}
        @include('users._modal-create')
        @include('users._modal-edit')

    </div>
@endsection
