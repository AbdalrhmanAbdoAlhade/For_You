<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NotificationReadAllTest extends TestCase
{
    use RefreshDatabase;

    public function test_mark_all_as_read_updates_only_current_user_unread_notifications(): void
    {
        $user = User::factory()->customer()->create();
        $other = User::factory()->customer()->create();

        $this->notification($user->id, null);
        $this->notification($user->id, null);
        $alreadyRead = $this->notification($user->id, now());
        $otherNotification = $this->notification($other->id, null);

        Sanctum::actingAs($user);

        $this->postJson('/api/notifications/read-all', [], [
            'Accept-Language' => 'ar',
        ])
            ->assertOk()
            ->assertJsonPath('message', 'تم تعليم كل الإشعارات كمقروءة');

        $this->assertSame(0, DB::table('notifications')
            ->where('notifiable_id', $user->id)
            ->whereNull('read_at')
            ->count());

        $this->assertNotNull(DB::table('notifications')->where('id', $alreadyRead)->value('read_at'));
        $this->assertNull(DB::table('notifications')->where('id', $otherNotification)->value('read_at'));
    }

    protected function notification(int $userId, $readAt): string
    {
        $id = (string) Str::uuid();

        DB::table('notifications')->insert([
            'id'              => $id,
            'type'            => 'App\\Notifications\\OrderStatusUpdated',
            'notifiable_type' => User::class,
            'notifiable_id'   => $userId,
            'data'            => json_encode(['title' => 'إشعار', 'message' => 'رسالة']),
            'read_at'         => $readAt,
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        return $id;
    }
}
