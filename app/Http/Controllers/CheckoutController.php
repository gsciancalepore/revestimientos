<?php

namespace App\Http\Controllers;

use App\Actions\PlaceOrderAction;
use App\Http\Requests\Checkout\StoreCheckoutRequest;
use App\Models\Category;
use App\Models\Order;
use App\Services\Cart;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class CheckoutController extends Controller
{
    public function show(Cart $cart): View|RedirectResponse
    {
        if ($cart->isEmpty()) {
            return redirect()->route('carrito.show')->withErrors(['checkout' => 'El carrito está vacío.']);
        }

        if ($cart->hasUnpurchasable()) {
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
            return back()->withErrors(['checkout' => $e->getMessage()])->withInput();
        }

        session(['order_id' => $order->id]);

        return redirect()->route('checkout.success');
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
