<?php
namespace App\Services;

use App\Enums\FulfillmentStatus;
use App\Enums\PaymentStatus;
use App\Models\Address;
use App\Models\Order;
use App\Models\OrderActivity;
use App\Models\ProductVariant;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class CheckoutService
{
    public function __construct(private InventoryService $inventory)
    {
    }

    /** Calculate totals without persisting anything. */
    public function preview(int $addressId, array $items): array
    {
        $address = Address::findOrFail($addressId);
        [$lines, $subtotal] = $this->priceItems($items);
        return [
            'address' => $address->toArray(),
            'items' => $lines,
            'subtotal_minor' => $subtotal,
            'delivery_fee_minor' => 0,
            'total_minor' => $subtotal,
            'currency' => 'USD',
        ];
    }

    public function createOrder(int $userId, int $addressId, array $items, ?array $codDetails = null, ?string $paymentMethod = null): Order
    {
        return DB::transaction(function () use ($userId, $addressId, $items, $codDetails, $paymentMethod) {
            $this->inventory->reserve($items);

            $address = Address::where('user_id', $userId)->findOrFail($addressId);
            [$lines, $subtotal] = $this->priceItems($items);

            $isCod = $codDetails !== null;
            $total = $subtotal;
            // For an odd number of cents, round the required 50% deposit up so
            // that it is never less than half of the authoritative order total.
            $deposit = $isCod ? intdiv($total + 1, 2) : $total;
            $order = Order::create([
                'order_number' => 'ORD-' . now()->format('Ymd') . '-' . Str::upper(Str::random(6)),
                'user_id' => $userId,
                'address_id' => $address->id,
                'fulfillment_status' => $isCod ? FulfillmentStatus::PendingVerification : FulfillmentStatus::PendingPayment,
                'payment_status' => $isCod ? PaymentStatus::Unpaid : PaymentStatus::Pending,
                'subtotal_minor' => $subtotal,
                'delivery_fee_minor' => 0,
                'discount_minor' => 0,
                'total_minor' => $subtotal,
                'currency' => 'USD',
                'ship_recipient_name' => $address->recipient_name,
                'ship_phone' => $address->phone,
                'ship_address_line' => $address->address_line,
                'ship_province' => $address->province,
                'ship_latitude' => $address->latitude,
                'ship_longitude' => $address->longitude,
                'payment_method' => $isCod ? 'cash' : $paymentMethod,
                'cod_customer_name' => $codDetails['name'] ?? null,
                'cod_phone' => $codDetails['phone'] ?? null,
                'cod_email' => $codDetails['email'] ?? null,
                'cod_telegram' => $codDetails['telegram'] ?? null,
                'cod_address' => $codDetails['address'] ?? null,
                'cod_home_photo_path' => $codDetails['home_photo_path'] ?? null,
                'cod_verification_status' => $isCod ? 'pending' : null,
                'cod_terms_accepted_at' => $isCod ? now() : null,
            ]);

            foreach ($lines as $line) {
                $order->items()->create($line);
            }

            if ($isCod) {
                OrderActivity::record($order, $userId, 'cod_submitted', null, FulfillmentStatus::PendingVerification->value, ['document' => 'private_home_photo', 'terms_accepted_at' => $order->cod_terms_accepted_at?->toISOString(), 'policy_version' => 'cod-2026-10-v1']);
            } elseif ($paymentMethod !== null) {
                OrderActivity::record($order, $userId, 'payment_method_selected', null, FulfillmentStatus::PendingPayment->value, ['payment_method' => $paymentMethod]);
            }

            $order->payment()->create([
                'provider' => $isCod ? 'aba_pay' : ($paymentMethod ?? 'mock'),
                'payment_reference' => 'PAY-' . Str::upper(Str::random(10)),
                'amount_minor' => $deposit,
                'currency' => 'USD',
                'status' => $isCod ? PaymentStatus::Unpaid : PaymentStatus::Pending,
            ]);

            return $order;
        });
    }

    /** @return array{0: array, 1: int} line snapshots and subtotal in minor units */
    private function priceItems(array $items): array
    {
        $lines = [];
        $subtotal = 0;

        foreach ($items as $item) {
            $variant = ProductVariant::with('product')->findOrFail($item['product_variant_id']);
            $unitPrice = (int) $variant->product->price_minor;
            $quantity = (int) $item['quantity'];
            $lineTotal = $unitPrice * $quantity;

            $lines[] = [
                'product_variant_id' => $variant->id,
                'product_name_snapshot' => $variant->product->name,
                'asset_key_snapshot' => $variant->product->asset_key,
                'size_snapshot' => $variant->size,
                'color_snapshot' => $variant->color,
                'product_name' => $variant->product->name,
                'size' => $variant->size,
                'color' => $variant->color,
                'quantity' => $quantity,
                'unit_price_minor' => $unitPrice,
                'line_total_minor' => $lineTotal,
                'unit_price' => $unitPrice / 100,
                'line_total' => $lineTotal / 100,
            ];
            $subtotal += $lineTotal;
        }

        return [$lines, $subtotal];
    }
}
