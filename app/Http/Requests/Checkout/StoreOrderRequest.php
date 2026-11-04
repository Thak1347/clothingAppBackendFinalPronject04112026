<?php
namespace App\Http\Requests\Checkout;

use Illuminate\Foundation\Http\FormRequest;

class StoreOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }
    public function rules(): array
    {
        return [
            'address_id' => ['required', 'integer', 'exists:addresses,id'],
            'idempotency_key' => ['required', 'string', 'max:100'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_variant_id' => ['required', 'integer', 'exists:product_variants,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:99'],
            'payment_method' => ['sometimes', 'string', 'in:cash,aba_pay,khqr,khqr_checkout'],
            'cod.name' => ['required_if:payment_method,cash', 'string', 'max:120'],
            'cod.phone' => ['required_if:payment_method,cash', 'string', 'max:30'],
            'cod.email' => ['required_if:payment_method,cash', 'email', 'max:255'],
            'cod.telegram' => ['required_if:payment_method,cash', 'string', 'max:120'],
            'cod.address' => ['required_if:payment_method,cash', 'string', 'max:500'],
            'cod.home_photo' => ['required_if:payment_method,cash', 'image', 'max:5120'],
            'cod.terms_accepted' => ['exclude_unless:payment_method,cash', 'accepted'],
        ];
    }
}
