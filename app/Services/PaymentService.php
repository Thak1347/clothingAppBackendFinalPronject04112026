<?php
namespace App\Services;

use App\Enums\FulfillmentStatus;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\Order;
use App\Jobs\SendPushNotification;
use App\Services\Payments\KhqrPaymentProvider;
use App\Services\Payments\PaymentProviderInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymentService
{
    public function __construct(private PaymentProviderInterface $provider)
    {
    }

    public function initiate(Payment $payment, string $method): array
    {
        if (!in_array($payment->status, [PaymentStatus::Pending, PaymentStatus::Unpaid, PaymentStatus::Expired, PaymentStatus::Failed], true)) {
            abort(422, 'Payment is not pending.');
        }

        if ($method === 'cash') {
            abort(422, 'COD orders require the 50% ABA Pay deposit.');
        }

        if (!in_array($method, ['khqr', 'khqr_checkout', 'aba_pay'], true)) {
            abort(422, 'Unsupported payment method.');
        }
        if (config('services.payment.driver') !== 'khqr') {
            abort(503, 'KHQR payments are not configured on this server.');
        }

        $fromStatus = $payment->status->value;
        $payment->update(['provider' => $method, 'status' => PaymentStatus::Pending]);
        $payment->order->update(['payment_status' => PaymentStatus::Pending]);
        \App\Models\OrderActivity::record($payment->order, $payment->order->user_id, 'payment_initiated', $payment->order->fulfillment_status?->value, $payment->order->fulfillment_status?->value, ['from_payment_status' => $fromStatus, 'to_payment_status' => PaymentStatus::Pending->value, 'provider' => $method]);
        if ($method === 'aba_pay') {
            return app(KhqrPaymentProvider::class)->initiateAbaPay($payment->fresh());
        }
        if ($method === 'khqr_checkout') {
            return app(KhqrPaymentProvider::class)->initiateCheckout($payment->fresh());
        }
        return app(KhqrPaymentProvider::class)->initiate($payment->fresh());
    }

    public function handleCallback(array $payload): ?Payment
    {
        $reference = $payload['transaction_id'] ?? null;
        if (!is_string($reference) || $reference === '') return null;
        $payment = Payment::where('payment_reference', $reference)->first();
        if (!$payment) {
            Log::warning('KHQR callback references an unknown local payment.', ['transaction_id' => $reference]);
            return null;
        }
        if (in_array($payment->status, [PaymentStatus::Paid, PaymentStatus::PartiallyPaid], true)) return $payment;
        if (!in_array($payment->provider, ['khqr', 'khqr_checkout', 'aba_pay'], true)
            || strtoupper((string) ($payload['status'] ?? '')) !== 'SUCCESS'
            || !is_numeric($payload['amount'] ?? null)
            || $payment->currency !== 'USD'
            || number_format((float) $payload['amount'], 2, '.', '') !== number_format($payment->amount_minor / 100, 2, '.', '')) {
            Log::warning('KHQR callback does not match a pending local payment.', [
                'transaction_id' => $reference,
                'local_provider' => $payment->provider,
                'local_currency' => $payment->currency,
                'local_amount_minor' => $payment->amount_minor,
            ]);
            return null;
        }
        $verified = app(KhqrPaymentProvider::class)->verify($payload);
        if ($verified !== 'paid') {
            Log::warning('KHQR callback provider verification was not successful.', [
                'transaction_id' => $reference,
                'verification_status' => $verified,
            ]);
            return null;
        }
        return $this->completeVerifiedPayment($payment, [
            'provider_txn_ref' => data_get($payload, 'payment_details.externalRef'),
            'callback_req_time' => $payload['req_time'] ?? null,
            'callback_payment_details' => $payload['payment_details'] ?? null,
        ]);
    }

    public function handleRedirectReturn(array $payload): Payment
    {
        $transactionId = $payload['transaction_id'] ?? null;
        abort_unless(is_string($transactionId) && $transactionId !== '', 422, 'Missing transaction_id.');

        $payment = Payment::where('payment_reference', $transactionId)->firstOrFail();
        abort_unless($payment->provider === 'khqr_checkout', 422, 'Payment is not a hosted KHQR payment.');

        if (app(KhqrPaymentProvider::class)->verifyRedirect($payload) === 'paid') {
            $payment = $this->completeVerifiedPayment($payment, ['redirect_verified' => true]);
        }
        return $payment;
    }

    public function poll(Order $order): array
    {
        $payment = $order->payment;
        if (!$payment || in_array($payment->status, [PaymentStatus::Paid, PaymentStatus::PartiallyPaid], true) || !in_array($payment->provider, ['khqr', 'khqr_checkout', 'aba_pay'], true))
            return ['status' => $payment?->status?->value ?? 'pending'];
        if ($payment->status === PaymentStatus::Expired) return ['status' => 'expired'];
        if (now()->greaterThan($order->created_at->copy()->addMinutes(15))) {
            $oldStatus = $order->payment_status?->value;
            $payment->update(['status' => PaymentStatus::Expired]);
            $order->update(['payment_status' => PaymentStatus::Expired]);
            \App\Models\OrderActivity::record($order, null, 'payment_expired', $order->fulfillment_status?->value, $order->fulfillment_status?->value, ['from_payment_status' => $oldStatus, 'to_payment_status' => PaymentStatus::Expired->value]);
            return ['status' => 'expired'];
        }
        if (in_array($payment->provider, ['aba_pay', 'khqr_checkout'], true)) {
            $amount = number_format($payment->amount_minor / 100, 2, '.', '');
            $verification = app(KhqrPaymentProvider::class)->verifyTransactionStatus(
                $payment->payment_reference,
                $amount,
            );
            if ($verification === 'paid') {
                $payment = $this->completeVerifiedPayment($payment, [
                    'status_check' => 'check-transv2-khqrcc',
                ]);
            }
            return ['status' => $payment->fresh()->status->value];
        }
        // Polling reads only the webhook-confirmed database status. The callback
        // performs check-trans before it is allowed to fulfil the order.
        return ['status' => $payment->fresh()->status->value];
    }

    private function completeVerifiedPayment(Payment $payment, array $metadata = []): Payment
    {
        if (in_array($payment->status, [PaymentStatus::Paid, PaymentStatus::PartiallyPaid], true)) {
            return $payment;
        }
        if ($payment->status !== PaymentStatus::Pending) return $payment;

        $wasPaid = false;
        $payment = DB::transaction(function () use ($payment, $metadata, &$wasPaid) {
            $order = Order::whereKey($payment->order_id)->lockForUpdate()->firstOrFail();
            $locked = Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail();
            if (in_array($locked->status, [PaymentStatus::Paid, PaymentStatus::PartiallyPaid], true)) return $locked;
            if ($locked->status !== PaymentStatus::Pending) return $locked;
            $isCodDeposit = $order->payment_method === 'cash';
            if ($isCodDeposit) abort_unless(($order->cod_verification_status === 'verified' && in_array($order->fulfillment_status, [FulfillmentStatus::AwaitingDeposit, FulfillmentStatus::CancellationRequested], true)) || $order->fulfillment_status === FulfillmentStatus::Cancelled, 422, 'COD order is not eligible for deposit confirmation.');
            $wasPaid = true;
            $codDeposit = $isCodDeposit;
            $locked->update([
                'status' => $codDeposit ? PaymentStatus::PartiallyPaid : PaymentStatus::Paid,
                'verified_at' => now(),
                'provider_txn_ref' => $metadata['provider_txn_ref'] ?? $locked->provider_txn_ref,
                'provider_metadata' => array_merge($locked->provider_metadata ?? [], array_filter($metadata + ['result' => 'paid'], fn ($value) => $value !== null)),
            ]);
            $oldStatus = $order->fulfillment_status?->value;
            $newFulfillment = match ($order->fulfillment_status) {
                FulfillmentStatus::AwaitingDeposit => FulfillmentStatus::Processing,
                FulfillmentStatus::PendingPayment => FulfillmentStatus::Processing,
                default => $order->fulfillment_status,
            };
            $order->update(['payment_status' => $codDeposit ? PaymentStatus::PartiallyPaid : PaymentStatus::Paid, 'fulfillment_status' => $newFulfillment]);
            \App\Models\OrderActivity::record($order, null, $codDeposit ? 'cod_deposit_verified' : 'payment_verified', $oldStatus, $order->fulfillment_status->value, ['amount_minor' => (int) $locked->amount_minor, 'payment_reference' => $locked->payment_reference, 'provider_txn_ref' => $metadata['provider_txn_ref'] ?? null]);
            app(ReceiptService::class)->issue($locked);
            return $locked;
        });
        if (!$wasPaid) return $payment->fresh();
        $order = $payment->order;
        $codDeposit = $order->payment_method === 'cash';
        SendPushNotification::dispatch(
            $order->user_id,
            $codDeposit ? 'COD deposit confirmed' : 'Payment successful',
            $codDeposit
                ? "Your 50% deposit for order {$order->order_number} was confirmed. The order is now processing."
                : "Payment for order {$order->order_number} was confirmed.",
        )->afterCommit();

        return $payment->fresh();
    }
}
