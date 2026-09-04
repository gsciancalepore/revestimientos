# Spec 07 Fase 3 — Checkout: HTTP + formulario + confirmación

- **Estado**: cerrada (2026-09-03) — 196 tests verde, implementada en `feat/checkout-07-fase3-http` (extiende 07.1 estructura + 07.2 `PlaceOrderAction`; no modifica `07-checkout.md` ni `07-checkout-fase2.md`; no implementa `ConfirmPaymentAction`)
- **Fuentes**: Spec 07.1 (101–107 estructura `orders`/`order_lines`, `OrderStatus`, `PaymentGateway name()` solo), Spec 07.2 (108–114 `PlaceOrderAction`, `Cart::clear` post-commit, `bcmath`, `lockForUpdate`, `ShippingQuote`, `audit`), Spec 00 (13–14 anónimo, 15–18 envío, 19–23 pedido, 24–26 pagos), Spec 05 (81–92 carrito, 88 subtotal, 92 `hasUnpurchasable`), Spec 06 (93–100 `ShippingCalculator`/`ShippingQuote`), ADR-003 (centavos + `M2Calculator`), ADR-004 (audit), ADR-006 (puertos), `AGENTS.md` (controladores delgados, `FormRequest`, `Policies`), `.ai/rules/*`

## Objetivo

Exponer el **checkout anónimo vía HTTP**: formulario público, validación, invocación de `PlaceOrderAction` y confirmación por **sesión + redirect** sin exponer `{order}` en URL. Cierra 07 (07.1 persistencia + 07.2 lógica + 07.3 HTTP); 08 abre gestión/pago confirmado.

## Contexto

- 07.1 provee `orders`/`order_lines` (14 migraciones, `OrderStatus`, `PaymentGateway` `name()` solo); 07.2 provee `PlaceOrderAction` (`Cart` + `lockForUpdate` + `bcmath` + `audit` + `clear` post-commit).
- `Cart` en `session('cart')` (Spec 05) y `ShippingCalculator::quote(trim cp): ShippingQuote` (Spec 06) ya existen y son consumidos por 07.2 — 07.3 solo orquesta HTTP.
- Decisiones ya tomadas y congeladas en este borrador:
  - Éxito mediante **sesión + redirect**, sin `{order}` en URL.
  - `quote !disponible → shipping_cost_cents = 0` y **se permite crear el pedido** (fiel a 07.2:110/114).
  - Checkout ofrece **transferencia y MercadoPago** (`payment_method in ['transferencia','mercadopago']`).

## Reglas de negocio (continúan 07.2:108–114)

115. **Rutas públicas anónimas** (sin `auth`, sin `Policy`; matriz: público ✓): `GET /checkout` (`checkout.show`), `POST /checkout` (`checkout.store`), `GET /checkout/exito` (`checkout.success`). Sin `Route::resource` (coherente con carrito Spec 05, evita trap `routes.md` `parameters`). Nombres `checkout.*`.

116. **`StoreCheckoutRequest` (`app/Http/Requests/Checkout/StoreCheckoutRequest.php`)**: `customer_name` required string max:255, `customer_email` required email max:255, `customer_phone` required string max:50, `shipping_cp` required string `regex:/^[0-9]{4}$/` (conserva `0123`), `shipping_address` nullable string max:500, `payment_method` required `Rule::in(['transferencia','mercadopago'])`; mensajes en español. `prepareForValidation` hace `trim` de `customer_*`/`shipping_cp`/`payment_method` (coherente con `StoreShippingRateRequest`). No valida `cart` aquí — lo hace la Action (regla 108 prevalidación `hasUnpurchasable()`).

117. **`CheckoutController` delgado** (`app/Http/Controllers/CheckoutController.php`, inyecta `Cart`, `PlaceOrderAction`, no contiene reglas):
    - `show(Request, Cart)`: si `cart->isEmpty()` → `redirect()->route('carrito.show')` con mensaje; si `cart->hasUnpurchasable()` → `redirect()->route('carrito.show')->withErrors(...)` (no muestra form); sino `view('checkout.show', ['lines','subtotal','hasUnpurchasable','isEmpty','categorias','cart'])` usando `Category::orderBy('sort_order')` para `layouts/site` (`.ai/rules/views.md`).
    - `store(StoreCheckoutRequest)`: `validated()` → `PlaceOrderAction->execute(customer_name, customer_email, customer_phone, shipping_cp, shipping_address, payment_method)` → `session(['order_id' => $order->id])` → `redirect()->route('checkout.success')`; `catch DomainException $e` → `back()->withErrors(['checkout' => $e->getMessage()])->withInput()` (carrito permanece si `rollback`, regla 112).
    - `success(Request)`: lee `session('order_id')`; si `null` → `redirect()->route('carrito.show')`; sino `Order::with('lines')->findOrFail($id)` y `view('checkout.success', ['order','lines','categorias'])` snapshot congelado, sin recalcular; no expone `{order}` en URL ni requiere `auth`.

118. **Shipping en HTTP**: `store` reutiliza `ShippingCalculator::quote(trim(cp))` vía `PlaceOrderAction` (regla 110); si `!disponible` → `shipping_cost_cents = 0` y pedido se crea igual (decisión congelada). No bloquear UI en esta fase; `08` podrá exigir `disponible` si el negocio lo requiere.

119. **Vistas** (`resources/views/checkout/show.blade.php` + `success.blade.php`):
    - `show`: ` <x-layouts.site :categorias="$categorias">` (`.ai/rules/views.md` `App\View\Components\Layouts\Site`), `@csrf`, `old()` + `$errors`, lista `lines` (`precioUnitario` vía `M2Calculator` ya calculado en `PlaceOrderAction`), `subtotal` preview (`Cart::subtotal()`), radio `payment_method` (transferencia/mercadopago), `number_format($x/100,2,',','.')` centavos, nunca float.
    - `success`: muestra `order` snapshot (`customer_*`, `shipping_cp/address`, `shipping_cost_cents`, `subtotal/total`, `lines` con `product_name/codigo/marca/cantidad/precio_unitario/subtotal/specs`), mensaje según `payment_method` (instrucciones transferencia vs mercadopago pendiente).

120. **Fuera de alcance**: `ConfirmPaymentAction`, descuento stock (Spec 08, `lock` al pasar a `paid`), `Events/Listeners/Jobs`, `DTOs`, MercadoPago `createPreference`/`webhook` (Fase 3 de Payments), `Policies` para anónimo, `API` externa.

## Matriz de permisos

| Acción | Público anónimo (sesión) | admin | vendedor | depósito |
|---|---|---|---|---|
| `GET /checkout` (form) | ✓ (si carrito no vacío y comprable) | — | — | — |
| `POST /checkout` (confirma) | ✓ (sin `auth`) | — | — | — |
| `GET /checkout/exito` (confirmación) | ✓ (lee `session order_id`) | — | — | — |
| `GET /checkout` con carrito vacío/no comprable | redirect `carrito.show` | — | — | — |
| Acceso anónimo a `/admin/*` | — | ✓ | ✓ | ✓ |

## Casos borde

- Carrito vacío en `show` o `store` → redirect `carrito.show` (sin crear `Order`); `store` nunca llega a `PlaceOrderAction` si `show` ya redirigió, pero `store` revalida `isEmpty()` por si el cliente postea directo.
- `hasUnpurchasable()` true en `show` → redirect con error, no muestra form; en `store` (si el stock cambió entre `show` y `store`) → `DomainException` de `PlaceOrderAction` (regla 109 definitiva) → `back withErrors`, sin `Order`, carrito intacto.
- `customer_email` inválido, `shipping_cp` no `^[0-9]{4}$`, `payment_method` inválido → `422` por `StoreCheckoutRequest` antes de Action; sin `Order`.
- `shipping !disponible` → `store` crea `Order` con `shipping_cost=0` (permitido); `show` puede mostrar preview `Envío no disponible` pero no bloquea `POST`.
- `session('order_id')` null en `success` → redirect `carrito.show`; `order_id` no pertenece a sesión (tamper) → `findOrFail` 404; `order_id` de otro navegador → no visible (sesión aislada).
- `Order` mutado tras crear → `success` sigue mostrando snapshot de `order_lines`, no `Product` vigente.
- Concurrencia: dos `POST` simultáneos con último stock → `lockForUpdate` serializa (regla 109); uno `COMMIT` + `Cart::clear`, otro `rollback` + `DomainException` + carrito intacto + `audit` solo del primero.

## Criterios de aceptación

- [ ] `StoreCheckoutRequest` en `app/Http/Requests/Checkout/` con reglas/mensajes ES y `trim` en `prepareForValidation`.
- [ ] `CheckoutController` delgado (`show`/`store`/`success`) inyecta `Cart`/`PlaceOrderAction`, sin reglas de negocio, con `session('order_id')` sin `{order}` en URL.
- [ ] Rutas públicas `GET /checkout`, `POST /checkout`, `GET /checkout/exito` en `routes/web.php` (sin `auth`, sin `resource`).
- [ ] Vistas `checkout/show.blade.php` y `success.blade.php` con `layouts/site` + `categorias` prop, `number_format` centavos, snapshot.
- [ ] Flujo completo anónimo: `GET /checkout` (200) → `POST /checkout` válido (`transferencia` y `mercadopago`) → `Order PendingPayment` + `Lines` + `audit` + `session` + redirect `exito` + `cart` vacío; `POST` inválido → `422`/sin `Order`/carrito intacto; `GET /exito` sin sesión → redirect.
- [ ] Shipping `!disponible` crea `Order` con `0`, no `500`; shipping `disponible` suma `total`; `m2_por_caja` `string` nunca `float`.
- [ ] Pint, PHPStan nivel 8 (`app/`), Pest verde, CI `lint→stan→test` (una suite, `ceramica_test`); `npm run build` si vistas `@vite()` fallan (`.ai/rules/tests.md`).

## Decisiones arquitectónicas

- **Controlador delgado**: solo orquesta `Request validated → Action → redirect` (igual que `CartController`); reglas en `PlaceOrderAction` (transacción, `lock`, `bcmath`).
- **Sesión para éxito**: anónimo sin cuenta → `session order_id` evita exponer IDs y requiere cero `Policy`; `success` no usa route-model binding (sin `{order}`).
- **Validación en Request**: `StoreCheckoutRequest` centraliza `customer_*`/`cp`/`payment_method` (reusa `Rule::in`); `PlaceOrderAction` mantiene prevalidación `hasUnpurchasable` + definitiva bajo lock (regla 108/109, no redefine `M2Calculator`).
- **Sin anticipación**: no `ConfirmPaymentAction`, no `Events/Jobs`, no `DTO`, no `createPreference/webhook`.

## Evolución documentada (no anticipada)

- Spec 08: `ConfirmPaymentAction` (descuento stock `lock`, `order.paid` → `shipped` → `delivered/cancelled`, restitución, `audit`), gestión depósito, ventas WhatsApp manuales, `PaymentGateway` `createPreference`/webhook.

## Tareas técnicas

- [ ] `docs/specs/07-checkout-fase3-http.md` borrador → aprobación dueño.
- [ ] Rama `feat/checkout-07-fase3-http` desde `main`: `make:request`, `make:controller`, `routes`, vistas, tests `tests/Feature/Checkout/CheckoutTest.php`, `make format → lint → stan → test` (una suite).
- [ ] Actualizar `docs/arquitectura.md` (Checkout Fase 3) y `docs/roadmap.md` solo columna `Estado` + fecha al cerrar (excepción `AGENTS.md`).

## Nota de handoff

Fase 3 es solo HTTP; no tocar `PlaceOrderAction`/`M2Calculator`/`Cart`. TDD: red `GET /checkout` → `POST` → `success` (sesión), con `Cart` en sesión y `ShippingQuote` mock. Rama `feat/checkout-07-fase3-http` desde `main` (post 07.2 merge). Seguir `AGENTS.md`, `.ai/rules`, `PROJECT_PRINCIPLES.md`, `make` Docker.

