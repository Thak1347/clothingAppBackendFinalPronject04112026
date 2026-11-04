<?php
namespace App\Http\Requests\Address;

class UpdateAddressRequest extends StoreAddressRequest
{
    public function rules(): array
    {
        $rules = parent::rules();
        foreach ($rules as $k => $v) {
            array_unshift($rules[$k], 'sometimes');
        }
        return $rules;
    }
}
