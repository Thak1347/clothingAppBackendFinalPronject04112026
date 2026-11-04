<?php
namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'provider' => $this->provider,
            'status' => $this->status?->value,
            'reference' => $this->payment_reference,
            'amount_minor' => (int) $this->amount_minor,
            'currency' => $this->currency,
            'provider_metadata' => $request->user()?->role?->value === 'admin' ? $this->provider_metadata : array_intersect_key(is_array($this->provider_metadata) ? $this->provider_metadata : [], array_flip(['qr_url', 'checkout_url'])),
            'qr_url' => $request->user()?->role?->value === 'admin' ? data_get($this->provider_metadata, 'qr_url') : null,
            'paid_at' => $this->verified_at?->toISOString(),
        ];
    }
}
