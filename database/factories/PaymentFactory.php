<?php
namespace Database\Factories;

use App\Enums\PaymentStatus;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class PaymentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'provider' => 'mock',
            'payment_reference' => 'PAY-' . Str::upper(Str::random(12)),
            'provider_txn_ref' => null,
            'amount_minor' => fake()->numberBetween(1000, 100000),
            'currency' => 'USD',
            'status' => PaymentStatus::Pending,
            'verified_at' => null,
            'provider_metadata' => null,
        ];
    }

    public function paid(): static
    {
        return $this->state(fn() => [
            'status' => PaymentStatus::Paid,
            'verified_at' => now(),
            'provider_txn_ref' => 'txn_' . Str::random(16),
            'provider_metadata' => ['hash' => Str::random(20)],
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn() => ['status' => PaymentStatus::Failed]);
    }
}
