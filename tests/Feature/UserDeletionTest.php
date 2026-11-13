<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserDeletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_soft_deleting_user_releases_email_and_phone(): void
    {
        $user = User::factory()->create([
            'email' => 'reusable@example.com',
            'phone' => '01012345678',
        ]);

        $user->delete();

        $trashed = User::withTrashed()->findOrFail($user->id);

        $this->assertTrue($trashed->trashed());
        $this->assertSame('reusable@example.com', $trashed->deleted_email);
        $this->assertSame('01012345678', $trashed->deleted_phone);
        $this->assertSame("deleted+{$user->id}@deleted.invalid", $trashed->email);
        $this->assertSame("deleted-{$user->id}", $trashed->phone);

        // نفس البريد والهاتف يمكن استخدامهما في حساب جديد.
        $replacement = User::factory()->create([
            'email' => 'reusable@example.com',
            'phone' => '01012345678',
        ]);

        $this->assertSame('reusable@example.com', $replacement->email);
        $this->assertSame('01012345678', $replacement->phone);
    }

    public function test_soft_deleted_user_cannot_log_in_with_old_email(): void
    {
        $user = User::factory()->create([
            'email' => 'gone@example.com',
            'password' => 'password123',
        ]);

        $user->delete();

        $this->postJson('/api/login', [
            'email'    => 'gone@example.com',
            'password' => 'password123',
        ])->assertStatus(422);
    }

    public function test_user_without_phone_is_deleted_without_phone_placeholder(): void
    {
        $user = User::factory()->create(['phone' => null]);

        $user->delete();

        $trashed = User::withTrashed()->findOrFail($user->id);

        $this->assertNull($trashed->phone);
        $this->assertNull($trashed->deleted_phone);
    }
}
