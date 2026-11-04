<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\{
    AuthController,
    ProfileController,
    AvatarController,
    CategoryController,
    ProductController,
    AddressController,
    CheckoutController,
    ReceiptController,
    DeviceTokenController
};
use App\Http\Controllers\Api\V1\OrderController;
use App\Http\Controllers\Api\V1\PaymentController;
use App\Http\Controllers\Api\V1\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Api\V1\Admin\PaymentController as AdminPaymentController;
use App\Http\Controllers\Api\V1\Admin\CategoryController as AdminCategoryController;
use App\Http\Controllers\Api\V1\Admin\ProductController as AdminProductController;
use App\Http\Controllers\Api\V1\Admin\ProductVariantController as AdminProductVariantController;
use App\Http\Controllers\Api\V1\Admin\DashboardController as AdminDashboardController;

Route::prefix('v1')->group(function () {

    // Public
    Route::post('auth/register', [AuthController::class, 'register']);
    Route::post('auth/login', [AuthController::class, 'login']);
    Route::get('categories', [CategoryController::class, 'index']);
    Route::get('products', [ProductController::class, 'index']);
    Route::get('products/{product}', [ProductController::class, 'show']);
    // Provider-signed callback; authorization is enforced by the SHA-256 signature.
    Route::post('payments/callback', [PaymentController::class, 'callback']);
    // Hosted checkout success_url may receive the server callback POST as well as browser GET.
    Route::post('payments/khqr/success', [PaymentController::class, 'callback']);
    // Browser return is informational only. Payment status is checked server-side.
    Route::get('payments/khqr/success', [PaymentController::class, 'successReturn']);

    // Authenticated (Sanctum)
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::get('me', [ProfileController::class, 'show']);
        Route::patch('me', [ProfileController::class, 'update']);
        Route::patch('me/password', [ProfileController::class, 'updatePassword']);
        Route::post('me/avatar', [AvatarController::class, 'store']);
        Route::delete('me/avatar', [AvatarController::class, 'destroy']);

        Route::apiResource('addresses', AddressController::class);
        Route::post('addresses/{address}/set-default', [AddressController::class, 'setDefault']);

        Route::post('checkout/preview', [CheckoutController::class, 'preview']);
        Route::post('orders', [OrderController::class, 'store']);
        Route::get('orders', [OrderController::class, 'index']);
        Route::get('orders/{order}', [OrderController::class, 'show']);
        Route::get('orders/{order}/payment-status', [OrderController::class, 'paymentStatus']);
        Route::post('orders/{order}/cancel', [OrderController::class, 'cancel']);
        Route::post('orders/{order}/cod-details', [OrderController::class, 'updateCodDetails']);
        Route::post('orders/{order}/switch-to-aba', [OrderController::class, 'switchCodToAba']);
        Route::get('orders/{order}/receipt', [ReceiptController::class, 'show']);

        Route::post('payments/{payment}/initiate', [PaymentController::class, 'initiate']);

        Route::post('device-tokens', [DeviceTokenController::class, 'store']);
        Route::delete('device-tokens', [DeviceTokenController::class, 'destroy']);

        // Admin
        Route::middleware('admin')->prefix('admin')->group(function () {
            Route::get('dashboard', AdminDashboardController::class);

            // Orders
            Route::get('orders', [AdminOrderController::class, 'index']);
            Route::patch('orders/{order}/fulfillment', [AdminOrderController::class, 'updateFulfillment']);
            Route::patch('orders/{order}/cod-review', [AdminOrderController::class, 'reviewCod']);
            Route::patch('orders/{order}/cancellation-review', [AdminOrderController::class, 'reviewCancellation']);
            Route::patch('orders/{order}/cod-approval', [AdminOrderController::class, 'approveCodOrder']);
            Route::post('orders/{order}/cod-balance', [AdminOrderController::class, 'recordCodBalance']);
            Route::post('orders/{order}/refund', [AdminOrderController::class, 'recordRefund']);
            Route::get('orders/{order}/cod-document', [AdminOrderController::class, 'codDocument'])->name('admin.cod-document');

            // Payments
            Route::get('payments', [AdminPaymentController::class, 'index']);

            // Categories — admin CRUD
            Route::apiResource('categories', AdminCategoryController::class)->except(['show']);

            // Products — admin CRUD
            Route::post('products/{product}/images', [AdminProductController::class, 'updateImages']);
            Route::apiResource('products', AdminProductController::class)->except(['show']);

            // Product variants — nested under product
            Route::apiResource('products.variants', AdminProductVariantController::class)
                ->except(['show']);
        });
    });
});
