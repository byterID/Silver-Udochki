<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Колокольчик: очистка прочитанных и автоудаление старых уведомлений. */
class NotificationTest extends TestCase
{
    use RefreshDatabase;

    private function notification(User $user, bool $read, ?Carbon $at = null): string
    {
        $id = (string) Str::uuid();
        $at ??= now();

        DB::table('notifications')->insert([
            'id' => $id,
            'type' => 'App\Notifications\TaskFinished',
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'data' => json_encode(['status' => 'done']),
            'read_at' => $read ? $at : null,
            'created_at' => $at,
            'updated_at' => $at,
        ]);

        return $id;
    }

    public function test_очистить_удаляет_только_свои_прочитанные(): void
    {
        [$user, $other] = User::factory()->count(2)->create();
        $read = $this->notification($user, read: true);
        $unread = $this->notification($user, read: false);
        $foreign = $this->notification($other, read: true);

        $this->actingAs($user)->postJson(route('notifications.clear-read'))->assertOk();

        $this->assertDatabaseMissing('notifications', ['id' => $read]);
        $this->assertDatabaseHas('notifications', ['id' => $unread]);
        $this->assertDatabaseHas('notifications', ['id' => $foreign]);

        $this->actingAs($user)->getJson(route('notifications.index'))
            ->assertJsonPath('unread', 1)
            ->assertJsonCount(1, 'items');
    }

    public function test_гостю_очистка_недоступна(): void
    {
        $this->postJson(route('notifications.clear-read'))->assertUnauthorized();
    }

    public function test_уведомления_старше_недели_удаляются(): void
    {
        config(['tasks.notifications_ttl_days' => 7]);
        $user = User::factory()->create();
        $oldRead = $this->notification($user, read: true, at: now()->subDays(8));
        $oldUnread = $this->notification($user, read: false, at: now()->subDays(8));
        $fresh = $this->notification($user, read: false, at: now()->subDays(6));

        $this->artisan('notifications:prune')->expectsOutput('Удалено уведомлений: 2')->assertSuccessful();

        $this->assertDatabaseMissing('notifications', ['id' => $oldRead]);
        $this->assertDatabaseMissing('notifications', ['id' => $oldUnread]);
        $this->assertDatabaseHas('notifications', ['id' => $fresh]);
    }
}
