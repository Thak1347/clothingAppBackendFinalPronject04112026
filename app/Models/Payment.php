<?php
namespace App\Models;

use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'provider',
        'payment_reference',
        'provider_txn_ref',
        'amount_minor',
        'currency',
        'status',
        'verified_at',
        'provider_metadata',
    ];
    protected $casts = [
        'amount_minor' => 'integer',
        'status' => PaymentStatus::class,
        'verified_at' => 'datetime',
        'provider_metadata' => 'array',
    ];

    public function getPaidAtAttribute(): mixed { return $this->verified_at; }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
    public function transactionBill()
    {
        return $this->hasOne(TransactionBill::class);
    }
}
