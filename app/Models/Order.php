<?php

namespace App\Models;

use App\Enums\OrderStatus;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property-read Collection<int, OrderLine> $lines
 * @property OrderStatus $status
 */
class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    protected $fillable = [
        'status',
        'customer_name',
        'customer_email',
        'customer_phone',
        'shipping_cp',
        'shipping_address',
        'shipping_cost_cents',
        'subtotal_cents',
        'total_cents',
        'payment_method',
        'mp_preference_id',
        'mp_init_point',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'shipping_cost_cents' => 'integer',
            'subtotal_cents' => 'integer',
            'total_cents' => 'integer',
            'mp_preference_id' => 'string',
            'mp_init_point' => 'string',
        ];
    }

    /**
     * @return HasMany<OrderLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(OrderLine::class);
    }

    /**
     * @param  Builder<Order>  $query
     * @return Builder<Order>
     */
    public function scopePendingPayment(Builder $query): Builder
    {
        return $query->where('status', OrderStatus::PendingPayment);
    }

    /**
     * @param  Builder<Order>  $query
     * @return Builder<Order>
     */
    public function scopePaid(Builder $query): Builder
    {
        return $query->where('status', OrderStatus::Paid);
    }

    /**
     * @param  Builder<Order>  $query
     * @return Builder<Order>
     */
    public function scopeByEmail(Builder $query, string $email): Builder
    {
        return $query->where('customer_email', $email);
    }

    /**
     * @param  Builder<Order>  $query
     * @return Builder<Order>
     */
    public function scopeByStatus(Builder $query, OrderStatus $status): Builder
    {
        return $query->where('status', $status);
    }
}
