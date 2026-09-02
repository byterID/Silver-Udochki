@extends('control-panel.layout')

@section('panel-content')
    <div x-data="{
    showModal: false,
    role: { id: null, name: '', permissions: [] },
    groupedPermissions: {{ Illuminate\Support\Js::from($groups) }},
    superRole: @js(config('access.super_role')),
    lockedNames: @js(config('access.locked_permissions')),
    openRole(role) {
        this.role = {
            id: role.id,
            name: role.name,
            permissions: [...role.permissions],
        };
        this.showModal = true;
    },
    has(permId) {
        return this.role.permissions.includes(permId);
    },
    isLocked(perm) {
        return this.role.name === this.superRole && this.lockedNames.includes(perm.name);
    },
    toggle(permId) {
        const perm = this.groupedPermissions.find(p => p.id === permId);
        if (this.isLocked(perm)) return;
        if (this.has(permId)) {
            this.role.permissions = this.role.permissions.filter(id => id !== permId);
        } else {
            this.role.permissions.push(permId);
        }
    }
}">

        <div class="mb-6 flex items-center justify-between">
            <h1 class="text-2xl font-semibold text-gray-800">Управление доступом</h1>
            <span class="rounded-full bg-indigo-100 px-3 py-1 text-sm text-indigo-700">
                Групп: {{ $roles->count() }}
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
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Группа</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Действий разрешено</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Группа</th>
                </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                @forelse ($roles as $role)
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
                    <tr><td colspan="3" class="px-4 py-8 text-center text-gray-400">Групп пока нет</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>

        {{-- Модалка с ползунками действий --}}
        <div x-show="showModal" x-cloak
             class="fixed inset-0 z-50 flex items-center justify-center p-4" x-transition.opacity>
            <div class="absolute inset-0 bg-black/50" @click="showModal = false"></div>
            <div class="relative w-full max-w-lg rounded-xl bg-white p-6 shadow-xl"
                 @keydown.escape.window="showModal = false" x-transition>
                <div class="mb-5 flex items-center justify-between">
                    <h2 class="text-xl font-semibold text-gray-800">
                        Доступ группы «<span x-text="role.name"></span>»
                    </h2>
                    <button type="button" @click="showModal = false" class="text-gray-400 hover:text-gray-600">✕</button>
                </div>

                <form :action="'/control-panel/access-control/' + role.id" method="POST" class="flex flex-col gap-4">
                    @csrf
                    @method('PUT')

                    <div class="max-h-80 overflow-y-auto pr-1 flex flex-col gap-1">
                        <template x-for="permId in role.permissions" :key="'inp-' + permId">
                            <input type="hidden" name="permissions[]" :value="permId">
                        </template>

                        {{-- Действия, сгруппированные по заголовкам --}}
                        <template x-for="group in groupedPermissions" :key="group.name">
                            <div class="mb-3">
                                <h3 class="mb-1 px-1 text-xs font-semibold uppercase tracking-wider text-gray-400"
                                    x-text="group.name"></h3>

                                <template x-for="perm in group.permissions" :key="perm.id">
                                    <div class="flex items-center justify-between rounded-lg px-3 py-2.5 transition"
                                         :class="isLocked(perm) ? 'opacity-70' : 'hover:bg-gray-50'">
                <span class="text-sm font-medium text-gray-700 flex items-center gap-2">
                    <span x-text="perm.name"></span>
                    <template x-if="isLocked(perm)">
                        <span class="rounded bg-amber-100 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-amber-700">
                            обязательно
                        </span>
                    </template>
                </span>

                                        <button type="button" @click="toggle(perm.id)"
                                                :disabled="isLocked(perm)"
                                                role="switch" :aria-checked="has(perm.id)"
                                                class="relative inline-flex h-6 w-11 shrink-0 items-center rounded-full transition-colors focus:outline-none disabled:cursor-not-allowed"
                                                :class="has(perm.id) ? 'bg-indigo-600' : 'bg-gray-300'">
                    <span class="inline-block h-4 w-4 rounded-full bg-white shadow transition-transform"
                          :class="has(perm.id) ? 'translate-x-6' : 'translate-x-1'"></span>
                                        </button>
                                    </div>
                                </template>
                            </div>
                        </template>

                        <template x-if="groupedPermissions.length === 0">
                            <p class="px-3 py-4 text-center text-sm text-gray-400">
                                Сначала создайте действия в разделе «Действия в системе»
                            </p>
                        </template>
                    </div>

                    <div class="flex items-center justify-end gap-3 border-t border-gray-200 pt-4">
                        <button type="button" @click="showModal = false" class="text-sm text-gray-500 hover:text-gray-700">Отмена</button>
                        <button type="submit" class="rounded-lg bg-indigo-600 px-5 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-700">
                            Сохранить доступ
                        </button>
                    </div>
                </form>
            </div>
        </div>

    </div>
@endsection
