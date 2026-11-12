<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AuthThrottleAndSocialTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_is_rate_limited_per_email(): void
    {
        User::factory()->customer()->create(['email' => 'throttle@example.com']);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/login', [
                'email'    => 'throttle@example.com',
                'password' => 'wrong-password',
            ])->assertStatus(422);
        }

        $this->postJson('/api/login', [
            'email'    => 'throttle@example.com',
            'password' => 'wrong-password',
        ])->assertStatus(429);
    }

    public function test_social_login_cannot_link_admin_employee_or_influencer(): void
    {
        config(['services.google.client_ids' => ['test-client']]);

        $types = ['admin', 'employee', 'influencer'];

        foreach ($types as $type) {
            $email = "{$type}@example.com";

            $user = User::factory()->{$type}()->create(['email' => $email]);

            Http::fake([
                'oauth2.googleapis.com/*' => Http::response([
                    'aud'            => 'test-client',
                    'iss'            => 'accounts.google.com',
                    'sub'            => 'google-' . $type,
                    'email'          => $email,
                    'email_verified' => true,
                    'name'           => ucfirst($type),
                ], 200),
            ]);

            $response = $this->postJson('/api/auth/google/token', [
                'id_token' => 'fake-token-' . $type,
            ]);

            $response->assertStatus(403);
            $this->assertArrayNotHasKey('token', $response->json());
            $this->assertNull($user->fresh()->provider);
        }
    }

    public function test_inactive_account_does_not_receive_token(): void
    {
        User::factory()->customer()->inactive()->create([
            'email' => 'inactive@example.com',
        ]);

        $response = $this->withHeaders(['Accept-Language' => 'ar'])
            ->postJson('/api/login', [
                'email'    => 'inactive@example.com',
                'password' => 'password',
            ]);

        $response->assertStatus(403)
            ->assertJsonPath('message', 'الحساب موقوف');

        $this->assertArrayNotHasKey('token', $response->json());
    }

    public function test_social_login_for_inactive_customer_is_rejected(): void
    {
        config(['services.google.client_ids' => ['test-client']]);

        User::factory()->customer()->inactive()->create([
            'email' => 'inactive-social@example.com',
        ]);

        Http::fake([
            'oauth2.googleapis.com/*' => Http::response([
                'aud'            => 'test-client',
                'iss'            => 'accounts.google.com',
                'sub'            => 'google-inactive',
                'email'          => 'inactive-social@example.com',
                'email_verified' => true,
                'name'           => 'Inactive',
            ], 200),
        ]);

        $this->postJson('/api/auth/google/token', ['id_token' => 'fake'])
            ->assertStatus(403);
    }
}
