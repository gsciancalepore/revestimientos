<?php

namespace App\Models;

use App\Enums\ProductSaleUnit;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Builder;
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
        'slug',
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

    /**
     * Route-model binding público por slug (Spec 04, regla 71).
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function tieneOfertaActiva(): bool
    {
        return $this->precio_oferta_cents !== null && $this->precio_oferta_cents < $this->precio_cents;
    }

    public function descuentoPorcentaje(): ?int
    {
        if (! $this->tieneOfertaActiva()) {
            return null;
        }

        return (int) round(100 * ($this->precio_cents - $this->precio_oferta_cents) / $this->precio_cents);
    }

    /**
     * @param  Builder<Product>  $query
     * @return Builder<Product>
     */
    public function scopeActivo(Builder $query): Builder
    {
        return $query->where('activo', true);
    }

    /**
     * @param  Builder<Product>  $query
     * @return Builder<Product>
     */
    public function scopeConOferta(Builder $query): Builder
    {
        return $query->whereNotNull('precio_oferta_cents')
            ->whereColumn('precio_oferta_cents', '<', 'precio_cents');
    }

    /**
     * @param  Builder<Product>  $query
     * @return Builder<Product>
     */
    public function scopeDeCategoria(Builder $query, int|Category $category): Builder
    {
        return $query->where('category_id', $category instanceof Category ? $category->id : $category);
    }

    /**
     * Coincidencia parcial (ILIKE) sobre nombre, código y marca.
     *
     * @param  Builder<Product>  $query
     * @return Builder<Product>
     */
    public function scopeBuscar(Builder $query, string $termino): Builder
    {
        return $query->where(function (Builder $query) use ($termino): Builder {
            return $query->where('name', 'ilike', "%{$termino}%")
                ->orWhere('codigo', 'ilike', "%{$termino}%")
                ->orWhere('marca', 'ilike', "%{$termino}%");
        });
    }

    /**
     * @param  Builder<Product>  $query
     * @return Builder<Product>
     */
    public function scopePorMarca(Builder $query, string $marca): Builder
    {
        return $query->where('marca', $marca);
    }

    /**
     * Filtro por clave de `specs` JSONB (`specs->>'clave' = valor`).
     *
     * @param  Builder<Product>  $query
     * @return Builder<Product>
     */
    public function scopeSpecsValor(Builder $query, string $clave, string $valor): Builder
    {
        return $query->where('specs->'.$clave, $valor);
    }
}
