<?php

namespace App\Http\Requests\Categorias;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCategoryRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('categories', 'name')],
            'slug' => ['nullable', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('categories', 'slug')],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }

    protected function prepareForValidation(): void
    {
        // HIG-15: vaciar el campo manda `sort_order => null`
        // (`ConvertEmptyStringsToNull`); la clave existe en `validated()` con valor
        // null, así que el default del controlador nunca aplica y el null llegaba
        // al `int` no-nullable de la Action → TypeError → 500.
        if ($this->exists('sort_order') && $this->input('sort_order') === null) {
            $this->merge(['sort_order' => 0]);
        }
    }
}
