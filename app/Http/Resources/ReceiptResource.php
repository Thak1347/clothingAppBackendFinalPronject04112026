<?php
namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReceiptResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'receipt_number' => $this->bill_number,
            'payload' => $this->payload,
            'issued_at' => $this->issued_at?->toISOString(),
        ];
    }
}
