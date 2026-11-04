<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\TransactionBill;
use Illuminate\Support\Str;

class ReceiptService
{
    public function issue(Payment $payment): TransactionBill
    {
        $payment->loadMissing('order.items');
        $order = $payment->order;

        return TransactionBill::firstOrCreate(
            ['payment_id' => $payment->id],
            [
                'bill_number' => 'RCP-' . Str::upper(Str::random(10)),
                'receipt_reference' => $payment->payment_reference,
                'issued_at' => now(),
                'payload' => [
                    'order_number' => $order->order_number,
                    'amount' => $payment->amount_minor / 100,
                    'currency' => $payment->currency,
                    'provider' => $payment->provider,
                    'paid_at' => ($payment->verified_at ?? now())->toIso8601String(),
                    'receipt_type' => $payment->status?->value === 'partially_paid' ? 'deposit' : 'full_payment',
                    'payment_breakdown' => [[
                        'method' => $payment->provider,
                        'reference' => $payment->provider_txn_ref ?: $payment->payment_reference,
                        'amount_minor' => (int) $payment->amount_minor,
                        'paid_at' => ($payment->verified_at ?? now())->toIso8601String(),
                    ]],
                    'items' => $order->items->map(fn ($item) => [
                        'name' => $item->product_name_snapshot,
                        'size' => $item->size_snapshot,
                        'color' => $item->color_snapshot,
                        'qty' => $item->quantity,
                        'price' => $item->unit_price_minor / 100,
                    ])->all(),
                ],
            ],
        );
    }

    public function finalizeCodBalance(Payment $payment, int $cashBalanceMinor, int $receivedBy): TransactionBill
    {
        $payment->loadMissing('order.items');
        $order = $payment->order;
        $bill = $this->issue($payment);
        $payload = $bill->payload ?? [];
        $breakdown = $payload['payment_breakdown'] ?? [];
        $breakdown[] = [
            'method' => 'cash_on_delivery',
            'reference' => null,
            'amount_minor' => $cashBalanceMinor,
            'paid_at' => $order->cod_balance_paid_at?->toIso8601String() ?? now()->toIso8601String(),
            'received_by' => $receivedBy,
        ];

        $payload['receipt_type'] = 'final';
        $payload['amount'] = ((int) $order->total_minor) / 100;
        $payload['amount_minor'] = (int) $order->total_minor;
        $payload['payment_breakdown'] = $breakdown;
        $payload['paid_at'] = $order->cod_balance_paid_at?->toIso8601String() ?? now()->toIso8601String();
        $bill->update(['payload' => $payload, 'issued_at' => now()]);

        return $bill->fresh();
    }
}
