<?php

namespace Tests\Feature\Profile;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UpdatePasswordTest extends TestCase
{
    use RefreshDatabase;

    public function test_customers_and_admins_can_update_their_password(): void
    {
        foreach ([
            User::factory(),
            User::factory()->admin(),
        ] as $factory) {
            $user = $factory->create([
                'password' => Hash::make('OldPassword123'),
            ]);

            $this->actingAs($user, 'sanctum')
                ->patchJson('/api/v1/me/password', [
                    'current_password' => 'OldPassword123',
                    'new_password' => 'NewPassword123',
                    'new_password_confirmation' => 'NewPassword123',
                ])
                ->assertNoContent();

            $this->assertTrue(Hash::check('NewPassword123', $user->fresh()->password));
            $this->assertFalse(Hash::check('OldPassword123', $user->fresh()->password));
        }
    }

    public function test_password_update_requires_the_current_password(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('OldPassword123'),
        ]);

        $this->actingAs($user, 'sanctum')
            ->patchJson('/api/v1/me/password', [
                'current_password' => 'WrongPassword123',
                'new_password' => 'NewPassword123',
                'new_password_confirmation' => 'NewPassword123',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('current_password');

        $this->assertTrue(Hash::check('OldPassword123', $user->fresh()->password));
    }

    public function test_password_update_requires_a_confirmed_new_password_of_at_least_eight_characters(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('OldPassword123'),
        ]);

        $this->actingAs($user, 'sanctum')
            ->patchJson('/api/v1/me/password', [
                'current_password' => 'OldPassword123',
                'new_password' => 'short',
                'new_password_confirmation' => 'different',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['new_password']);
    }
}
