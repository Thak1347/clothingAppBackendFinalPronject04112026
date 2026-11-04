<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'product_variant_id',
        'product_name_snapshot',
        'asset_key_snapshot',
        'size_snapshot',
        'color_snapshot',
        'quantity',
        'unit_price_minor',
        'line_total_minor',
    ];
    protected $casts = [
        'quantity' => 'integer',
        'unit_price_minor' => 'integer',
        'line_total_minor' => 'integer',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
    public function variant()
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }
}
