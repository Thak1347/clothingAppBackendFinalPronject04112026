<?php
namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\DeviceToken\StoreDeviceTokenRequest;
use Illuminate\Http\Request;

class DeviceTokenController extends Controller
{
    public function store(StoreDeviceTokenRequest $request)
    {
        $request->user()->deviceTokens()->updateOrCreate(
            ['token' => $request->token],
            ['platform' => $request->platform],
        );
        return response()->json(['message' => 'Saved.']);
    }

    public function destroy(Request $request)
    {
        $request->user()->deviceTokens()
            ->where('token', $request->input('token'))->delete();
        return response()->noContent();
    }
}
