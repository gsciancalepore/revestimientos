<?php

namespace App\Http\Requests\ShippingRates;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Symfony\Component\HttpFoundation\Response;

class ImportShippingRatesRequest extends FormRequest
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
            'csv' => ['required', 'file', 'mimes:csv,txt', 'max:10240'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'csv.required' => 'El archivo CSV es obligatorio.',
            'csv.file' => 'El archivo CSV no es válido.',
            'csv.mimes' => 'El archivo debe ser CSV o TXT.',
            'csv.max' => 'El archivo no puede superar los 10 MB.',
        ];
    }

    /**
     * La spec fase 2 exige 422 (no redirect) ante cualquier error de validación
     * del importador: se re-renderiza el formulario con los errores.
     */
    protected function failedValidation(Validator $validator): never
    {
        throw new HttpResponseException(
            response(
                view('admin.tarifas-envio.import')->withErrors($validator),
                Response::HTTP_UNPROCESSABLE_ENTITY,
            )
        );
    }
}
