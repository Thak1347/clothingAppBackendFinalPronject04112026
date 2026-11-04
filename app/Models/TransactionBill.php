<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TransactionBill extends Model
{
    use HasFactory;

    protected $fillable = [
        'payment_id',
        'bill_number',
        'receipt_reference',
        'issued_at',
        'payload',
    ];
    protected $casts = [
        'issued_at' => 'datetime',
        'payload' => 'array',
    ];

    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }
}
