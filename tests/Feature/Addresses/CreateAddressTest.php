<?php

namespace Tests\Feature\Addresses;

use App\Models\Address;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateAddressTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_customer_can_create_an_address(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/v1/addresses', [
            'label' => 'Home',
            'recipient_name' => 'Jane Doe',
            'phone' => '012345678',
            'address_line' => '123 Main Street',
            'province' => 'Phnom Penh',
            'is_default' => true,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.label', 'Home')
            ->assertJsonPath('data.address_line', '123 Main Street')
            ->assertJsonPath('data.province', 'Phnom Penh')
            ->assertJsonPath('data.is_default', true);

        $this->assertDatabaseHas('addresses', [
            'user_id' => $user->id,
            'address_line' => '123 Main Street',
            'province' => 'Phnom Penh',
            'is_default' => true,
        ]);
    }
}
