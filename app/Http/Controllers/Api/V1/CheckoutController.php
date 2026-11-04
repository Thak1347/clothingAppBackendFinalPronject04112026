<?php
namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Checkout\PreviewCheckoutRequest;
use App\Services\CheckoutService;

class CheckoutController extends Controller
{
    public function preview(PreviewCheckoutRequest $request, CheckoutService $svc)
    {
        return response()->json(
            $svc->preview($request->integer('address_id'), $request->input('items'))
        );
    }
}
