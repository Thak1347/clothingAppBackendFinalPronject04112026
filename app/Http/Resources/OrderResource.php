<?php
namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $paidPaymentStatuses = ['paid', 'partially_paid', 'refund_pending', 'partially_refunded', 'refunded'];
        $paymentAmountReceived = in_array($this->payment?->status?->value, $paidPaymentStatuses, true) ? (int) ($this->payment?->amount_minor ?? 0) : 0;
        $amountPaid = min((int) $this->total_minor, $paymentAmountReceived + (int) $this->cod_balance_paid_minor);
        return [
            'id' => $this->id,
            'number' => $this->order_number,
            'fulfillment_status' => $this->fulfillment_status?->value,
            'payment_status' => $this->payment_status?->value,
            'payment_method' => $this->payment_method ?? $this->payment?->provider,
            'cod_verification_status' => $this->cod_verification_status,
            'cod_review_reason' => $this->cod_review_reason,
            'cancellation_review_reason' => $this->cancellation_review_reason,
            'cod_reviewed_at' => $this->cod_reviewed_at?->toISOString(),
            'cod_terms_accepted_at' => $request->user()?->role?->value === 'admin' || $request->user()?->id === $this->user_id ? $this->cod_terms_accepted_at?->toISOString() : null,
            'amount_paid_minor' => $amountPaid,
            'remaining_balance_minor' => max(0, (int) $this->total_minor - $amountPaid),
            'cod_balance_paid_minor' => (int) $this->cod_balance_paid_minor,
            'refunded_minor' => (int) $this->refunded_minor,
            'refund_pending_minor' => (int) $this->refund_pending_minor,
            'cod_balance_paid_at' => $this->cod_balance_paid_at?->toISOString(),
            'cod_customer' => $this->when(($request->user()?->role?->value === 'admin' || $request->user()?->id === $this->user_id) && $this->payment_method === 'cash', [
                'name' => $this->cod_customer_name,
                'phone' => $this->cod_phone,
                'email' => $this->cod_email,
                'telegram' => $this->cod_telegram,
                'address' => $this->cod_address,
                'home_photo_url' => $request->user()?->role?->value === 'admin' && $this->cod_home_photo_path ? route('admin.cod-document', ['order' => $this->id]) : null,
            ]),
            'subtotal_minor' => (int) $this->subtotal_minor,
            'discount_minor' => (int) $this->discount_minor,
            'delivery_fee_minor' => (int) $this->delivery_fee_minor,
            'total_minor' => (int) $this->total_minor,
            'currency' => $this->currency,
            'address' => [
                'recipient_name' => $this->ship_recipient_name,
                'phone' => $this->ship_phone,
                'address_line' => $this->ship_address_line,
                'province' => $this->ship_province,
            ],
            'items' => OrderItemResource::collection($this->whenLoaded('items')),
            'payment' => new PaymentResource($this->whenLoaded('payment')),
            'activities' => $this->whenLoaded('activities', fn () => $this->activities->map(fn ($activity) => [
                'event' => $activity->event, 'from_status' => $activity->from_status, 'to_status' => $activity->to_status,
                'details' => $activity->details, 'actor_name' => $activity->actor?->name,
                'created_at' => $activity->created_at?->toISOString(),
            ])->values()),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
