<?php
namespace Database\Seeders;

use App\Enums\NotificationChannel;
use App\Models\Address;
use App\Models\Notification;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\ProductVariant;
use App\Models\TransactionBill;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DemoOrderSeeder extends Seeder
{
    public function run(): void
    {
        $customers = User::where('role', 'customer')->get();

        foreach ($customers as $customer) {
            $address = $customer->addresses()->first();
            if (!$address) {
                continue;
            }

            $order = $this->createOrder($customer, $address);

            // Attach 2 random variants as line items.
            $variants = ProductVariant::inRandomOrder()->take(2)->get();

            $subtotal = 0;
            foreach ($variants as $variant) {
                $qty = random_int(1, 3);
                $unit = (int) $variant->product->price_minor;
                $line = $qty * $unit;

                OrderItem::create([
                    'order_id' => $order->id,
                    'product_variant_id' => $variant->id,
                    'product_name_snapshot' => $variant->product->name,
                    'asset_key_snapshot' => $variant->product->asset_key,
                    'size_snapshot' => $variant->size,
                    'color_snapshot' => $variant->color,
                    'quantity' => $qty,
                    'unit_price_minor' => $unit,
                    'line_total_minor' => $line,
                ]);

                $subtotal += $line;
            }

            $fee = 200; // $2.00 USD
            $discount = 0;

            $order->update([
                'subtotal_minor' => $subtotal,
                'delivery_fee_minor' => $fee,
                'discount_minor' => $discount,
                'total_minor' => $subtotal + $fee - $discount,
            ]);

            // Create paid payment + receipt + notification audit row.
            $payment = Payment::create([
                'order_id' => $order->id,
                'provider' => 'mock',
                'payment_reference' => 'PAY-' . Str::upper(Str::random(12)),
                'provider_txn_ref' => 'txn_' . Str::random(16),
                'amount_minor' => $order->total_minor,
                'currency' => 'USD',
                'status' => 'paid',
                'verified_at' => now(),
                'provider_metadata' => ['hash' => Str::random(20)],
            ]);

            TransactionBill::create([
                'payment_id' => $payment->id,
                'bill_number' => 'BILL-' . Str::upper(Str::random(10)),
                'receipt_reference' => 'RCP-' . Str::upper(Str::random(10)),
                'issued_at' => now(),
                'payload' => [
                    'order_number' => $order->order_number,
                    'total_minor' => $order->total_minor,
                    'currency' => 'USD',
                ],
            ]);

            Notification::create([
                'user_id' => $customer->id,
                'order_id' => $order->id,
                'channel' => NotificationChannel::Push,
                'type' => 'payment_paid',
                'title' => 'Payment received',
                'body' => 'We received your payment for ' . $order->order_number,
                'payload' => ['payment_reference' => $payment->payment_reference],
                'sent_at' => now(),
            ]);
        }
    }

    private function createOrder(User $user, Address $address): Order
    {
        return Order::create([
            'user_id' => $user->id,
            'address_id' => $address->id,
            'order_number' => 'ORD-' . Str::upper(Str::random(12)),

            'ship_recipient_name' => $address->recipient_name,
            'ship_phone' => $address->phone,
            'ship_address_line' => $address->address_line,
            'ship_province' => $address->province,
            'ship_latitude' => $address->latitude,
            'ship_longitude' => $address->longitude,

            'subtotal_minor' => 0,
            'delivery_fee_minor' => 0,
            'discount_minor' => 0,
            'total_minor' => 0,

            'currency' => 'USD',
            'fulfillment_status' => 'processing',
            'payment_status' => 'paid',
        ]);
    }
}
