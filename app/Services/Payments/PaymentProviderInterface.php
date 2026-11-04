<?php
namespace App\Services\Payments;

use App\Models\Payment;

interface PaymentProviderInterface
{
    /** Initiate a payment; return payload (e.g. QR string) */
    public function initiate(Payment $payment): array;

    /** Verify callback payload signature; return status string. */
    public function verify(array $payload): string; // 'paid' | 'failed'
}
