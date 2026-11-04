<?php
namespace Database\Factories;

use App\Enums\FulfillmentStatus;
use App\Enums\PaymentStatus;
use App\Models\Address;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class OrderFactory extends Factory
{
    public function definition(): array
    {
        $subtotal = fake()->numberBetween(1000, 100000);
        $fee = fake()->numberBetween(0, 5000);
        $discount = fake()->numberBetween(0, 1000);

        $address = Address::factory()->make(); // snapshot shape, no persist

        return [
            'user_id' => User::factory(),
            'address_id' => null,
            'order_number' => 'ORD-' . Str::upper(Str::random(12)),

            'ship_recipient_name' => $address->recipient_name,
            'ship_phone' => $address->phone,
            'ship_address_line' => $address->address_line,
            'ship_province' => $address->province,
            'ship_latitude' => $address->latitude,
            'ship_longitude' => $address->longitude,

            'subtotal_minor' => $subtotal,
            'delivery_fee_minor' => $fee,
            'discount_minor' => $discount,
            'total_minor' => $subtotal + $fee - $discount,

            'currency' => 'USD',
            'fulfillment_status' => FulfillmentStatus::PendingPayment,
            'payment_status' => PaymentStatus::Pending,
        ];
    }

    public function paid(): static
    {
        return $this->state(fn() => [
            'payment_status' => PaymentStatus::Paid,
            'fulfillment_status' => FulfillmentStatus::Processing,
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn() => [
            'fulfillment_status' => FulfillmentStatus::Cancelled,
            'payment_status' => PaymentStatus::Failed,
        ]);
    }

    public function forUser(User $user): static
    {
        return $this->state(fn() => ['user_id' => $user->id]);
    }
}
