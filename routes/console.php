<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use App\Enums\PaymentStatus;
use App\Models\Order;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::call(function (): void {
    Order::where('payment_status', PaymentStatus::Pending->value)
        ->where('created_at', '<=', now()->subMinutes(15))
        ->with('payment')
        ->chunkById(100, function ($orders): void {
            foreach ($orders as $order) {
                $order->payment?->update(['status' => PaymentStatus::Expired]);
                $order->update(['payment_status' => PaymentStatus::Expired]);
                \App\Models\OrderActivity::record($order, null, 'payment_expired', $order->fulfillment_status?->value, $order->fulfillment_status?->value, ['to_payment_status' => PaymentStatus::Expired->value]);
            }
        });
})->name('expire-stale-payment-orders')->everyMinute()->withoutOverlapping();

Schedule::call(function (): void {
    Order::whereNotNull('cod_home_photo_path')->whereNotNull('cod_home_photo_delete_at')
        ->where('cod_home_photo_delete_at', '<=', now())->chunkById(100, function ($orders): void {
            foreach ($orders as $order) {
                \Illuminate\Support\Facades\Storage::disk('local')->delete($order->cod_home_photo_path);
                $order->update(['cod_home_photo_path' => null, 'cod_home_photo_delete_at' => null]);
            }
        });
})->name('delete-expired-cod-photos')->daily()->withoutOverlapping();
