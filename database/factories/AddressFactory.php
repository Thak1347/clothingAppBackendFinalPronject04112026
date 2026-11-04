<?php
namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class AddressFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'label' => fake()->randomElement(['Home', 'Work', null]),
            'recipient_name' => fake()->name(),
            'phone' => fake()->numerify('0########'),
            'address_line' => fake()->streetAddress(),
            'province' => fake()->city(),
            'latitude' => fake()->optional()->latitude(),
            'longitude' => fake()->optional()->longitude(),
            'is_default' => false,
        ];
    }

    public function default(): static
    {
        return $this->state(fn() => ['is_default' => true]);
    }

    public function forUser(User $user): static
    {
        return $this->state(fn() => ['user_id' => $user->id]);
    }
}
