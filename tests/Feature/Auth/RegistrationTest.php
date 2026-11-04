<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_saves_and_returns_phone_country_code(): void
    {
        $this->postJson('/api/v1/auth/register', [
            'name' => 'Phone User',
            'email' => 'phone-user@example.com',
            'phone' => '123456789',
            'phone_country_code' => '+855',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
        ])
            ->assertCreated()
            ->assertJsonPath('user.phone', '123456789')
            ->assertJsonPath('user.phone_country_code', '+855');

        $this->assertDatabaseHas('users', [
            'email' => 'phone-user@example.com',
            'phone' => '123456789',
            'phone_country_code' => '+855',
        ]);
    }

    public function test_registration_rejects_an_invalid_phone_country_code(): void
    {
        $this->postJson('/api/v1/auth/register', [
            'name' => 'Phone User',
            'email' => 'phone-user@example.com',
            'phone' => '123456789',
            'phone_country_code' => '855',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('phone_country_code');

        $this->assertDatabaseMissing('users', [
            'email' => 'phone-user@example.com',
        ]);
    }
}
