<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderActivity extends Model
{
    protected $fillable = ['order_id', 'actor_id', 'event', 'from_status', 'to_status', 'details'];
    protected $casts = ['details' => 'array'];

    public static function record(Order $order, ?int $actorId, string $event, ?string $from = null, ?string $to = null, array $details = []): self
    {
        return $order->activities()->create(['actor_id' => $actorId, 'event' => $event, 'from_status' => $from, 'to_status' => $to, 'details' => $details ?: null]);
    }

    public function order() { return $this->belongsTo(Order::class); }
    public function actor() { return $this->belongsTo(User::class, 'actor_id'); }
}
