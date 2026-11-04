<?php
namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Address\StoreAddressRequest;
use App\Http\Requests\Address\UpdateAddressRequest;
use App\Http\Resources\AddressResource;
use App\Models\Address;
use Illuminate\Http\Request;

class AddressController extends Controller
{
    public function index(Request $request)
    {
        return AddressResource::collection(
            $request->user()->addresses()->orderByDesc('is_default')->get()
        );
    }

    public function store(StoreAddressRequest $request)
    {
        $data = $request->validated();
        $data['user_id'] = $request->user()->id;

        if (!empty($data['is_default'])) {
            $request->user()->addresses()->update(['is_default' => false]);
        }
        $address = Address::create($data);
        return new AddressResource($address);
    }

    public function show(Address $address)
    {
        $this->authorize('view', $address);
        return new AddressResource($address);
    }

    public function update(UpdateAddressRequest $request, Address $address)
    {
        $this->authorize('update', $address);
        $address->update($request->validated());
        return new AddressResource($address->fresh());
    }

    public function destroy(Address $address)
    {
        $this->authorize('delete', $address);
        $address->delete();
        return response()->noContent();
    }

    public function setDefault(Request $request, Address $address)
    {
        $this->authorize('update', $address);
        $request->user()->addresses()->update(['is_default' => false]);
        $address->update(['is_default' => true]);
        return new AddressResource($address->fresh());
    }
}
