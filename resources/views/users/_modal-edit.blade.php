<div x-show="showEdit" x-cloak
     class="fixed inset-0 z-50 flex items-center justify-center p-4" x-transition.opacity>
    <div class="absolute inset-0 bg-black/50" @click="showEdit = false"></div>

    <div class="relative w-full max-w-lg rounded-xl bg-white p-6 shadow-xl"
         @keydown.escape.window="showEdit = false" x-transition>
        <div class="mb-5 flex items-center justify-between">
            <h2 class="text-xl font-semibold text-gray-800">Редактирование пользователя</h2>
            <button type="button" @click="showEdit = false" class="text-gray-400 hover:text-gray-600">✕</button>
        </div>

        {{-- Форма изменения --}}
        <form :action="'/control-panel/users/' + editUser.id" method="POST" class="flex flex-col gap-4">
            @csrf
            @method('PUT')
            <div>
                <label class="mb-1 block text-sm font-medium text-gray-700">Имя</label>
                <input type="text" name="name" x-model="editUser.name" required
                       class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-400 focus:ring-indigo-400">
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-gray-700">Email</label>
                <input type="email" name="email" x-model="editUser.email" required
                       class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-400 focus:ring-indigo-400">
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-gray-700">Роль</label>
                <select name="role" x-model="editUser.role" required
                        class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-400 focus:ring-indigo-400">
                    @foreach ($roles as $role)
                        <option value="{{ $role->name }}">{{ $role->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-center justify-end gap-3 pt-2">
                <button type="button" @click="showEdit = false" class="text-sm text-gray-500 hover:text-gray-700">Отмена</button>
                <button type="submit" class="rounded-lg bg-indigo-600 px-5 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-700">Сохранить</button>
            </div>
        </form>

        {{-- Блок удаления --}}
        <div class="mt-6 border-t border-gray-200 pt-5">
            <template x-if="!confirmingDelete">
                <button type="button" @click="confirmingDelete = true"
                        class="text-sm font-medium text-red-600 hover:text-red-700">
                    Удалить пользователя
                </button>
            </template>

            <template x-if="confirmingDelete">
                <form :action="'/control-panel/users/' + editUser.id" method="POST" class="flex flex-col gap-3">
                    @csrf
                    @method('DELETE')
                    <p class="text-sm text-gray-600">
                        Для подтверждения введите email пользователя
                        (<span class="font-medium" x-text="editUser.email"></span>):
                    </p>
                    <input type="text" name="email_confirmation" placeholder="Повторите email"
                           class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-red-400 focus:ring-red-400">
                    @error('email_confirmation') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                    <div class="flex items-center gap-3">
                        <button type="submit" class="rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700">
                            Удалить навсегда
                        </button>
                        <button type="button" @click="confirmingDelete = false" class="text-sm text-gray-500 hover:text-gray-700">Отмена</button>
                    </div>
                </form>
            </template>
        </div>
    </div>
</div>
