<?php

namespace App\Http\Requests\Cart;

use Illuminate\Foundation\Http\FormRequest;

class AddToCartRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'producto' => ['required', 'string', 'exists:products,slug'],
            'cantidad' => ['nullable', 'integer', 'min:1'],
            'superficie' => ['nullable', 'numeric', 'min:0.01'],
            'desperdicio' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'producto.required' => 'El producto es obligatorio.',
            'producto.exists' => 'El producto no existe.',
            'cantidad.integer' => 'La cantidad debe ser un número entero.',
            'cantidad.min' => 'La cantidad debe ser al menos 1.',
            'superficie.numeric' => 'La superficie debe ser un número.',
            'superficie.min' => 'La superficie debe ser mayor a 0.',
        ];
    }
}
