<?php
namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateVariantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('variant')->id ?? $this->route('variant');

        return [
            'sku' => [
                'sometimes',
                'string',
                'max:100',
                Rule::unique('product_variants', 'sku')->ignore($id)
            ],
            'size' => ['sometimes', 'string', 'max:30'],
            'color' => ['sometimes', 'string', 'max:50'],
            'stock_quantity' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
