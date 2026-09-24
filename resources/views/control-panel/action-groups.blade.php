@extends('control-panel.layout')

@section('panel-content')
    <div x-data="{
        showModal: false,
        mode: 'create',
        form: { id: null, name: '' },
        openCreate() {
            this.mode = 'create';
            this.form = { id: null, name: '' };
            this.showModal = true;
        },
        openEdit(group) {
            this.mode = 'edit';
            this.form = { id: group.id, name: group.name };
            this.showModal = true;
        }
     }">

        <div class="mb-6 flex items-center justify-between">
            <h1 class="text-2xl font-semibold text-gray-800">Группы действий</h1>
            <div class="flex items-center gap-3">
                <span class="rounded-full bg-indigo-100 px-3 py-1 text-sm text-indigo-700">
                    Всего: {{ $groups->count() }}
                </span>
                <button type="button" @click="openCreate()"
                        class="inline-flex items-center gap-1.5 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-indigo-700">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    Создать группу действий
                </button>
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
                    <th class="px-4 py-3 text-left font-semibold text-gray-600 w-24">ID</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Название группы</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Действий в группе</th>
                </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                @forelse ($groups as $group)
                    <tr class="cursor-pointer hover:bg-gray-50"
                        @click="openEdit({ id: {{ $group->id }}, name: @js($group->name) })">
                        <td class="px-4 py-3 font-mono text-gray-500">{{ $group->id }}</td>
                        <td class="px-4 py-3 font-medium text-gray-800">{{ $group->name }}</td>
                        <td class="px-4 py-3">
                                <span class="rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-700">
                                    {{ $group->permissions_count }}
                                </span>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="px-4 py-8 text-center text-gray-400">Групп пока нет</td></tr>
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
                        x-text="mode === 'create' ? 'Новая группа действий' : 'Редактирование группы'"></h2>
                    <button type="button" @click="showModal = false" class="text-gray-400 hover:text-gray-600">✕</button>
                </div>

                <form :action="mode === 'create' ? '{{ route('control-panel.action-groups.store') }}' : '/control-panel/action-groups/' + form.id"
                      method="POST" class="flex flex-col gap-4">
                    @csrf
                    <template x-if="mode === 'edit'">
                        <input type="hidden" name="_method" value="PUT">
                    </template>

                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Название группы</label>
                        <input type="text" name="name" x-model="form.name" required
                               placeholder="например: Управление заказами"
                               class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-400 focus:ring-indigo-400">
                        @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
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
