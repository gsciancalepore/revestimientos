<?php

namespace App\Http\Requests\Productos;

use App\Enums\ProductSaleUnit;
use App\Models\Category;
use App\Services\ProductSpecs;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductRequest extends FormRequest
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
            'codigo' => ['required', 'string', 'max:255', 'unique:products,codigo'],
            'descripcion' => ['nullable', 'string'],
            'precio_cents' => ['required', 'integer', 'min:0'],
            'precio_oferta_cents' => ['nullable', 'integer', 'min:0'],
            'unidad_venta' => ['required', Rule::enum(ProductSaleUnit::class)],
            'm2_por_caja' => ['required_if:unidad_venta,m2', 'nullable', 'decimal:2', 'min:0'],
            'stock' => ['required', 'integer', 'min:0'],
            'activo' => ['sometimes', 'boolean'],
            'imagenes' => ['nullable', 'array'],
            'specs' => ['nullable', 'array', $this->validateSpecsKeys()],
            'specs.*' => ['nullable', 'string'],
        ];
    }

    private function validateSpecsKeys(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail): void {
            $category = Category::where('id', $this->input('category_id'))->first();

            if ($category === null) {
                return;
            }

            $allowed = app(ProductSpecs::class)->allowedKeysFor($category);

            $unknown = array_diff(array_keys($value ?? []), $allowed);

            if ($unknown !== []) {
                $fail('Los atributos no están permitidos para la familia "'.$category->name.'".');
            }
        };
    }
}
