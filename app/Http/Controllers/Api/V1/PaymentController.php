<?php
namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Payment\InitiatePaymentRequest;
use App\Models\Payment;
use App\Services\PaymentService;
use App\Services\Payments\KhqrPaymentProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redirect;

class PaymentController extends Controller
{
    public function initiate(InitiatePaymentRequest $request, Payment $payment, PaymentService $svc)
    {
        abort_unless($payment->order->user_id === $request->user()->id, 403);
        $allowedOrderStates = $payment->order->payment_method === 'cash'
            ? [\App\Enums\FulfillmentStatus::AwaitingDeposit]
            : [\App\Enums\FulfillmentStatus::PendingPayment];
        abort_unless(in_array($payment->order->fulfillment_status, $allowedOrderStates, true), 422, 'Payment cannot be initiated for this order status.');
        $method = $request->validated('provider');
        if ($payment->order->payment_method === 'cash') {
            abort_unless($method === 'aba_pay', 422, 'COD orders require the 50% ABA Pay deposit.');
            abort_unless($payment->order->cod_verification_status === 'verified' && $payment->order->fulfillment_status === \App\Enums\FulfillmentStatus::AwaitingDeposit, 422, 'Your COD documents must be approved before paying the deposit.');
            abort_unless(in_array($payment->status, [\App\Enums\PaymentStatus::Unpaid, \App\Enums\PaymentStatus::Pending, \App\Enums\PaymentStatus::Expired, \App\Enums\PaymentStatus::Failed], true), 422, 'COD deposit is no longer payable.');
            $payment->update(['provider' => 'aba_pay', 'amount_minor' => intdiv((int) $payment->order->total_minor + 1, 2)]);
            $payment->refresh();
        } else {
            abort_unless($payment->order->payment_method === null || $payment->order->payment_method === $method, 422, 'Payment method cannot be changed after checkout.');
            if ($payment->order->payment_method === null && $payment->provider !== 'mock') {
                abort_unless($payment->provider === $method, 422, 'Payment method cannot be changed after payment starts.');
            }
            $payment->order->update(['payment_method' => $method]);
        }
        return response()->json($svc->initiate($payment, $method));
    }

    public function callback(Request $request, PaymentService $svc)
    {
        $payload = $request->all();
        $reference = $payload['transaction_id'] ?? null;
        Log::info('Payment webhook received', [
            'transaction_id' => is_scalar($reference) ? (string) $reference : null,
            'status' => is_scalar($payload['status'] ?? null) ? strtoupper((string) $payload['status']) : null,
        ]);
        abort_unless(app(KhqrPaymentProvider::class)->verifyWebhookHash($payload), 403, 'Invalid webhook signature.');
        if (strtoupper((string) ($payload['status'] ?? '')) !== 'SUCCESS') return response()->json(['received' => true]);
        $payment = $svc->handleCallback($payload);
        if (!$payment) {
            Log::warning('KHQR callback acknowledged without completing payment.', [
                'transaction_id' => is_scalar($reference) ? (string) $reference : null,
                'reason' => 'unknown_reference_or_amount_provider_mismatch_or_unverified_status',
            ]);
        }
        // AnajakPay documentation requires HTTP 200 acknowledgments to prevent
        // retry storms. Acknowledgment is not proof of payment; only the service
        // completion flow updates local order/payment state.
        return response()->json(['received' => true]);
    }

    public function successReturn(Request $request, PaymentService $svc)
    {
        $reference = $request->query('transaction_id');
        if (!is_string($reference) || $reference === '') {
            return response()->json(['payment_status' => 'pending', 'message' => 'Missing transaction reference.'], 422);
        }

        if ($request->isMethod('post')) {
            return $this->callback($request, $svc);
        }
        $payment = $svc->handleRedirectReturn($request->query());
        $status = $payment->fresh()->status->value;
        $clientUrl = (string) config('services.payment.khqr.client_return_url');
        if ($clientUrl !== '') {
            $separator = str_contains($clientUrl, '?') ? '&' : '?';
            return Redirect::away($clientUrl . $separator . http_build_query([
                'transaction_id' => $payment->payment_reference,
                'payment_status' => $status,
            ], '', '&', PHP_QUERY_RFC3986));
        }

        return response()->json([
            'transaction_id' => $payment->payment_reference,
            'payment_status' => $status,
            'message' => 'Payment status is determined by verified provider notifications.',
        ]);
    }
}
