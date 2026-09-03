<?php

namespace App\Http\Controllers;

use App\Http\Requests\Cart\AddToCartRequest;
use App\Http\Requests\Cart\UpdateCartRequest;
use App\Models\Category;
use App\Models\Product;
use App\Services\Cart;
use App\Services\M2Calculator;
use App\Services\ShippingCalculator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CartController extends Controller
{
    public function show(Request $request, Cart $cart, ShippingCalculator $calculator): View
    {
        $lines = $cart->lines();
        $subtotal = $cart->subtotal();
        $cp = $request->query('cp') !== null ? trim((string) $request->query('cp')) : null;
        $shippingQuote = null;
        $shippingError = null;

        if ($cp !== null && $cp !== '') {
            if (! preg_match('/^[0-9]{4}$/', $cp)) {
                $shippingError = 'El código postal debe tener 4 dígitos.';
            } else {
                $shippingQuote = $calculator->quote($cp);
            }
        }

        $total = null;
        if ($shippingQuote !== null && $shippingQuote->disponible) {
            $total = $subtotal + $shippingQuote->costoCents;
        }

        return view('cart.show', [
            'lines' => $lines,
            'subtotal' => $subtotal,
            'hasUnpurchasable' => $cart->hasUnpurchasable(),
            'isEmpty' => $cart->isEmpty(),
            'categorias' => Category::query()->orderBy('sort_order')->get(),
            'cp' => $cp,
            'shippingQuote' => $shippingQuote,
            'shippingError' => $shippingError,
            'total' => $total,
        ]);
    }

    public function add(AddToCartRequest $request, Cart $cart, M2Calculator $calculator): RedirectResponse
    {
        $product = Product::query()->where('slug', $request->validated('producto'))->firstOrFail();

        try {
            $cantidad = $this->resolverCantidad($request, $product, $calculator);
            $cart->add($product, $cantidad);
        } catch (\DomainException $e) {
            return back()->withErrors(['producto' => $e->getMessage()]);
        }

        return redirect()->route('carrito.show')->with('status', 'Producto agregado al carrito.');
    }

    public function update(UpdateCartRequest $request, Product $producto, Cart $cart): RedirectResponse
    {
        $cantidad = (int) $request->validated('cantidad');

        try {
            if ($cantidad === 0) {
                $cart->remove($producto);

                return redirect()->route('carrito.show')->with('status', 'Producto eliminado del carrito.');
            }

            $cart->update($producto, $cantidad);
        } catch (\DomainException $e) {
            return back()->withErrors(['cantidad' => $e->getMessage()]);
        }

        return redirect()->route('carrito.show')->with('status', 'Carrito actualizado.');
    }

    public function remove(Product $producto, Cart $cart): RedirectResponse
    {
        $cart->remove($producto);

        return redirect()->route('carrito.show')->with('status', 'Producto eliminado del carrito.');
    }

    public function clear(Cart $cart): RedirectResponse
    {
        $cart->clear();

        return redirect()->route('carrito.show')->with('status', 'Carrito vaciado.');
    }

    private function resolverCantidad(AddToCartRequest $request, Product $product, M2Calculator $calculator): int
    {
        if ($product->isM2Mode()) {
            $superficie = $request->validated('superficie');

            if ($superficie !== null) {
                /** @var numeric-string $m2 */
                $m2 = (string) $superficie;
                $withWaste = $request->boolean('desperdicio');

                if ($withWaste) {
                    $m2 = $calculator->aplicarDesperdicio($m2);
                }

                if ($product->m2_por_caja === null) {
                    throw new \DomainException('El producto no tiene m² por caja configurado.');
                }

                /** @var numeric-string $m2PorCaja */
                $m2PorCaja = (string) $product->m2_por_caja;

                // @phpstan-ignore argument.type
                return $calculator->cajasNecesarias($m2, $m2PorCaja);
            }

            $cantidad = $request->validated('cantidad');

            if ($cantidad !== null) {
                return (int) $cantidad;
            }

            throw new \DomainException('Debe indicar la superficie en m².');
        }

        $cantidad = $request->validated('cantidad');

        if ($cantidad === null) {
            throw new \DomainException('Debe indicar la cantidad.');
        }

        $desperdicio = $request->boolean('desperdicio');

        if ($desperdicio) {
            // En modo unidad el desperdicio no aplica (regla borde).
        }

        return (int) $cantidad;
    }
}
