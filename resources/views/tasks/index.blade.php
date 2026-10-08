<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold text-gray-800">Мои задачи</h2>
    </x-slot>

    <div class="mx-auto max-w-4xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">
        <form method="POST" action="{{ route('tasks.store') }}"
              class="flex flex-wrap items-end gap-4 rounded-lg bg-white p-6 shadow">
            @csrf
            <input type="hidden" name="type" value="demo_report">

            <label class="block">
                <span class="text-sm text-gray-600">Демо-отчёт, длительность в секундах</span>
                <input type="number" name="payload[seconds]" min="1" max="60"
                       value="{{ old('payload.seconds', 10) }}"
                       class="mt-1 block w-40 rounded-md border-gray-300 shadow-sm focus:border-emerald-600 focus:ring-emerald-600">
                <x-input-error :messages="$errors->get('payload.seconds')" class="mt-1" />
            </label>

            <button type="submit"
                    class="rounded-md bg-emerald-800 px-4 py-2 font-semibold text-amber-50 hover:bg-emerald-700">
                Запустить
            </button>
        </form>

        <div class="overflow-hidden rounded-lg bg-white shadow">
            @forelse ($tasks as $task)
                <a href="{{ route('tasks.show', $task) }}"
                   class="flex items-center justify-between gap-4 border-b px-6 py-4 last:border-0 hover:bg-gray-50">
                    <div>
                        <div class="font-medium text-gray-800">{{ $task->type->label() }}</div>
                        <div class="text-sm text-gray-500">{{ $task->created_at->diffForHumans() }}</div>
                    </div>
                    <span @class([
                        'rounded-full px-3 py-1 text-sm font-semibold',
                        'bg-gray-100 text-gray-700' => $task->status === \App\Enums\TaskStatus::Pending,
                        'bg-amber-100 text-amber-800' => $task->status === \App\Enums\TaskStatus::Processing,
                        'bg-emerald-100 text-emerald-800' => $task->status === \App\Enums\TaskStatus::Done,
                        'bg-red-100 text-red-800' => $task->status === \App\Enums\TaskStatus::Failed,
                    ])>{{ $task->status->label() }}</span>
                </a>
            @empty
                <p class="px-6 py-10 text-center text-gray-500">Задач пока нет</p>
            @endforelse
        </div>

        {{ $tasks->links() }}
    </div>
</x-app-layout>
