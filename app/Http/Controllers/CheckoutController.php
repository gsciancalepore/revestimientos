<?php

namespace App\Http\Controllers;

use App\Actions\PlaceOrderAction;
use App\Enums\OrderStatus;
use App\Http\Requests\Checkout\StoreCheckoutRequest;
use App\Logging\EventLog;
use App\Models\Category;
use App\Models\Order;
use App\Services\Cart;
use App\Services\MercadoPagoGateway;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

class CheckoutController extends Controller
{
    public function show(Cart $cart): View|RedirectResponse
    {
        if ($cart->isEmpty()) {
            EventLog::record('checkout.rejected', ['motivo' => 'carrito_vacio']);

            return redirect()->route('carrito.show')->withErrors(['checkout' => 'El carrito está vacío.']);
        }

        if ($cart->hasUnpurchasable()) {
            EventLog::record('checkout.rejected', ['motivo' => 'no_comprable']);

            return redirect()->route('carrito.show')->withErrors(['checkout' => 'El carrito contiene productos no comprables.']);
        }

        return view('checkout.show', [
            'lines' => $cart->lines(),
            'subtotal' => $cart->subtotal(),
            'categorias' => Category::query()->orderBy('sort_order')->get(),
        ]);
    }

    public function store(StoreCheckoutRequest $request, Cart $cart, PlaceOrderAction $action): RedirectResponse
    {
        if ($cart->isEmpty()) {
            EventLog::record('checkout.rejected', ['motivo' => 'carrito_vacio']);

            return redirect()->route('carrito.show')->withErrors(['checkout' => 'El carrito está vacío.']);
        }

        try {
            $order = $action->execute(
                $request->validated('customer_name'),
                $request->validated('customer_email'),
                $request->validated('customer_phone'),
                $request->validated('shipping_cp'),
                $request->validated('shipping_address'),
                $request->validated('payment_method'),
            );
        } catch (\DomainException $e) {
            // Los mensajes de `PlaceOrderAction` son textos fijos, sin datos del cliente.
            EventLog::record('checkout.rejected', ['motivo' => 'dominio', 'detalle' => $e->getMessage()]);

            return back()->withErrors(['checkout' => $e->getMessage()])->withInput();
        }

        session(['order_id' => $order->id]);

        if ($request->validated('payment_method') === 'mercadopago') {
            return $this->derivarAMercadoPago($order);
        }

        $this->registrarDerivacion($order);

        return redirect()->route('checkout.success');
    }

    public function retryMercadoPago(Request $request): RedirectResponse
    {
        $orderId = session('order_id');

        if ($orderId === null) {
            return redirect()->route('carrito.show');
        }

        /** @var Order $order */
        $order = Order::query()->findOrFail($orderId);

        if ($order->payment_method !== 'mercadopago' || $order->status !== OrderStatus::PendingPayment) {
            abort(403);
        }

        return $this->derivarAMercadoPago($order);
    }

    /**
     * Genera la preferencia y redirige a MercadoPago (reglas 124 y 126). El
     * fallo se registra como `checkout.mp_preference_failed`, que enmienda el
     * `Log::error` de la regla 124 (spec observabilidad-01, §Enmiendas).
     */
    private function derivarAMercadoPago(Order $order): RedirectResponse
    {
        try {
            $url = app(MercadoPagoGateway::class)->paymentUrl($order);
        } catch (Throwable $e) {
            EventLog::record('checkout.mp_preference_failed', ['order_id' => $order->id], 'error', $e);

            return redirect()->route('checkout.success')->with('payment_error', 'No pudimos generar el link de pago, reintentá.');
        }

        $this->registrarDerivacion($order);

        return redirect()->away($url);
    }

    /**
     * El cliente fue derivado a pagar (OBS-05.3): distingue "llegó a pagar" de
     * "se cayó antes", no el abandono en MercadoPago de un webhook perdido.
     */
    private function registrarDerivacion(Order $order): void
    {
        EventLog::record('checkout.payment_started', [
            'order_id' => $order->id,
            'payment_method' => $order->payment_method,
            'total_cents' => $order->total_cents,
        ]);
    }

    public function success(Cart $cart): View|RedirectResponse
    {
        $orderId = session('order_id');

        if ($orderId === null) {
            return redirect()->route('carrito.show');
        }

        /** @var Order|null $order */
        $order = Order::query()->with('lines')->find($orderId);

        if ($order === null) {
            return redirect()->route('carrito.show');
        }

        return view('checkout.success', [
            'order' => $order,
            'lines' => $order->lines,
            'categorias' => Category::query()->orderBy('sort_order')->get(),
        ]);
    }
}
