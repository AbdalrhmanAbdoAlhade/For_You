<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthEmailChangeTest extends TestCase
{
    use RefreshDatabase;

    public function test_email_change_requires_current_password_and_resets_verification(): void
    {
        $user = User::factory()->customer()->create([
            'email' => 'old@example.com',
        ]);

        Sanctum::actingAs($user);

        $this->patchJson('/api/auth/profile', ['email' => 'new@example.com'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('current_password');

        $this->assertSame('old@example.com', $user->fresh()->email);

        $this->patchJson('/api/auth/profile', [
            'email'            => 'new@example.com',
            'current_password' => 'password',
        ])->assertOk();

        $fresh = $user->fresh();
        $this->assertSame('new@example.com', $fresh->email);
        $this->assertNull($fresh->email_verified_at);
    }

    public function test_passwordless_social_account_cannot_change_email(): void
    {
        $user = User::factory()->customer()->create([
            'email'    => 'social@example.com',
            'password' => null,
        ]);

        Sanctum::actingAs($user);

        $this->patchJson('/api/auth/profile', ['email' => 'attacker@example.com'])
            ->assertStatus(403);

        $this->assertSame('social@example.com', $user->fresh()->email);
    }
}
