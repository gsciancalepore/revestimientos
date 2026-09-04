<?php

namespace App\Http\Requests\Productos;

use App\Enums\ProductSaleUnit;
use App\Rules\AllowedSpecs;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'marca' => ['nullable', 'string', 'max:255'],
            'codigo' => ['required', 'string', 'max:255', Rule::unique('products', 'codigo')->ignore($this->route('product'))],
            'descripcion' => ['nullable', 'string'],
            'precio_cents' => ['required', 'integer', 'min:0'],
            'precio_oferta_cents' => ['nullable', 'integer', 'min:0'],
            'unidad_venta' => ['required', Rule::enum(ProductSaleUnit::class)],
            'm2_por_caja' => ['required_if:unidad_venta,m2', 'nullable', 'decimal:2', 'min:0'],
            'stock' => ['required', 'integer', 'min:0'],
            'activo' => ['sometimes', 'boolean'],
            'imagenes' => ['nullable', 'array'],
            'specs' => ['nullable', 'array', new AllowedSpecs],
            'specs.*' => ['nullable', 'string'],
        ];
    }
}
