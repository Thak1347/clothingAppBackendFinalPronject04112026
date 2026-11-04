<?php
namespace Database\Factories;

use App\Enums\NotificationChannel;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class NotificationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'order_id' => Order::factory(),
            'channel' => NotificationChannel::Push,
            'type' => 'order_created',
            'title' => 'Order placed',
            'body' => 'Thank you for your order.',
            'payload' => ['order' => fake()->uuid()],
            'sent_at' => now(),
            'read_at' => null,
        ];
    }

    public function telegram(): static
    {
        return $this->state(fn() => ['channel' => NotificationChannel::Telegram]);
    }
}
