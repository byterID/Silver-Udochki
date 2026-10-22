<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Models\Task;
use App\Models\User;
use App\Services\Tasks\TaskNotifier;
use App\Services\Tasks\TaskProcessor;
use App\Tasks\DemoReportRunner;
use App\Tasks\TaskRunner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Storage;
use Junges\Kafka\Facades\Kafka;
use Junges\Kafka\Message\ConsumedMessage;
use Junges\Kafka\Message\Message;
use RuntimeException;
use Tests\TestCase;
use Throwable;

// Конвейер задач без настоящей Kafka: события ловит Kafka::fake(), сообщения воркеру подаём сами
class TaskPipelineTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'tasks.max_attempts' => 3,
            'tasks.stale_after_minutes' => 10,
            'tasks.publish_grace_seconds' => 60,
            'tasks.pending_timeout_minutes' => 60,
            'tasks.result_ttl_minutes' => 5,
        ]);
        Kafka::fake();
        Storage::fake('local');
        $this->user = User::factory()->create();
    }

    /** @param array<string, mixed> $columns */
    private function task(TaskStatus $status = TaskStatus::Pending, array $columns = []): Task
    {
        $task = $this->user->tasks()->create(['type' => TaskType::DemoReport, 'payload' => ['seconds' => 1]]);
        $task->forceFill(['status' => $status, ...$columns])->save();

        return $task;
    }

    /** @param array<string, mixed> $body */
    private function message(array $body, string $topic = 'tasks.requested'): ConsumedMessage
    {
        return new ConsumedMessage($topic, 0, [], $body, (string) $this->user->id, 42, null);
    }

    private function kafkaIsDown(): void
    {
        Exceptions::fake();
        Kafka::swap(new class
        {
            public function publish(): never
            {
                throw new RuntimeException('Брокер недоступен');
            }
        });
    }

    private function fakeRunner(?Throwable $failure = null): CountingRunner
    {
        $runner = new CountingRunner($failure);
        $this->app->instance(DemoReportRunner::class, $runner);

        return $runner;
    }

    private function publishedOn(string $topic, string $taskId, ?string $status = null): void
    {
        Kafka::assertPublishedOn($topic, null, fn (Message $m) => $m->getBody()['task_id'] === $taskId
            && $m->getKey() === (string) $this->user->id
            && ($status === null || $m->getBody()['status'] === $status));
    }

    public function test_задача_записывается_в_бд_и_уходит_в_kafka(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson(route('tasks.store'), ['type' => 'demo_report', 'payload' => ['seconds' => 5]])
            ->assertStatus(202);

        $task = Task::query()->sole();
        $this->assertSame($task->id, $response->json('task_id'));
        $this->assertSame(route('tasks.show', $task), $response->headers->get('Location'));
        $this->assertSame(TaskStatus::Pending, $task->status);
        $this->assertNotNull($task->published_at);
        $this->publishedOn('tasks.requested', $task->id);
    }

    public function test_без_kafka_задача_принимается_и_досылается_сверкой(): void
    {
        $fake = Kafka::getFacadeRoot();
        $this->kafkaIsDown();

        $this->actingAs($this->user)
            ->post(route('tasks.store'), ['type' => 'demo_report', 'payload' => ['seconds' => 5]])
            ->assertRedirect(route('tasks.show', Task::query()->sole()));

        $task = Task::query()->sole();
        $this->assertNull($task->published_at);
        Exceptions::assertReported(RuntimeException::class);

        // Kafka поднялась, а веб-запрос так и не дослал событие
        Kafka::swap($fake);
        $this->travel(2)->minutes();
        $this->artisan('tasks:reconcile')->assertSuccessful();

        $this->assertNotNull($task->fresh()?->published_at);
        $this->publishedOn('tasks.requested', $task->id);
    }

    public function test_воркер_пишет_отчёт_и_сообщает_о_завершении(): void
    {
        $task = $this->task();

        app(TaskProcessor::class)->handle($this->message(['task_id' => $task->id]));

        $task->refresh();
        $this->assertSame(TaskStatus::Done, $task->status);
        $this->assertSame(1, $task->attempts);
        $this->assertNotNull($task->completion_published_at);
        $this->publishedOn('tasks.completed', $task->id, 'done');

        $csv = Storage::disk('local')->get((string) $task->result_path);
        $this->assertStringStartsWith("\xEF\xBB\xBF".'Задача;Время;Этап', $csv);
        $this->assertStringContainsString('Задача создана', $csv);
        $this->assertStringContainsString('Шаг 1 из 1', $csv);
    }

    public function test_повтор_сообщения_не_запускает_задачу_второй_раз(): void
    {
        $runner = $this->fakeRunner();
        $task = $this->task();

        app(TaskProcessor::class)->handle($this->message(['task_id' => $task->id]));
        app(TaskProcessor::class)->handle($this->message(['task_id' => $task->id]));

        $this->assertSame(1, $runner->runs);
        $this->assertSame(1, $task->fresh()?->attempts);
        Kafka::assertPublishedOnTimes('tasks.completed', 1);
    }

    public function test_ошибка_раннера_завершает_задачу_с_ошибкой(): void
    {
        Exceptions::fake();
        $this->fakeRunner(new RuntimeException('Диск переполнен'));
        $task = $this->task();

        app(TaskProcessor::class)->handle($this->message(['task_id' => $task->id]));

        $task->refresh();
        $this->assertSame(TaskStatus::Failed, $task->status);
        $this->assertSame('Диск переполнен', $task->error);
        $this->assertNull($task->result_path);
        Exceptions::assertReported(fn (RuntimeException $e) => $e->getMessage() === 'Диск переполнен');
        $this->publishedOn('tasks.completed', $task->id, 'failed');
    }

    public function test_битое_сообщение_уходит_в_dlq(): void
    {
        $runner = $this->fakeRunner();

        app(TaskProcessor::class)->handle($this->message(['task_id' => 'не-uuid']));

        $this->assertSame(0, $runner->runs);
        Kafka::assertPublishedOn('tasks.requested.dlq', null, fn (Message $m) => $m->getBody()['source_offset'] === 42);
    }

    public function test_уведомление_о_завершении_приходит_один_раз(): void
    {
        $done = $this->task(TaskStatus::Done, ['finished_at' => now()]);
        $pending = $this->task();
        $notifier = app(TaskNotifier::class);

        $notifier->handle($this->message(['task_id' => $done->id, 'status' => 'done'], 'tasks.completed'));
        $notifier->handle($this->message(['task_id' => $done->id, 'status' => 'done'], 'tasks.completed'));
        $notifier->handle($this->message(['task_id' => $pending->id], 'tasks.completed'));

        $this->assertSame(1, $this->user->notifications()->count());
        $this->assertSame($done->id, $this->user->notifications()->sole()->data['task_id']);
        $this->assertNotNull($done->fresh()?->notified_at);
        $this->assertNull($pending->fresh()?->notified_at);

        $this->actingAs($this->user)->getJson(route('notifications.index'))
            ->assertJsonPath('unread', 1)
            ->assertJsonPath('items.0.message', 'Задача выполнена, результат готов')
            ->assertJsonPath('items.0.url', route('tasks.show', $done));
    }

    public function test_сверка_разбирает_зависшие_задачи(): void
    {
        $exhausted = $this->task(TaskStatus::Processing, ['attempts' => 3, 'started_at' => now()->subMinutes(20)]);
        $stuck = $this->task(TaskStatus::Processing, [
            'attempts' => 1, 'created_at' => now()->subMinutes(21), 'published_at' => now()->subMinutes(21), 'started_at' => now()->subMinutes(20),
        ]);
        $forgotten = $this->task(TaskStatus::Pending, ['created_at' => now()->subHours(2), 'published_at' => now()->subHours(2)]);
        $silent = $this->task(TaskStatus::Done, ['finished_at' => now()->subMinutes(5)]);
        $healthy = $this->task(TaskStatus::Processing, ['attempts' => 1, 'started_at' => now()->subMinute()]);

        $this->artisan('tasks:reconcile')
            ->expectsOutput('failed=1 requeued=1 expired=1 published=1 republished=0 completed=1')
            ->assertSuccessful();

        $this->assertSame(TaskStatus::Failed, $exhausted->fresh()?->status);
        $this->assertSame(TaskStatus::Failed, $forgotten->fresh()?->status);
        $this->assertSame(TaskStatus::Processing, $healthy->fresh()?->status);

        // Возвращена в очередь и сразу заново опубликована
        $stuck->refresh();
        $this->assertSame(TaskStatus::Pending, $stuck->status);
        $this->assertNull($stuck->started_at);
        $this->publishedOn('tasks.requested', $stuck->id);

        $this->assertNotNull($silent->fresh()?->completion_published_at);
        $this->publishedOn('tasks.completed', $silent->id, 'done');
    }

    public function test_сверка_сообщает_о_недоступной_kafka(): void
    {
        $task = $this->task(TaskStatus::Pending, ['created_at' => now()->subMinutes(5)]);
        $this->kafkaIsDown();

        $this->artisan('tasks:reconcile')->assertFailed();

        $this->assertNull($task->fresh()?->published_at);
    }

    public function test_результат_скачивается_пока_не_истёк_срок(): void
    {
        $this->freezeSecond();
        $task = $this->task(TaskStatus::Done, ['finished_at' => now(), 'result_path' => 'results/r.csv']);
        Storage::disk('local')->put('results/r.csv', 'отчёт');

        $this->actingAs($this->user)->getJson(route('tasks.status', $task))
            ->assertOk()
            ->assertJsonPath('download_url', route('tasks.download', $task))
            ->assertJsonPath('expires_in', 300)
            ->assertJsonPath('expired', false);
        $this->assertSame('отчёт', $this->actingAs($this->user)->get(route('tasks.download', $task))->assertOk()->streamedContent());

        $this->travel(6)->minutes();
        $this->artisan('tasks:prune-results')->expectsOutput('Удалено файлов: 1')->assertSuccessful();

        Storage::disk('local')->assertMissing('results/r.csv');
        $this->actingAs($this->user)->get(route('tasks.download', $task))->assertStatus(410);
        $this->actingAs($this->user)->getJson(route('tasks.status', $task))
            ->assertJsonPath('download_url', null)
            ->assertJsonPath('expired', true);
    }

    public function test_у_незавершённой_задачи_нечего_скачивать(): void
    {
        $this->actingAs($this->user)->get(route('tasks.download', $this->task()))->assertNotFound();
    }

    public function test_чужая_задача_недоступна(): void
    {
        $task = $this->task(TaskStatus::Done, ['finished_at' => now(), 'result_path' => 'results/r.csv']);
        $stranger = User::factory()->create();

        $this->actingAs($stranger)->get(route('tasks.show', $task))->assertForbidden();
        $this->actingAs($stranger)->getJson(route('tasks.status', $task))->assertForbidden();
        $this->actingAs($stranger)->get(route('tasks.download', $task))->assertForbidden();
    }
}

// Подменяет раннер демо-отчёта и считает запуски
final class CountingRunner implements TaskRunner
{
    public int $runs = 0;

    public function __construct(private readonly ?Throwable $failure = null) {}

    public function run(Task $task): string
    {
        $this->runs++;
        if ($this->failure !== null) {
            throw $this->failure;
        }
        Storage::disk('local')->put("results/{$task->id}.csv", 'ok');

        return "results/{$task->id}.csv";
    }
}
