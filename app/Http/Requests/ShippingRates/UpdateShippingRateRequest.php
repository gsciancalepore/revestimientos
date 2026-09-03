<?php

namespace App\Http\Requests\ShippingRates;

use App\Models\ShippingRate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateShippingRateRequest extends FormRequest
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
            'cp' => ['required', 'string', 'regex:/^[0-9]{4}$/'],
            'costo_cents' => ['required', 'integer', 'min:0'],
            'activo' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'cp.required' => 'El código postal es obligatorio.',
            'cp.regex' => 'El código postal debe tener 4 dígitos.',
            'costo_cents.required' => 'El costo es obligatorio.',
            'costo_cents.integer' => 'El costo debe ser un número entero en centavos.',
            'costo_cents.min' => 'El costo no puede ser negativo.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('cp')) {
            $this->merge(['cp' => trim((string) $this->input('cp'))]);
        }
    }

    /**
     * @param  Validator  $validator
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if ($validator->failed()) {
                return;
            }

            /** @var ShippingRate $rate */
            $rate = $this->route('tarifa_envio') ?? $this->route('shipping_rate');
            $cp = (string) $this->input('cp');
            $activo = $this->boolean('activo', true);

            if ($activo && ShippingRate::query()->where('cp', $cp)->activo()->where('id', '!=', $rate->id)->exists()) {
                $validator->errors()->add('cp', 'Ya existe una tarifa activa para este código postal.');
            }
        });
    }
}
