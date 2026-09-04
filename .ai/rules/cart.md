---
paths:
  - 'app/Http/Controllers/CheckoutController.php, app/Http/Controllers/CartController.php, app/Http/Requests/Checkout/**, app/Http/Requests/Cart/**'
---

# Cart

## Checkout anónimo: sesión, rutas explícitas y preview ?cp=
Checkout y carrito son públicos anónimos (sin auth, sin Policy) y no usan Route::resource (rutas explícitas checkout.show/store/success, checkout.mercadopago.retry). Controladores delgados: Request validado → Action → redirect. Éxito anónimo por session('order_id'), sin {order} en URL ni route-model binding; sin sesión → redirect carrito.show. Preview de envío server-side con ?cp= (trim + regex ^[0-9]{4}$ → ShippingCalculator::quote → total solo si disponible); la preview es informativa, PlaceOrderAction es la fuente de verdad.
