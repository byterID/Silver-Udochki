@extends('control-panel.layout')

@section('panel-content')
    <div x-data="{
        showModal: false,
        mode: 'create',
        form: { id: null, name: '', title: '', action_group_id: '' },
        openCreate() {
            this.mode = 'create';
            this.form = { id: null, name: '', title: '', action_group_id: '' };
            this.showModal = true;
        },
        openEdit(perm) {
            this.mode = 'edit';
            this.form = { id: perm.id, name: perm.name, title: perm.title ?? '', action_group_id: perm.action_group_id ?? '' };
            this.showModal = true;
        }
     }">

        <div class="mb-6 flex items-center justify-between">
            <h1 class="text-2xl font-semibold text-gray-800">Действия в системе</h1>
            <span class="rounded-full bg-indigo-100 px-3 py-1 text-sm text-indigo-700">
                Всего: {{ $permissions->count() }}
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
                    <th class="px-4 py-3 text-left font-semibold text-gray-600 w-24">ID</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Название</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Код действия</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Группа</th>
                </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                @forelse ($permissions as $permission)
                    <tr class="cursor-pointer hover:bg-gray-50"
                        @click="openEdit({
                            id: {{ $permission->id }},
                            name: @js($permission->name),
                            title: @js($permission->title),
                            action_group_id: {{ $permission->action_group_id ?? 'null' }}
                        })">
                        <td class="px-4 py-3 font-mono text-gray-500">{{ $permission->id }}</td>
                        <td class="px-4 py-3 font-medium text-gray-800">{{ $permission->title ?? '—' }}</td>
                        <td class="px-4 py-3 font-mono text-xs text-gray-400">{{ $permission->name }}</td>
                        <td class="px-4 py-3 text-gray-600">{{ $permission->actionGroup?->name ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="2" class="px-4 py-8 text-center text-gray-400">Действий пока нет</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>

        {{-- Единая модалка создания / редактирования --}}
        <div x-show="showModal" x-cloak
             class="fixed inset-0 z-50 flex items-center justify-center p-4" x-transition.opacity>
            <div class="absolute inset-0 bg-black/50" @click="showModal = false"></div>
            <div class="relative w-full max-w-sm rounded-xl bg-white p-6 shadow-xl"
                 @keydown.escape.window="showModal = false" x-transition>
                <div class="mb-5 flex items-center justify-between">
                    <h2 class="text-xl font-semibold text-gray-800"
                        x-text="mode === 'create' ? 'Новое действие' : 'Редактирование действия'"></h2>
                    <button type="button" @click="showModal = false" class="text-gray-400 hover:text-gray-600">✕</button>
                </div>

                <form :action="'{{ route('control-panel.permissions.update', ['permission' => '__ID__']) }}'.replace('__ID__', form.id)"
                      method="POST" class="flex flex-col gap-4">
                    @csrf
                    @method('PUT')

                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Название</label>
                        <input type="text" name="title" x-model="form.title" required
                               placeholder="например: Доступ к панели управления"
                               class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-400 focus:ring-indigo-400">
                        @error('title') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Код действия</label>
                        <input type="text" x-model="form.name" readonly disabled
                               class="w-full rounded-md border-gray-200 bg-gray-100 font-mono text-sm text-gray-500">
                        <p class="mt-1 text-xs text-gray-400">Задаётся в коде системы и не меняется через панель.</p>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Группа действий</label>
                        <select name="action_group_id" x-model="form.action_group_id"
                                class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-400 focus:ring-indigo-400">
                            <option value="">— без группы —</option>
                            @foreach ($groups as $group)
                                <option value="{{ $group->id }}">{{ $group->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="flex items-center justify-end gap-3 pt-2">
                        <button type="button" @click="showModal = false" class="text-sm text-gray-500 hover:text-gray-700">Отмена</button>
                        <button type="submit" class="rounded-lg bg-indigo-600 px-5 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-700"
                                x-text="mode === 'create' ? 'Создать' : 'Сохранить'"></button>
                    </div>
                </form>
            </div>
        </div>

    </div>
@endsection
