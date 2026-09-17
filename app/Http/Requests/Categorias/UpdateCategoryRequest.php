<?php

namespace App\Http\Requests\Categorias;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCategoryRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('categories', 'name')->ignore($this->route('category'))],
            'slug' => ['nullable', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('categories', 'slug')->ignore($this->route('category'))],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }

    protected function prepareForValidation(): void
    {
        // HIG-15: igual que en el alta — el campo vaciado se normaliza a 0.
        if ($this->exists('sort_order') && $this->input('sort_order') === null) {
            $this->merge(['sort_order' => 0]);
        }
    }
}
