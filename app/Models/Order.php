<?php

namespace App\Models;

use App\Enums\OrderStatus;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * @property-read Collection<int, OrderLine> $lines
 * @property OrderStatus $status
 */
class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    /**
     * Las dos acciones auditadas que destacan un pedido como incidente de pago
     * (regla 161). Las del webhook no son incidentes: registran qué pasó con una
     * notificación, y tres de ellas ni siquiera tienen pedido asociado.
     *
     * @var list<string>
     */
    public const ACCIONES_DE_INCIDENTE = ['order.payment_amount_mismatch', 'order.paid_after_cancel'];

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
     * Traza de auditoría del pedido (ADR-004). Solo se muestra a admin (regla 162).
     *
     * @return MorphMany<AuditLog, $this>
     */
    public function auditLogs(): MorphMany
    {
        return $this->morphMany(AuditLog::class, 'subject')->latest('id');
    }

    /**
     * Regla 145 y 161: el pedido está pagado y alguna de sus líneas apunta a un
     * producto con stock negativo, así que hay que reponerle al fabricante antes
     * de despachar.
     *
     * Se **deriva del estado**, sin columna ni migración: se apaga solo cuando el
     * admin repone, que es la propiedad que lo hace útil como señal.
     */
    public function necesitaReposicion(): bool
    {
        if ($this->status !== OrderStatus::Paid) {
            return false;
        }

        // Si el producto faltara —lo impiden la regla 67 y la FK `restrictOnDelete`—
        // el destacado simplemente no aplica: es una pantalla, no un movimiento de
        // stock. Descontar y restituir sí lanzan ante esa inconsistencia (regla 149).
        return $this->lines->contains(fn (OrderLine $line): bool => ($line->product->stock ?? 0) < 0);
    }

    /**
     * Reglas 151, 157 y 161: se cobró plata que el pedido no puede aceptar —monto
     * distinto del total, o cobro sobre un pedido cancelado—. **No se apaga**, y
     * está bien que no lo haga: esos pedidos quedan trabados a propósito y se
     * resuelven fuera del sistema.
     */
    public function tieneIncidenteDePago(): bool
    {
        return $this->auditLogs
            ->whereIn('action', self::ACCIONES_DE_INCIDENTE)
            ->isNotEmpty();
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
