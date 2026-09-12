<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Order;
use App\Models\User;
use DomainException;

/**
 * Matriz de permisos de la Spec 08 fase 08.c (reglas 160 a 165).
 *
 * El depósito ve `/admin/despacho` y mueve `paid → shipped → delivered`, pero no
 * entra al listado de pedidos: no necesita ver plata. El vendedor es el inverso:
 * ve los pedidos y no cobra ni cancela.
 */
class OrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->esAlguno($user, UserRole::Admin, UserRole::Vendedor);
    }

    public function view(User $user, Order $order): bool
    {
        return $this->esAlguno($user, UserRole::Admin, UserRole::Vendedor);
    }

    /**
     * Regla 162: la traza de auditoría es solo para admin.
     *
     * `audit_logs` guarda `ip_address` y `user_agent`; que el vendedor vea quién
     * confirmó cada cobro y desde qué IP es una decisión que la spec toma
     * explícitamente, no de arrastre.
     */
    public function viewAudit(User $user, Order $order): bool
    {
        return $this->esAlguno($user, UserRole::Admin);
    }

    /** Regla 160: el vendedor ve los pedidos pero no confirma cobros. */
    public function confirmPayment(User $user, Order $order): bool
    {
        return $this->esAlguno($user, UserRole::Admin);
    }

    /** Regla 165: todas las cancelaciones son de admin. */
    public function cancel(User $user, Order $order): bool
    {
        return $this->esAlguno($user, UserRole::Admin);
    }

    /** Regla 163: la vista depósito es de admin y depósito. */
    public function viewDispatch(User $user): bool
    {
        return $this->esAlguno($user, UserRole::Admin, UserRole::Deposito);
    }

    /** Regla 164: `paid → shipped` y `shipped → delivered`. */
    public function dispatch(User $user, Order $order): bool
    {
        return $this->esAlguno($user, UserRole::Admin, UserRole::Deposito);
    }

    private function esAlguno(User $user, UserRole ...$roles): bool
    {
        try {
            return in_array($user->role(), $roles, true);
        } catch (DomainException) {
            // Un usuario sin rol (o con más de uno) no autoriza nada: mismo criterio
            // que las Policies de la Spec 01.
            return false;
        }
    }
}
