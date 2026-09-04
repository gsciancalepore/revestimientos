<?php

namespace App\Http\Requests\Checkout;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCheckoutRequest extends FormRequest
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
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_email' => ['required', 'string', 'email', 'max:255'],
            'customer_phone' => ['required', 'string', 'max:50'],
            'shipping_cp' => ['required', 'string', 'regex:/^[0-9]{4}$/'],
            'shipping_address' => ['nullable', 'string', 'max:500'],
            'payment_method' => ['required', 'string', Rule::in(['transferencia', 'mercadopago'])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'customer_name.required' => 'El nombre es obligatorio.',
            'customer_email.required' => 'El email es obligatorio.',
            'customer_email.email' => 'El email debe ser válido.',
            'customer_phone.required' => 'El teléfono es obligatorio.',
            'shipping_cp.required' => 'El código postal es obligatorio.',
            'shipping_cp.regex' => 'El código postal debe tener 4 dígitos.',
            'payment_method.required' => 'El medio de pago es obligatorio.',
            'payment_method.in' => 'El medio de pago no es válido.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'customer_name' => $this->input('customer_name') !== null ? trim((string) $this->input('customer_name')) : null,
            'customer_email' => $this->input('customer_email') !== null ? trim((string) $this->input('customer_email')) : null,
            'customer_phone' => $this->input('customer_phone') !== null ? trim((string) $this->input('customer_phone')) : null,
            'shipping_cp' => $this->input('shipping_cp') !== null ? trim((string) $this->input('shipping_cp')) : null,
            'shipping_address' => $this->input('shipping_address') !== null ? trim((string) $this->input('shipping_address')) : null,
            'payment_method' => $this->input('payment_method') !== null ? trim((string) $this->input('payment_method')) : null,
        ]);
    }
}
