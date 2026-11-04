<?php
namespace App\Services\Payments;

use App\Models\Payment;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Exceptions\HttpResponseException;
use RuntimeException;

class KhqrPaymentProvider implements PaymentProviderInterface
{
    /** Build a signed URL for AnajakPay's hosted checkout page. */
    public function initiateCheckout(Payment $payment): array
    {
        $profileId = (string) config('services.payment.khqr.profile_id');
        $secret = (string) config('services.payment.khqr.secret_key');
        // Hosted checkout always redirects the customer to success_url. A global
        // webhook, when configured in the merchant profile, receives its own POST.
        $successUrl = (string) config('services.payment.khqr.checkout_return_url');
        abort_if($profileId === '' || $secret === '' || $successUrl === '', 503,
            'KHQR hosted checkout credentials or customer return URL are not configured.');

        $transactionId = $payment->payment_reference;
        $amount = number_format($payment->amount_minor / 100, 2, '.', '');
        $remark = 'Order ' . $payment->order->order_number;
        // One public endpoint can receive the provider's POST callback and its
        // signed browser GET redirect. Preserve any query string from configuration.
        $successUrl .= (str_contains($successUrl, '?') ? '&' : '?')
            . http_build_query(['transaction_id' => $transactionId], '', '&', PHP_QUERY_RFC3986);
        $items = base64_encode(json_encode($payment->order->items->map(fn ($item) => [
            'name' => $item->product_name_snapshot,
            'quantity' => $item->quantity,
            'unit_price' => number_format($item->unit_price_minor / 100, 2, '.', ''),
        ])->values()->all(), JSON_THROW_ON_ERROR));
        $fields = [
            'transaction_id' => $transactionId,
            'amount' => $amount,
            'success_url' => $successUrl,
            'remark' => $remark,
            'items' => $items,
            'hash' => sha1($secret . $transactionId . $amount . $successUrl . $remark),
        ];
        $checkoutUrl = rtrim((string) config('services.payment.khqr.base_url'), '/') . '/payment/requestv2/'
            . rawurlencode($profileId) . '?' . http_build_query($fields, '', '&', PHP_QUERY_RFC3986);

        $payment->update(['provider_metadata' => array_merge($payment->provider_metadata ?? [], [
            'checkout_url' => $checkoutUrl,
        ])]);

        return [
            'provider' => 'khqr_checkout',
            'checkout_url' => $checkoutUrl,
            'reference' => $transactionId,
            'amount' => $amount,
            'currency' => $payment->currency,
        ];
    }

    public function initiateAbaPay(Payment $payment): array
    {
        $profileId = (string) (config('services.payment.anajakpay.profile_id') ?: config('services.payment.khqr.profile_id'));
        $secret = (string) (config('services.payment.anajakpay.secret') ?: config('services.payment.khqr.secret_key'));
        $successUrl = (string) (config('services.payment.khqr.webhook_url') ?: config('services.payment.khqr.success_url'));
        abort_if($profileId === '' || $secret === '' || $successUrl === '', 503,
            'ABA Pay credentials or callback URL are not configured.');

        $transactionId = $payment->payment_reference;
        $amount = number_format($payment->amount_minor / 100, 2, '.', '');
        $remark = 'Order ' . $payment->order->order_number;
        $fields = [
            'transaction_id' => $transactionId,
            'amount' => $amount,
            'success_url' => $successUrl,
            'remark' => $remark,
            'template' => 'template4_color',
            'hash' => sha1($secret . $transactionId . $amount . $successUrl . $remark),
        ];
        $url = rtrim(config('services.payment.khqr.base_url'), '/') . '/' . rawurlencode($profileId)
            . '/payment-gateway/v1/payments/qr-api-khqrcc';
        try {
            $response = Http::asForm()->acceptJson()
                ->timeout((int) config('services.payment.khqr.timeout', 15))->post($url, $fields);
        } catch (ConnectionException $exception) {
            report($exception);
            abort(502, 'Could not connect to ABA Pay. Please retry.');
        }
        if ($response->status() === 429) {
            $retryAfter = $response->header('Retry-After');
            $message = is_string($retryAfter) && ctype_digit($retryAfter)
                ? "ABA Pay is temporarily rate-limited. Please try again in {$retryAfter} seconds."
                : 'ABA Pay is temporarily rate-limited. Please wait before trying again.';
            throw new HttpResponseException(response()->json(
                ['message' => $message],
                429,
                is_string($retryAfter) && $retryAfter !== '' ? ['Retry-After' => $retryAfter] : [],
            ));
        }
        $body = $response->json();
        $data = data_get($body, 'data', []);
        if (!$response->successful() || (int) data_get($body, 'responseCode', 1) !== 0
            || (string) data_get($data, 'transaction_id') !== $transactionId
            || number_format((float) data_get($data, 'amount', 0), 2, '.', '') !== $amount
            || (!is_string(data_get($data, 'qr')) || data_get($data, 'qr') === '')
                && (!is_string(data_get($data, 'qr_url')) || data_get($data, 'qr_url') === '')) {
            report(new RuntimeException('ABA Pay QR creation returned an invalid response.'));
            abort(502, 'ABA Pay could not create a valid payment QR. Please retry.');
        }
        $payment->update(['provider_metadata' => array_merge($payment->provider_metadata ?? [], [
            'qr_url' => data_get($data, 'qr_url'), 'qr_md5' => data_get($data, 'md5'),
        ])]);
        return ['provider' => 'aba_pay', 'qr_string' => data_get($data, 'qr'),
            'qr_url' => data_get($data, 'qr_url'), 'reference' => $transactionId,
            'amount' => $amount, 'currency' => $payment->currency];
    }

    public function initiate(Payment $payment): array
    {
        $profileId = (string) config('services.payment.khqr.profile_id');
        $secret = (string) config('services.payment.khqr.secret_key');
        $successUrl = (string) (config('services.payment.khqr.webhook_url') ?: config('services.payment.khqr.success_url'));
        abort_if($profileId === '' || $secret === '' || $successUrl === '', 503,
            'KHQR payment credentials or callback URL are not configured.');

        $transactionId = $payment->payment_reference;
        $amount = number_format($payment->amount_minor / 100, 2, '.', '');
        $remark = 'Order ' . $payment->order->order_number;
        $items = base64_encode(json_encode($payment->order->items->map(fn ($item) => [
            'name' => $item->product_name_snapshot,
            'quantity' => $item->quantity,
            'unit_price' => number_format($item->unit_price_minor / 100, 2, '.', ''),
        ])->values()->all(), JSON_THROW_ON_ERROR));
        $fields = [
            'transaction_id' => $transactionId,
            'amount' => $amount,
            'success_url' => $successUrl,
            'remark' => $remark,
            'items' => $items,
            'hash' => sha1($secret . $transactionId . $amount . $successUrl . $remark),
        ];

        $url = rtrim(config('services.payment.khqr.base_url'), '/') . '/' . rawurlencode($profileId)
            . '/payment-gateway/v1/payments/qr-api';
        try {
            $response = Http::asForm()
                ->acceptJson()
                ->timeout((int) config('services.payment.khqr.timeout', 15))
                ->post($url, $fields);
        } catch (ConnectionException $exception) {
            report($exception);
            abort(502, 'Could not connect to the KHQR payment provider. Please retry.');
        }

        $body = $response->json();
        if (!$response->successful() || (int) data_get($body, 'responseCode', 1) !== 0) {
            report(new RuntimeException('KHQR QR creation failed: HTTP ' . $response->status()));
            abort(502, 'KHQR could not create a payment QR. Please retry.');
        }

        $data = data_get($body, 'data', []);
        $qr = data_get($data, 'qr');
        if (!is_string($qr) || $qr === '') {
            report(new RuntimeException('KHQR QR response did not include QR data.'));
            abort(502, 'KHQR returned an invalid QR response. Please retry.');
        }

        $payment->update(['provider_metadata' => array_merge($payment->provider_metadata ?? [], [
            'qr_url' => data_get($data, 'qr_url'),
            'qr_md5' => data_get($data, 'md5'),
            'qr_req_time' => data_get($data, 'req_time'),
        ])]);

        return [
            'provider' => 'khqr',
            'qr_string' => $qr,
            'qr_url' => data_get($data, 'qr_url'),
            'reference' => $transactionId,
            'amount' => $amount,
            'currency' => $payment->currency,
        ];
    }

    public function verify(array $payload): string
    {
        $secret = (string) (config('services.payment.anajakpay.secret') ?: config('services.payment.khqr.secret_key'));
        $required = ['req_time', 'transaction_id', 'amount', 'status', 'hash'];
        foreach ($required as $field) {
            if (!array_key_exists($field, $payload) || !is_scalar($payload[$field])) {
                return 'invalid';
            }
        }
        if ($secret === '' || strtoupper((string) $payload['status']) !== 'SUCCESS') {
            return 'invalid';
        }

        // Retain original scalar formatting from the JSON body; the signature is over those exact strings.
        $expected = hash('sha256', $secret . $payload['req_time'] . $payload['transaction_id']
            . $payload['amount'] . $payload['status']);
        if (!hash_equals($expected, (string) $payload['hash'])) {
            return 'invalid';
        }
        $amount = number_format((float) $payload['amount'], 2, '.', '');
        $payment = Payment::where('payment_reference', (string) $payload['transaction_id'])->first();
        if (!$payment || !in_array($payment->provider, ['khqr', 'khqr_checkout', 'aba_pay'], true) || $payment->currency !== 'USD'
            || $amount !== number_format($payment->amount_minor / 100, 2, '.', '')) return 'invalid';
        return $this->verifyTransaction((string) $payload['transaction_id'], $amount);
    }

    public function verifyWebhookHash(array $payload): bool
    {
        foreach (['req_time', 'transaction_id', 'amount', 'status', 'hash'] as $field) {
            if (!isset($payload[$field]) || !is_scalar($payload[$field])) return false;
        }
        $secret = (string) (config('services.payment.anajakpay.secret') ?: config('services.payment.khqr.secret_key'));
        if ($secret === '') return false;
        $expected = hash('sha256', $secret . $payload['req_time'] . $payload['transaction_id'] . $payload['amount'] . $payload['status']);
        return hash_equals($expected, (string) $payload['hash']);
    }

    /** Verify the signed browser return and provider transaction status. */
    public function verifyRedirect(array $payload): string
    {
        $profileId = (string) config('services.payment.khqr.profile_id');
        $required = ['transaction_id', 'success_time', 'success_amount', 'bakong_hash', 'success_hash'];
        foreach ($required as $field) {
            if (!isset($payload[$field]) || !is_scalar($payload[$field]) || (string) $payload[$field] === '') {
                return 'invalid';
            }
        }
        $profileKey = (string) config('services.payment.khqr.profile_key');
        if ($profileKey === '' || !ctype_digit((string) $payload['success_time'])
            || !is_numeric($payload['success_amount'])) {
            return 'invalid';
        }

        $timestamp = (int) $payload['success_time'];
        if ($timestamp > time() + 60 || time() - $timestamp > 180) {
            return 'invalid';
        }

        $expected = sha1($profileKey . $payload['success_time'] . $payload['success_amount']
            . $payload['bakong_hash'] . $payload['transaction_id']);
        if (!hash_equals($expected, (string) $payload['success_hash'])) {
            return 'invalid';
        }

        $payment = Payment::where('payment_reference', (string) $payload['transaction_id'])->first();
        $amount = number_format((float) $payload['success_amount'], 2, '.', '');
        if (!$payment || $payment->provider !== 'khqr_checkout' || $payment->currency !== 'USD'
            || $amount !== number_format($payment->amount_minor / 100, 2, '.', '')) return 'invalid';
        return $this->verifyTransaction((string) $payload['transaction_id'], $amount);
    }

    private function verifyTransaction(string $transactionId, string $amount): string
    {
        $profileId = (string) (config('services.payment.anajakpay.profile_id') ?: config('services.payment.khqr.profile_id'));
        $endpoint = in_array(
            Payment::where('payment_reference', $transactionId)->value('provider'),
            ['aba_pay', 'khqr_checkout'],
            true,
        )
            ? 'check-transv2-khqrcc' : 'check-trans';
        $url = rtrim(config('services.payment.khqr.base_url'), '/') . '/' . rawurlencode($profileId)
            . '/payment-gateway/v1/payments/' . $endpoint;
        try {
            $response = Http::asForm()->acceptJson()
                ->timeout((int) config('services.payment.khqr.timeout', 15))
                ->post($url, [
                    'transaction_id' => $transactionId,
                    'hash' => sha1((string) (config('services.payment.anajakpay.secret') ?: config('services.payment.khqr.secret_key')) . $transactionId),
                ]);
        } catch (ConnectionException $exception) {
            report($exception);
            return 'pending';
        }

        $body = $response->json();
        $providerAmount = data_get($body, 'data.amount');
        if ($endpoint === 'check-transv2-khqrcc' && $providerAmount === null) {
            $providerAmount = data_get($body, 'data.success_amount');
        }
        if (!$response->successful() || !is_array($body) || (int) data_get($body, 'responseCode', 1) !== 0
            || strtolower((string) data_get($body, 'data.status')) !== 'success'
            || ($providerAmount !== null && number_format((float) $providerAmount, 2, '.', '') !== $amount)) {
            return 'pending';
        }

        if ($endpoint === 'check-transv2-khqrcc'
            && (!is_numeric($providerAmount)
                || (data_get($body, 'data.transaction_id') !== null
                    && (string) data_get($body, 'data.transaction_id') !== $transactionId)
                || (data_get($body, 'data.currency') !== null
                    && strtoupper((string) data_get($body, 'data.currency')) !== 'USD'))) {
            return 'pending';
        }

        return 'paid';
    }

    public function verifyTransactionStatus(string $transactionId, string $amount): string
    {
        return $this->verifyTransaction($transactionId, $amount);
    }
}
