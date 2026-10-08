<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Http\Requests\StoreTaskRequest;
use App\Http\Resources\TaskResource;
use App\Models\Task;
use App\Services\Tasks\TaskDispatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class TaskController extends Controller
{
    public function index(Request $request): View
    {
        $tasks = $request->user()->tasks()->latest()->paginate(20);

        return view('tasks.index', compact('tasks'));
    }

    public function store(StoreTaskRequest $request, TaskDispatcher $dispatcher): JsonResponse|RedirectResponse
    {
        $task = $dispatcher->dispatch(
            $request->user(),
            TaskType::from($request->validated('type')),
            $request->validated('payload', []),
        );

        if ($request->expectsJson()) {
            return response()->json(['task_id' => $task->id], 202)
                ->header('Location', route('tasks.show', $task));
        }

        return redirect()->route('tasks.show', $task);
    }

    public function show(Request $request, Task $task): View
    {
        return view('tasks.show', [
            'task' => $task,
            'initial' => TaskResource::make($task)->resolve($request),
        ]);
    }

    public function status(Request $request, Task $task): JsonResponse
    {
        return response()->json(TaskResource::make($task)->resolve($request));
    }

    public function download(Task $task): StreamedResponse
    {
        abort_unless($task->status === TaskStatus::Done, 404);              // результата не было и нет

        $disk = Storage::disk('local');
        abort_if($task->result_path === null, 410, 'Срок хранения файла истёк'); // был, но удалён по сроку

        $filename = sprintf('%s_%s.csv', $task->type->value, $task->created_at->format('Y-m-d_His'));

        return $disk->download($task->result_path, $filename);
    }
}
