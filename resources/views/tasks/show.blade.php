<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold text-gray-800">{{ $task->type->label() }}</h2>
    </x-slot>

    <div class="mx-auto max-w-3xl px-4 py-8 sm:px-6 lg:px-8"
         x-data="taskStatus(@js($initial), @js(route('tasks.status', $task)))">
        <div class="rounded-lg bg-white p-6 shadow">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <div class="text-sm text-gray-500">Номер тикета</div>
                    <div class="font-mono text-sm text-gray-800" x-text="task.id"></div>
                </div>
                <span class="rounded-full px-3 py-1 text-sm font-semibold"
                      :class="{
                          'bg-gray-100 text-gray-700': task.status === 'pending',
                          'bg-amber-100 text-amber-800': task.status === 'processing',
                          'bg-emerald-100 text-emerald-800': task.status === 'done',
                          'bg-red-100 text-red-800': task.status === 'failed',
                      }"
                      x-text="task.status_label"></span>
            </div>

            <p x-show="!task.finished" class="mt-6 flex items-center gap-2 text-gray-700">
                <svg class="h-4 w-4 animate-spin text-emerald-700" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" class="opacity-25"/>
                    <path d="M4 12a8 8 0 018-8" stroke="currentColor" stroke-width="4" stroke-linecap="round"/>
                </svg>
                Задача выполняется в фоне. Страницу можно закрыть: когда всё будет готово, придёт уведомление.
            </p>

            <p x-show="task.error" x-cloak x-text="task.error" class="mt-6 text-red-700"></p>

            <template x-if="task.download_url">
                <div class="space-y-2">
                    <a :href="task.download_url" class="mt-6 inline-block rounded-md bg-emerald-800 px-4 py-2 font-semibold text-amber-50 hover:bg-emerald-700">Скачать результат</a>
                    <p class="text-sm text-amber-800">
                        Файл хранится на сервере около {{ config('tasks.result_ttl_minutes') }} мин. после завершения.
                        Будет удалён через <span class="font-semibold" x-text="remainingLabel"></span>.
                    </p>
                </div>
            </template>

            <p x-show="task.expired" x-cloak class="text-sm text-stone-600">
                Срок хранения файла истёк, и он удалён с сервера. Запустите задачу заново, чтобы получить новый отчёт.
            </p>

            <p x-show="offline" x-cloak class="mt-4 text-sm text-amber-700">
                Нет связи с сервером, пробую снова…
            </p>
        </div>

        <a href="{{ route('tasks.index') }}" class="mt-4 inline-block text-sm text-emerald-800 hover:underline">
            ← Все задачи
        </a>
    </div>
</x-app-layout>
