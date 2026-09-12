<?php

namespace App\Http\Controllers;

use App\Actions\CancelOrderAction;
use App\Actions\ConfirmPaymentAction;
use App\Enums\OrderStatus;
use App\Models\Order;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Panel de pedidos (Spec 08 fase 08.c, reglas 159 a 162 y 165).
 *
 * Delgado a propósito: los destacados se derivan en el modelo y las decisiones
 * de negocio viven en `ConfirmPaymentAction` y `CancelOrderAction`, que ya
 * validan por su cuenta (la regla 159 incluida).
 */
class OrderController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Order::class);

        $estado = $this->estadoFiltrado($request);
        $busqueda = trim((string) $request->query('q', ''));

        $pedidos = Order::query()
            ->with(['lines.product', 'auditLogs'])
            ->when($estado, fn ($query, OrderStatus $filtro) => $query->byStatus($filtro))
            ->when($busqueda !== '', function ($query) use ($busqueda): void {
                // Búsqueda por email o por ID (regla 161).
                $query->where(function ($sub) use ($busqueda): void {
                    $sub->where('customer_email', 'ilike', '%'.$busqueda.'%');

                    if (ctype_digit($busqueda)) {
                        $sub->orWhere('id', (int) $busqueda);
                    }
                });
            })
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.pedidos.index', [
            'pedidos' => $pedidos,
            'estados' => OrderStatus::cases(),
            'estadoActual' => $estado,
            'busqueda' => $busqueda,
        ]);
    }

    public function show(Order $pedido): View
    {
        Gate::authorize('view', $pedido);

        $pedido->load(['lines.product', 'auditLogs']);

        return view('admin.pedidos.show', [
            'pedido' => $pedido,
            // Regla 162: la traza se muestra solo a admin.
            'auditoria' => Gate::allows('viewAudit', $pedido) ? $pedido->auditLogs : null,
        ]);
    }

    /** Regla 159: confirmación manual de una transferencia. */
    public function confirmPayment(Order $pedido, ConfirmPaymentAction $action): RedirectResponse
    {
        Gate::authorize('confirmPayment', $pedido);

        try {
            $action->execute($pedido, 'manual');
        } catch (DomainException $e) {
            return back()->withErrors(['pedido' => $e->getMessage()]);
        }

        return back()->with('status', 'Pago confirmado. El stock quedó descontado.');
    }

    /** Regla 165: cancelación, con restitución si el pedido ya estaba pagado. */
    public function cancel(Order $pedido, CancelOrderAction $action): RedirectResponse
    {
        Gate::authorize('cancel', $pedido);

        try {
            $action->execute($pedido);
        } catch (DomainException $e) {
            return back()->withErrors(['pedido' => $e->getMessage()]);
        }

        return back()->with('status', 'Pedido cancelado.');
    }

    private function estadoFiltrado(Request $request): ?OrderStatus
    {
        $estado = $request->query('estado');

        return is_string($estado) ? OrderStatus::tryFrom($estado) : null;
    }
}
