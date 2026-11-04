<?php
namespace App\Providers;

use App\Models\Address;
use App\Models\Order;
use App\Policies\AddressPolicy;
use App\Policies\OrderPolicy;
use App\Policies\ReceiptPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use App\Services\Payments\KhqrPaymentProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(KhqrPaymentProvider::class, fn () => new KhqrPaymentProvider());
    }

    public function boot(): void
    {
        Gate::policy(Address::class, AddressPolicy::class);
        Gate::policy(Order::class, OrderPolicy::class);
        Gate::define('viewReceipt', [ReceiptPolicy::class, 'view']);

        RateLimiter::for('api', fn(Request $r) =>
            Limit::perMinute(120)->by($r->user()?->id ?: $r->ip()));

        RateLimiter::for('login', fn(Request $r) =>
            Limit::perMinute(10)->by($r->input('email') . '|' . $r->ip()));
    }
}
