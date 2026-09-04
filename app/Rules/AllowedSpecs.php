<?php

namespace App\Rules;

use App\Models\Category;
use App\Services\ProductSpecs;
use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

class AllowedSpecs implements DataAwareRule, ValidationRule
{
    /**
     * @var array<string, mixed>
     */
    protected array $data = [];

    /**
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $categoryId = $this->data['category_id'] ?? null;

        if ($categoryId === null) {
            return;
        }

        $category = Category::where('id', $categoryId)->first();

        if ($category === null) {
            return;
        }

        $allowed = app(ProductSpecs::class)->allowedKeysFor($category);

        $unknown = array_diff(array_keys($value ?? []), $allowed);

        if ($unknown !== []) {
            $fail('Los atributos no están permitidos para la familia "'.$category->name.'".');
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }
}
