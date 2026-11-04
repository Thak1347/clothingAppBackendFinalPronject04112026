<?php
namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreVariantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'sku' => ['required', 'string', 'max:100', 'unique:product_variants,sku'],
            'size' => ['required', 'string', 'max:30'],
            'color' => ['required', 'string', 'max:50'],
            'stock_quantity' => ['required', 'integer', 'min:0'],
        ];
    }
}
