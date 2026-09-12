<?php

namespace App\Http\Controllers;

use App\Actions\TransitionOrderStatusAction;
use App\Enums\OrderStatus;
use App\Models\Order;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Vista depósito (Spec 08 fase 08.c, reglas 163 y 164).
 *
 * Dos solapas, ambas **ordenadas por antigüedad y sin importes**: el depósito
 * arma envíos, no necesita ver plata. Sin la segunda solapa, la transición
 * `shipped → delivered` que la regla 164 le permite no tendría desde dónde
 * dispararse, porque el depósito no entra a `/admin/pedidos`.
 */
class DispatchController extends Controller
{
    private const SOLAPAS = ['preparar', 'despachados'];

    public function index(Request $request): View
    {
        Gate::authorize('viewDispatch', Order::class);

        $solapa = in_array($request->query('solapa'), self::SOLAPAS, true)
            ? (string) $request->query('solapa')
            : 'preparar';

        $estado = $solapa === 'preparar' ? OrderStatus::Paid : OrderStatus::Shipped;

        return view('admin.despacho.index', [
            'solapa' => $solapa,
            'pedidos' => Order::query()
                ->with('lines')
                ->byStatus($estado)
                ->oldest('id')
                ->paginate(20)
                ->withQueryString(),
        ]);
    }

    public function ship(Order $pedido, TransitionOrderStatusAction $action): RedirectResponse
    {
        return $this->transicionar($pedido, OrderStatus::Shipped, $action, 'Pedido marcado como despachado.');
    }

    public function deliver(Order $pedido, TransitionOrderStatusAction $action): RedirectResponse
    {
        return $this->transicionar($pedido, OrderStatus::Delivered, $action, 'Pedido marcado como entregado.');
    }

    private function transicionar(
        Order $pedido,
        OrderStatus $destino,
        TransitionOrderStatusAction $action,
        string $mensaje,
    ): RedirectResponse {
        Gate::authorize('dispatch', $pedido);

        try {
            $action->execute($pedido, $destino);
        } catch (DomainException $e) {
            return back()->withErrors(['pedido' => $e->getMessage()]);
        }

        return back()->with('status', $mensaje);
    }
}
