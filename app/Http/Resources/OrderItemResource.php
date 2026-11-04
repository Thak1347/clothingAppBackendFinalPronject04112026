<?php
namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_name' => $this->product_name_snapshot,
            'size' => $this->size_snapshot,
            'color' => $this->color_snapshot,
            'unit_price_minor' => (int) $this->unit_price_minor,
            'quantity' => (int) $this->quantity,
            'line_total_minor' => (int) $this->line_total_minor,
        ];
    }
}
