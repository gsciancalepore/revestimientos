# Spec 07 Fase 4 — MercadoPago: creación de preferencia y redirección

- **Estado**: borrador (2026-09-03) — pendiente de aprobación del dueño (extiende 07.1 estructura + 07.2 `PlaceOrderAction` + 07.3 HTTP; no modifica `07-checkout.md`, `07-checkout-fase2.md` ni `07-checkout-fase3-http.md`; no implementa webhook/confirmación/stock)
- **Fuentes**: Spec 07.1 (101–107 `orders`/`order_lines`, `OrderStatus`, `PaymentGateway name()` solo), Spec 07.2 (108–114 `PlaceOrderAction` `lockForUpdate`/`bcmath`/`audit`/`clear` post-commit), Spec 07.3 (115–120 `CheckoutController` `session order_id`, `StoreCheckoutRequest`, `shipping !disponible → 0`), Spec 00 (24–26 pagos), ADR-003 (centavos + bcmath), ADR-004 (audit), ADR-006 (puertos), `PROJECT_PRINCIPLES.md`, `AGENTS.md`, `.ai/rules/*`

## Objetivo

Implementación real de `PaymentGateway` para **MercadoPago**: `MercadoPagoGateway` crea `Preference` vía SDK oficial, asocia `mp_preference_id`/`mp_init_point` a `Order`, y `checkout mercadopago → MercadoPago` por `redirect init_point`; `transferencia` sin cambios; manejo de error de API con `Order PendingPayment` + aviso y **reintento por POST explícito**, sin `GET` con efecto; tests con gateway fake/mock; sin webhook, sin confirmación, sin decremento de stock.

## Contexto

- 07.1 define `PaymentGateway` minimalista `name(): string` (`app/Contracts/PaymentGateway.php:5`) + `ManualTransferGateway` `transferencia` (`AppServiceProvider` bind); 07.2 lo consume solo vía `name()`; 07.3 orquesta `POST /checkout → PlaceOrderAction → session order_id → success` con ambas opciones `transferencia`/`mercadopago` pero `success` promete "Serás redirigido" sin redirigir (deuda que 07.4 salda).
- 07.4 respeta YAGNI: no `DTOs/Events/Listeners/Jobs` anticipados; no `DiscountCalculator`; no multi-gateway genérico (solo `PaymentGateway` + `MercadoPagoGateway` concreto).

## Reglas de negocio (continúan 07.3:115–120)

121. **Contrato `PaymentGateway` extendido mínimamente** (sin DTO): `paymentUrl(Order $order): ?string`. `null` significa *este gateway no tiene URL de pago* (`ManualTransferGateway → null`); fallo de MercadoPago **no** es `null`, es excepción. `name(): string` permanece (`transferencia`/`mercadopago`).

122. **`MercadoPagoGateway` encapsula completamente el SDK oficial**: `composer require mercadopago/dx-php` (versión pineada, verificar con `composer show`, como Breeze `2.4.2`); config `config/services.php` `'mercadopago' => ['access_token' => env('MERCADOPAGO_ACCESS_TOKEN'), 'public_key' => env('MERCADOPAGO_PUBLIC_KEY')]`, `.env.example` keys vacías, `php artisan config:show services.mercadopago` para verificar; `MERCADOPAGO_ACCESS_TOKEN` nunca en repo ni en `audit`. `Order` tras `PlaceOrderAction` ya existe (`PendingPayment`, `bcmath`, `audit`).

123. **Creación de Preference y asociación a `Order`**: migración `add_mp_fields_to_orders` añade `mp_preference_id nullable string`, `mp_init_point nullable text` (nombres explícitos MP, no genéricos). `Order` fillable + factory states + `casts` (strings). Al crear `Preference`: `items: [{title: product_name, quantity: cantidad (entera `M2→cajas`), unit_price: bcdiv((string)precio_unitario_cents,'100',2) cast a float solo en borde SDK documentado, currency_id: 'ARS'}]`, `external_reference: (string) $order->id` (para futuro webhook 08), `back_urls: {success: route('checkout.success'), failure: route('checkout.success'), pending: route('checkout.success')}` con `APP_URL` + `auto_return: 'approved'`, `metadata` opcional. Tras éxito, `Order::update(['mp_preference_id' => $pref->id, 'mp_init_point' => $pref->init_point])`.

124. **Checkout `mercadopago → MercadoPago`** (`CheckoutController` delgado, sin reglas de negocio, solo orquesta): `store` ya hace `PlaceOrderAction → session order_id`; inmediatamente después, si `payment_method === 'mercadopago'` → `try { $url = app(MercadoPagoGateway::class)->paymentUrl($order); return redirect()->away($url); } catch (Throwable $e) { Log::error('mp preference failed', ['order_id' => $order->id, 'error' => $e->getMessage()]); return redirect()->route('checkout.success')->with('payment_error', 'No pudimos generar el link de pago, reintentá.'); }`. `transferencia` mantiene `redirect checkout.success` sin cambios.

125. **Manejo de error de API sin `null`**: `paymentUrl()` **lanza excepción** ante error de API/configuración (timeout, 4xx/5xx, token ausente); **devuelve `null` solo para gateways sin URL** (transferencia). `CheckoutController` captura excepción y no borra `Order` (ya `COMMIT` + `audit`, `PendingPayment` garantizado, `Cart` ya vacío porque `PlaceOrderAction` hizo `clear` post-commit). `GET /checkout/exito` no crea `Preference` (sin efecto secundario).

126. **Reintento por POST explícito** (HTTP correcto, sin `GET` con efecto): `POST /checkout/mercadopago/reintentar` (`checkout.mercadopago.retry`, sin `auth`) lee `session('order_id')` → `Order::findOrFail` → valida `payment_method === 'mercadopago'` y `status === PendingPayment` → `try { $url = MercadoPagoGateway->paymentUrl($order) } catch → redirect success with payment_error` → `redirect()->away($url)` y sobrescribe `mp_preference_id`/`mp_init_point` en `Order`. `GET /checkout/exito` permanece **solo lectura**, nunca crea `Preference`: si `mp_init_point` existe → muestra botón/enlace para continuar a MercadoPago; si no existe y existe `payment_error` → muestra botón “Reintentar pago” mediante `POST`.

127. **Transferencia sin cambios**: `ManualTransferGateway::paymentUrl() → null`, `CheckoutController` no intenta redirigir, `success` muestra instrucciones transferencia.

128. **Fuera de alcance**: webhook (`POST /webhook/mercadopago`), `ConfirmPaymentAction` (cambio a `paid` + descuento stock `lock`), `Events/Listeners/Jobs`, `DTO` si no aporta, `Policies` anónimas, `API` externa fuera de SDK, `DiscountCalculator`.

## Matriz de permisos

| Acción | Público anónimo (sesión) | admin | vendedor | depósito |
|---|---|---|---|---|
| `GET /checkout`, `POST /checkout` | ✓ (sin `auth`) | — | — | — |
| `GET /checkout/exito` (lectura) | ✓ (`session order_id`) | — | — | — |
| `POST /checkout/mercadopago/reintentar` | ✓ (`session order_id`, `mercadopago` + `PendingPayment`) | — | — | — |

## Casos borde

- Carrito vacío / `hasUnpurchasable` → igual que 07.3 (redirect `carrito.show`, sin `Order`, sin `Preference`).
- `customer_*`/`cp`/`payment_method` inválido → `422` por `StoreCheckoutRequest` (07.3:116), sin `Order`.
- `Order` `PendingPayment` + `mercadopago` + `M2`/`Unidad` → `Preference` con `quantity` entera, `unit_price` `bcdiv/2` `ARS`, `external_reference` = `order->id`.
- `MERCADOPAGO_ACCESS_TOKEN` ausente/inválido o `Preference` 4xx/5xx/timeout → `paymentUrl()` lanza, `store`/`retry` capturan, `Order` queda `PendingPayment` con `Cart` vacío, `success` con `payment_error` y botón reintentar (`POST`), sin `500`.
- `ManualTransferGateway` → `paymentUrl() === null`, nunca lanza por MP.
- `GET /checkout/exito` sin `session` → redirect `carrito.show`; `order_id` tamper/otro navegador → `findOrFail` 404; `success` nunca crea `Preference`.
- `POST /reintentar` con `Order` no `mercadopago` o no `PendingPayment` → `403` o `redirect success` sin `Preference`.
- Concurrencia: dos `POST /checkout` simultáneos siguen `lockForUpdate` de 07.2; `Preference` se crea sobre `Order` ya `COMMIT` (fuera de transacción).

## Criterios de aceptación

- [ ] `composer require mercadopago/dx-php` pineado (versión verificada con `composer show`) + `config/services.php` `mercadopago` + `.env.example` keys vacías + `AppServiceProvider` no rompe `ManualTransferGateway` (binding `PaymentGateway → ManualTransferGateway` + `bind(MercadoPagoGateway::class)` propio).
- [ ] Migración `add_mp_fields_to_orders` (`mp_preference_id`, `mp_init_point` nullable) + `Order` fillable + factories.
- [ ] `PaymentGateway` con `paymentUrl(Order): ?string` (`ManualTransferGateway → null`, `MercadoPagoGateway → init_point` o lanza); `MercadoPagoGateway` encapsula SDK completo, `bcdiv` solo en borde SDK documentado.
- [ ] `CheckoutController@store` para `mercadopago`: `try paymentUrl → redirect away`, `catch → redirect success with payment_error` manteniendo `Order Cart vacío`; `transferencia` sin cambios; `POST /checkout/mercadopago/reintentar` (`retry`) con `session order_id` y misma lógica `try/catch`.
- [ ] `GET /checkout/exito` sin efecto, sin crear `Preference`: si `mp_init_point` existe → muestra botón/enlace para continuar a MercadoPago; si `mp_init_point` no existe y existe `payment_error` → muestra botón “Reintentar pago” mediante `POST /checkout/mercadopago/reintentar`; transferencia muestra instrucciones.
- [ ] Tests `tests/Feature/Checkout/MercadoPagoTest.php` con gateway fake/mock (sin llamar API real): transferencia sin columnas MP ok; mercadopago OK → `302` a `init_point` + columnas persistidas; error API → `Order PendingPayment` + `302 success` con `payment_error` + sin `500` + `GET success` con botón `POST`; `GET success` nunca crea `Preference`.
- [ ] Pint, PHPStan nivel 8 (`app/`), Pest verde, CI `lint→stan→test` (una suite).

## Decisiones arquitectónicas

- **`paymentUrl(): ?string` semántica `null` vs excepción**: evita confundir "no corresponde URL" (transferencia) con "debería haber URL pero falló" (MP). `CheckoutController` decide redirección vs aviso.
- **`POST` para reintento**: respeta HTTP (sin `GET` con efecto secundario); `success` permanece lectura pura.
- **SDK oficial vs `Http` nativo**: se elige SDK por decisión explícita (tu respuesta), asumiendo mantenimiento vendor; descartado `Http` documentado como alternativa.
- **Sin DTO**: `init_point` + `preference_id` bastan (YAGNI hasta webhook).
- **Sin webhook/stock**: 07.4 no cambia `status` ni descuenta stock (08 lo hace con `ConfirmPaymentAction` + `lock`).

## Evolución documentada (no anticipada)

- **Spec 08**: `POST /webhook/mercadopago` (`external_reference` → `Order`), `ConfirmPaymentAction` (`paid` + descuento stock `lock` + `audit`), restitución, gestión depósito, ventas WhatsApp.

## Tareas técnicas

- [ ] `docs/specs/07-checkout-fase4-mercadopago.md` borrador → aprobación dueño (solo este doc, sin tocar 07/07.2/07.3).
- [ ] Rama `feat/checkout-07-fase4-mercadopago` desde `main`: migración `mp_*` → `Order`/`Factory` → `PaymentGateway paymentUrl()` → `MercadoPagoGateway` SDK + config → `CheckoutController` (`store` + `retry` `POST`) + rutas → `checkout/success.blade` botón/aviso → `FakeMercadoPagoGateway` + tests → `make format → lint → stan → test`.
- [ ] Actualizar `docs/arquitectura.md` (MercadoPago Fase 4) y `docs/roadmap.md` solo columna `Estado` + fecha al cerrar (excepción `AGENTS.md`).

## Nota de handoff

07.4 es solo Preferencia y redirección; no tocar `PlaceOrderAction` (ya `bcmath`/`lock`/`audit`), no `ConfirmPaymentAction`, no `Policies`, no `DTOs`. TDD: red `MercadoPagoTest` fake → `MercadoPagoGateway` → `CheckoutController` (store + retry). Rama `feat/checkout-07-fase4-mercadopago` desde `main` (post 07.3 merge). Seguir `AGENTS.md`, `.ai/rules`, `PROJECT_PRINCIPLES.md`, `make` Docker.

