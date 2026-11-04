<?php

namespace Tests\Feature\Profile;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpdateProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_update_phone_number_and_country_code(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->patchJson('/api/v1/me', [
                'name' => $user->name,
                'email' => $user->email,
                'phone' => '123456789',
                'phone_country_code' => '+855',
            ])
            ->assertOk()
            ->assertJsonPath('data.phone', '123456789')
            ->assertJsonPath('data.phone_country_code', '+855');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'phone' => '123456789',
            'phone_country_code' => '+855',
        ]);
    }

    public function test_phone_country_code_must_be_a_valid_international_dial_code(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->patchJson('/api/v1/me', [
                'phone' => '123456789',
                'phone_country_code' => '855',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('phone_country_code');
    }

    public function test_customer_can_remove_phone_number_and_country_code(): void
    {
        $user = User::factory()->create([
            'phone' => '123456789',
            'phone_country_code' => '+855',
        ]);

        $this->actingAs($user, 'sanctum')
            ->patchJson('/api/v1/me', [
                'phone' => null,
                'phone_country_code' => null,
            ])
            ->assertOk()
            ->assertJsonPath('data.phone', null)
            ->assertJsonPath('data.phone_country_code', null);
    }
}
