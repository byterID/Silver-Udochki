<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\TaskStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;

final class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $items = $user->notifications()
            ->latest()
            ->limit(10)
            ->get()
            ->map(fn (DatabaseNotification $n): array => [
                'id' => $n->id,
                'read_at' => $n->read_at?->toIso8601String(),
                'created_at' => $n->created_at->toIso8601String(),
                'message' => $this->message($n->data),
                'url' => $this->taskUrl($n->data),
            ]);

        return response()->json([
            'unread' => $user->unreadNotifications()->count(),
            // Колокольчик опрашивает чаще, пока есть задачи без уведомления. Окно в час —
            // чтобы потерянная нотификатором задача не держала частый опрос вечно
            'pending_tasks' => $user->tasks()
                ->whereNull('notified_at')
                ->where('created_at', '>', now()->subHour())
                ->exists(),
            'items' => $items,
        ]);
    }

    /** @param array<string, mixed> $data */
    private function message(array $data): string
    {
        return match (TaskStatus::tryFrom((string) ($data['status'] ?? ''))) {
            TaskStatus::Done => 'Задача выполнена, результат готов',
            TaskStatus::Failed => 'Задача завершилась с ошибкой',
            default => 'Статус задачи изменился',
        };
    }

    /** @param array<string, mixed> $data */
    private function taskUrl(array $data): ?string
    {
        $id = $data['task_id'] ?? null;

        return is_string($id) && Str::isUuid($id) ? route('tasks.show', $id) : null;
    }

    public function read(Request $request, string $id): JsonResponse
    {
        abort_unless(Str::isUuid($id), 404);

        $request->user()->notifications()->whereKey($id)->firstOrFail()->markAsRead();

        return response()->json(['ok' => true]);
    }

    public function readAll(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return response()->json(['ok' => true]);
    }

    public function clearRead(Request $request): JsonResponse
    {
        $request->user()->readNotifications()->delete();

        return response()->json(['ok' => true]);
    }
}
