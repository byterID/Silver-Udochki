<div x-show="showCreate" x-cloak
     class="fixed inset-0 z-50 flex items-center justify-center p-4" x-transition.opacity>
    <div class="absolute inset-0 bg-black/50" @click="showCreate = false"></div>
    <div class="relative w-full max-w-lg rounded-xl bg-white p-6 shadow-xl"
         @keydown.escape.window="showCreate = false" x-transition>
        <div class="mb-5 flex items-center justify-between">
            <h2 class="text-xl font-semibold text-gray-800">Новый сотрудник</h2>
            <button type="button" @click="showCreate = false" class="text-gray-400 hover:text-gray-600">✕</button>
        </div>

        <form method="POST" action="{{ route('control-panel.users.store') }}" class="flex flex-col gap-4">
            @csrf
            <div>
                <label class="mb-1 block text-sm font-medium text-gray-700">Имя</label>
                <input type="text" name="name" value="{{ old('name') }}" required
                       class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-400 focus:ring-indigo-400">
                @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-gray-700">Email</label>
                <input type="email" name="email" value="{{ old('email') }}" required
                       class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-400 focus:ring-indigo-400">
                @error('email') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-gray-700">Роль</label>
                <select name="role" required
                        class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-400 focus:ring-indigo-400">
                    @foreach ($roles as $role)
                        <option value="{{ $role->name }}" @selected(old('role') === $role->name)>{{ $role->name }}</option>
                    @endforeach
                </select>
                @error('role') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-gray-700">Пароль</label>
                <input type="password" name="password" required
                       class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-400 focus:ring-indigo-400">
                @error('password') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-gray-700">Повтор пароля</label>
                <input type="password" name="password_confirmation" required
                       class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-400 focus:ring-indigo-400">
            </div>
            <div class="flex items-center justify-end gap-3 pt-2">
                <button type="button" @click="showCreate = false" class="text-sm text-gray-500 hover:text-gray-700">Отмена</button>
                <button type="submit" class="rounded-lg bg-indigo-600 px-5 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-700">Создать</button>
            </div>
        </form>
    </div>
</div>
