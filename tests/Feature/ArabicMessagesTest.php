<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArabicMessagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_validation_messages_are_arabic_when_accept_language_is_ar(): void
    {
        $response = $this->postJson('/api/login', [], [
            'Accept-Language' => 'ar',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('errors.email.0', 'حقل البريد الإلكتروني مطلوب.')
            ->assertJsonPath('errors.password.0', 'حقل كلمة المرور مطلوب.');
    }

    public function test_validation_messages_follow_accept_language_english(): void
    {
        $this->postJson('/api/login', [], [
            'Accept-Language' => 'en',
        ])
            ->assertStatus(422)
            ->assertJsonPath('errors.email.0', 'The email field is required.');
    }

    public function test_invalid_email_message_is_arabic(): void
    {
        $this->postJson('/api/login', [
            'email'    => 'not-an-email',
            'password' => 'secret',
        ], [
            'Accept-Language' => 'ar',
        ])
            ->assertStatus(422)
            ->assertJsonPath('errors.email.0', 'يجب أن يكون البريد الإلكتروني بريدًا إلكترونيًا صالحًا.');
    }
}
