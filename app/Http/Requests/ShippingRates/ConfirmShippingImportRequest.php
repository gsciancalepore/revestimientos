<?php

namespace App\Http\Requests\ShippingRates;

use Illuminate\Foundation\Http\FormRequest;

class ConfirmShippingImportRequest extends FormRequest
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
            'token' => ['required', 'string', 'size:40'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'token.required' => 'El token de importación es obligatorio.',
            'token.size' => 'El token de importación no es válido.',
        ];
    }
}
