<?php

namespace App\Models;

use Database\Factories\OrderLineFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property array<string, mixed>|null $specs
 */
class OrderLine extends Model
{
    /** @use HasFactory<OrderLineFactory> */
    use HasFactory;

    protected $fillable = [
        'order_id',
        'product_id',
        'product_name',
        'product_codigo',
        'marca',
        'unidad_venta',
        'm2_por_caja',
        'cantidad',
        'precio_unitario_cents',
        'subtotal_cents',
        'specs',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'm2_por_caja' => 'string',
            'cantidad' => 'integer',
            'precio_unitario_cents' => 'integer',
            'subtotal_cents' => 'integer',
            'specs' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
