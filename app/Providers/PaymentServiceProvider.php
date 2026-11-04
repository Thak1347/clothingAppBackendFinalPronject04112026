<?php
namespace App\Providers;

use App\Services\Payments\KhqrPaymentProvider;
use App\Services\Payments\PaymentProviderInterface;
use RuntimeException;
use Illuminate\Support\ServiceProvider;

class PaymentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(PaymentProviderInterface::class, function () {
            if (config('services.payment.driver') !== 'khqr') {
                throw new RuntimeException('Unsupported payment driver. Set PAYMENT_DRIVER=khqr.');
            }

            return app(KhqrPaymentProvider::class);
        });
    }
}
