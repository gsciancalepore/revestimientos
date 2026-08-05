<?php

namespace App\Services;

use App\Models\Category;

/**
 * Allowed `specs` keys per product family (Spec 03).
 *
 * Keys are validated by the product's category. Categories created by the
 * admin that are not in this map get no allowed keys (specs must be empty).
 */
class ProductSpecs
{
    /**
     * Slug de la categoría → claves permitidas en `specs`.
     *
     * @var array<string, list<string>>
     */
    private const ALLOWED = [
        'porcelanatos' => ['medida', 'color', 'acabado', 'espesor', 'rectificado', 'piezas_por_caja', 'uso', 'aplicacion'],
        'ceramicas' => ['medida', 'color', 'acabado', 'espesor', 'piezas_por_caja', 'uso', 'aplicacion'],
        'pastinas' => ['color', 'rendimiento', 'peso'],
        'adhesivos' => ['rendimiento', 'tiempo_de_fraguado', 'peso'],
    ];

    /**
     * @return list<string>
     */
    public function allowedKeysFor(Category $category): array
    {
        return self::ALLOWED[$category->slug] ?? [];
    }
}
