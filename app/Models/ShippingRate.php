<?php

namespace App\Models;

use Database\Factories\ShippingRateFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShippingRate extends Model
{
    /** @use HasFactory<ShippingRateFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'cp',
        'costo_cents',
        'activo',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'costo_cents' => 'integer',
            'activo' => 'boolean',
        ];
    }

    /**
     * @param  Builder<ShippingRate>  $query
     * @return Builder<ShippingRate>
     */
    public function scopeActivo(Builder $query): Builder
    {
        return $query->where('activo', true);
    }
}
