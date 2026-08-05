<?php

namespace App\Models;

use App\Enums\ProductSaleUnit;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property ProductSaleUnit $unidad_venta
 * @property array<string, mixed>|null $imagenes
 * @property array<string, mixed>|null $specs
 */
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'category_id',
        'name',
        'marca',
        'codigo',
        'descripcion',
        'precio_cents',
        'precio_oferta_cents',
        'unidad_venta',
        'm2_por_caja',
        'stock',
        'activo',
        'imagenes',
        'specs',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'precio_cents' => 'integer',
            'precio_oferta_cents' => 'integer',
            'unidad_venta' => ProductSaleUnit::class,
            'm2_por_caja' => 'string',
            'stock' => 'integer',
            'activo' => 'boolean',
            'imagenes' => 'array',
            'specs' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function isM2Mode(): bool
    {
        return $this->unidad_venta === ProductSaleUnit::M2;
    }

    /**
     * Precio por caja (solo modo m², ADR-003). NULL en modo unidad.
     */
    public function precioCajaCents(): ?int
    {
        if (! $this->isM2Mode() || $this->m2_por_caja === null) {
            return null;
        }

        return (int) round((float) bcmul((string) $this->precio_cents, (string) $this->m2_por_caja, 2));
    }
}
