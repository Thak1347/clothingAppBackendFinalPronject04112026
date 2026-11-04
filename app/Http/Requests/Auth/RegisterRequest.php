<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:30'],
            'phone_country_code' => [
                'nullable',
                'string',
                'regex:/^\+[1-9]\d{0,2}$/',
            ],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }
}
