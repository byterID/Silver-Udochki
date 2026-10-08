<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Junges\Kafka\Facades\Kafka;
use Tests\TestCase;

final class TaskTest extends TestCase
{
    use RefreshDatabase;   // каждый тест на чистой базе

    public function test_user_can_create_task(): void
    {
        Kafka::fake();     // сообщения не уходят в сеть, а запоминаются

        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('tasks.store'), ['type' => 'demo_report', 'payload' => ['seconds' => 5]])
            ->assertRedirect();

        $this->assertDatabaseHas('tasks', ['user_id' => $user->id, 'status' => 'pending']);
        Kafka::assertPublishedOn('tasks.requested');
    }

    public function test_user_cannot_view_foreign_task(): void
    {
        Kafka::fake();

        [$owner, $stranger] = User::factory()->count(2)->create();
        $task = $owner->tasks()->create(['type' => 'demo_report', 'payload' => ['seconds' => 5]]);

        $this->actingAs($stranger)->get(route('tasks.show', $task))->assertForbidden();
    }
}
