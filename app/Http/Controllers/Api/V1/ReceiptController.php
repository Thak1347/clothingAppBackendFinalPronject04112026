<?php
namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ReceiptResource;
use App\Models\Order;

class ReceiptController extends Controller
{
    public function show(Order $order)
    {
        $this->authorize('viewReceipt', $order);
        $bill = $order->payment?->transactionBill;
        abort_unless($bill, 404, 'Receipt not issued yet.');
        return new ReceiptResource($bill);
    }
}
