<?php
namespace App\Models;

use App\Enums\FulfillmentStatus;
use App\Enums\PaymentStatus;
use App\Enums\NotificationChannel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'address_id',
        'order_number',
        'ship_recipient_name',
        'ship_phone',
        'ship_address_line',
        'ship_province',
        'ship_latitude',
        'ship_longitude',
        'subtotal_minor',
        'delivery_fee_minor',
        'discount_minor',
        'total_minor',
        'currency',
        'fulfillment_status',
        'payment_status',
        'payment_method', 'cod_customer_name', 'cod_phone', 'cod_email', 'cod_telegram',
        'cod_address', 'cod_home_photo_path', 'cod_verification_status', 'cod_verified_at',
        'cod_reviewed_by', 'cod_reviewed_at', 'cod_terms_accepted_at', 'cod_review_reason', 'cod_balance_paid_minor', 'cod_balance_paid_at',
        'cod_balance_received_by', 'inventory_released_at', 'cod_home_photo_delete_at', 'refunded_minor', 'refund_pending_minor',
        'cancellation_requested_from', 'cancellation_review_reason',
    ];
    protected $casts = [
        'subtotal_minor' => 'integer',
        'delivery_fee_minor' => 'integer',
        'discount_minor' => 'integer',
        'total_minor' => 'integer',
        'cod_balance_paid_minor' => 'integer',
        'refunded_minor' => 'integer', 'refund_pending_minor' => 'integer',
        'ship_latitude' => 'float',
        'ship_longitude' => 'float',
        'fulfillment_status' => FulfillmentStatus::class,
        'payment_status' => PaymentStatus::class,
        'cod_verified_at' => 'datetime',
        'cod_reviewed_at' => 'datetime',
        'cod_terms_accepted_at' => 'datetime',
        'cod_balance_paid_at' => 'datetime',
        'inventory_released_at' => 'datetime',
        'cod_home_photo_delete_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
    public function address()
    {
        return $this->belongsTo(Address::class);
    }
    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }
    public function payments()
    {
        return $this->hasMany(Payment::class);
    }
    public function payment()
    {
        return $this->hasOne(Payment::class);
    }
    public function notifications()
    {
        return $this->hasMany(Notification::class);
    }

    public function activities() { return $this->hasMany(OrderActivity::class); }

    public function getCodHomePhotoUrlAttribute(): ?string
    {
        if (!$this->cod_home_photo_path) return null;
        return route('admin.cod-document', ['order' => $this->id]);
    }
}
