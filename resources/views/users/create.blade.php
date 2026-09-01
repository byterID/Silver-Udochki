@extends('control-panel.layout')

@section('panel-content')
    <div class="mb-6 flex items-center gap-3">
        <a href="{{ route('control-panel.staff') }}"
           class="text-gray-400 hover:text-gray-600">← Назад</a>
        <h1 class="text-2xl font-semibold text-gray-800">Новый сотрудник</h1>
    </div>

    <div class="max-w-lg rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
        <form method="POST" action="{{ route('control-panel.staff.store') }}" class="flex flex-col gap-5">
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
                        <option value="{{ $role->name }}" @selected(old('role') === $role->name)>
                            {{ $role->name }}
                        </option>
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

            <div class="flex items-center gap-3 pt-2">
                <button type="submit"
                        class="rounded-lg bg-indigo-600 px-5 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-700">
                    Создать
                </button>
                <a href="{{ route('control-panel.staff') }}"
                   class="text-sm text-gray-500 hover:text-gray-700">Отмена</a>
            </div>
        </form>
    </div>
@endsection
