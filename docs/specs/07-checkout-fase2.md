# Spec 07 Fase 2 — Checkout: lógica `PlaceOrderAction` (compra anónima)

- **Estado**: cerrada (2026-09-03) — implementada y mergeada a `main` (PR #3)
- **Fuentes**: Spec 07.1 (reglas 101–107, estructura `orders`/`order_lines`, `OrderStatus`, `PaymentGateway name()` solo), Spec 00 (19–23 pedido, 24–26 pagos, 27 anónimo), Spec 05 (81–92 carrito, 88 subtotal), Spec 06 (93–100 envío, `ShippingCalculator` + `ShippingQuote`), ADR-003 (bcmath, centavos, `M2Calculator`), ADR-005 (stock al confirmar pago, `lockForUpdate`), ADR-004 (audit), ADR-006 (puertos)

## Objetivo

Implementar la **lógica del checkout anónimo**: `PlaceOrderAction` convierte el carrito de sesión en un `Order` + `OrderLines` con precios y `shipping_cost_cents` congelados, dentro de una transacción con validación de stock/`activo` y `Cart::clear()` **solo tras `COMMIT`**. No incluye controladores/rutas, confirmación de pago ni descuento de stock (pertenece a Spec 08).

## Contexto

- 07.1 ya provee `orders`/`order_lines` (14 migraciones), `Order/OrderLine` (casts, relaciones, scopes), `OrderStatus` inglés + `label()`, `PaymentGateway` `name()` solo + `ManualTransferGateway`.
- `Cart` vive en `session('cart')` (`array<product_id=>cantidad>` entera `M2→cajas`/`Unidad→unidades`, Spec 05) y `ShippingCalculator::quote(trim cp): ShippingQuote{costoCents, disponible}` ya existe (Spec 06, `app/Services/ShippingCalculator.php:5`) — **07.2 solo consume el contrato, no lo rediseña ni reimplementa**.
- `M2Calculator` (`m2DesdeDimensiones`, `aplicarDesperdicio`, `cajasNecesarias` + `precioCajaCents`) ya existe (Spec 04, ADR-003) — 07.2 lo reutiliza, no redefine fórmulas.
- Fase 2 respeta YAGNI: no `DTOs/Events/Listeners/Jobs`, no `DiscountCalculator`, no reserva con vencimiento.

## Reglas de negocio (continúan 07.1:101–107)

108. **`PlaceOrderAction` entrada**: `execute(customer_name: string, customer_email: string, customer_phone: string, shipping_cp: string, shipping_address: ?string, payment_method: string): Order`. Valida `customer_name/phone` requerido string, `customer_email` formato email, `shipping_cp` `^[0-9]{4}$` string con ceros, `payment_method in ['transferencia','mercadopago']`, `cart no vacío` y **prevalidación** `Cart::hasUnpurchasable()` → `DomainException`. `hasUnpurchasable()` es solo prevalidación del carrito; las condiciones susceptibles de cambio concurrente (`activo`, `stock`) se revalidan definitivamente dentro de la transacción sobre productos bloqueados (regla 109).

109. **Transacción y lock**: todo dentro de `DB::transaction` con `Product::whereIn(ids)->lockForUpdate()->get()`. Dentro valida definitivamente `activo=true` y `cantidad ≤ stock` (en `unidad_venta` del producto: `M2→cajas`/`Unidad→unidades`); si falla → `DomainException` y `rollback`, carrito permanece intacto.

110. **Cálculos con bcmath (centavos, nunca float)**: para productos `M2`, `PlaceOrderAction` utiliza `M2Calculator`/`Product::precioCajaCents()` para obtener `precio_unitario_cents`; para `Unidad`, utiliza `precio_cents` directamente (no redefine fórmula, reutiliza `M2Calculator` y `Product` ya existentes, regla 84/ADR-003). Luego `subtotal_linea = bcmul((string)cantidad, (string)precio_unitario_cents)`; `subtotal = bcadd Σ subtotal_linea`; `ShippingQuote quote = ShippingCalculator->quote(trim(shipping_cp))` (contrato ya existente, Spec 06) → `shipping_cost_cents = quote->disponible ? quote->costoCents : 0` (snapshot, no se recalcula después); `total = bcadd((string)subtotal, (string)shipping_cost_cents)`. `m2_por_caja` `string` nunca `float`.

111. **Snapshot**: cada `OrderLine` persiste `product_name/codigo/marca/unidad_venta/m2_por_caja(string)/cantidad/subtotal_cents/precio_unitario_cents/specs` del `Product` al momento; independiente de mutaciones posteriores de `Product`.

112. **Limpieza `Cart` post-commit**: `Cart::clear()` **solo después de `COMMIT OK`**, fuera de la transacción. Si `rollback` (validación, stock, excepción) el carrito permanece intacto — checkout fallido no borra la compra en curso.

113. **Creación y auditoría**: crea `Order(status=PendingPayment, customer_*, shipping_*, shipping_cost_cents, subtotal_cents, total_cents, payment_method)` + `OrderLines` + `audit_logs` `order.created` con `payload` `{subtotal, shipping, total, lines: [product_id,cantidad]}` vía `AuditRecorder`. No descuenta stock (ADR-005 → `ConfirmPaymentAction` Spec 08 al pasar a `paid`).

114. **Fuera de alcance Fase 2**: controladores/rutas, `ConfirmPaymentAction`, `Events/Listeners/Jobs`, descuentos. No se crea DTO en esta fase. UI sobre si checkout debe bloquearse cuando `quote !disponible` (07.2 define `shipping_cost=0`, decisión de rutas/UI no es parte de esta fase).

## Matriz de permisos

| Acción | Público anónimo (sesión) | admin | vendedor | depósito |
|---|---|---|---|---|
| Ejecutar `PlaceOrderAction` (checkout) | ✓ (sin `auth`) | — | — | — |
| Ver/crear `Order` vía UI | — (sin rutas Fase 2) | — | — | — |

## Casos borde

- Carrito vacío o `hasUnpurchasable()` → `DomainException` antes de transacción, sin `Order`.
- Producto `activo=false` o `cantidad > stock` detectado dentro del `lockForUpdate` → `DomainException` + `rollback`, carrito intacto.
- `m2_por_caja` null en `Unidad` → `null` snapshot; en `M2` nunca null (Spec 03:59) — si ocurre, `DomainException`.
- `payment_method` null/inválido, `shipping_cp` inválido, `email` inválido → `ValidationException` (422) antes de transacción.
- Concurrencia: dos `PlaceOrder` simultáneos con último stock → `lockForUpdate` serializa, uno `COMMIT`, otro `DomainException` por `cantidad>stock` tras lock. Existe cobertura de concurrencia sobre PostgreSQL que verifica que dos operaciones concurrentes sobre el mismo stock no pueden confirmar ambas la compra (no se exige necesariamente un Feature test convencional si el entorno no reproduce concurrencia real).
- `ShippingQuote !disponible` → `shipping_cost=0` snapshot, no excepción; `total = subtotal`. 07.2 define únicamente `quote disponible → shipping_cost = costoCents`, `quote no disponible → 0`; cualquier decisión de bloquear checkout en UI/rutas queda fuera de esta fase.

## Criterios de aceptación (Fase 2)

- [ ] `PlaceOrderAction` existe en `app/Actions/` (una clase = un caso, sin HTTP), inyecta `Cart`, `ShippingCalculator`, `AuditRecorder`.
- [ ] Valida `customer_*`, `shipping_cp`, `payment_method`, `cart` no vacío y prevalidación `hasUnpurchasable()`; validación definitiva `activo`/`stock` dentro de `lockForUpdate`; `DomainException` con mensaje claro y sin side-effects (no `Order`, carrito intacto si `rollback`).
- [ ] Transacción `DB::transaction` + `lockForUpdate` valida `activo` y `cantidad ≤ stock`; si falla → `rollback`.
- [ ] Cálculos `bcmath` centavos vía `M2Calculator`/`precioCajaCents()` para `M2` y `precio_cents` para `Unidad`; `m2_por_caja` `string` nunca `float`; `shipping_cost_cents` freeze de `ShippingQuote` (`disponible? costo:0`).
- [ ] Crea `Order` (`PendingPayment`) + `OrderLines` snapshot independiente; `total = subtotal + shipping`; no recalcula si `Product`/`shipping_rates` cambian después.
- [ ] `audit_logs order.created` con `actor null` (anónimo) + `subject Order` + `payload`; no descuenta stock.
- [ ] `Cart::clear()` solo tras `COMMIT`; `rollback` mantiene carrito.
- [ ] Tests `tests/Feature/Orders/PlaceOrderTest.php` cubren behaviours: carrito vacío, `activo=false`, `stock insuficiente`, `M2`/`Unidad` (vía `M2Calculator`), `shipping disponible/no disponible`, snapshot independencia, `audit`, `rollback` carrito intacto vs `commit` carrito limpio, concurrencia/lock sobre PostgreSQL (la cantidad de tests surge de los behaviours, no fija).
- [ ] Pint, PHPStan nivel 8 (`app/`), Pest verde, CI `lint→stan→test` (una suite `ceramica_test`).

## Decisiones arquitectónicas

- **Una Action = un caso**: `PlaceOrderAction` delgada, sin HTTP, transaccional; reutiliza `Cart`, `M2Calculator` (regla 84, no redefine fórmula) y `ShippingCalculator::quote(): ShippingQuote` (contrato Spec 06 ya existente; 07.2 solo lo consume, no lo rediseña).
- **Dinero**: `BIGINT` centavos + `bcmath`; `Cart::subtotal()` y `Order.subtotal/total` no usan `float`.
- **Snapshot**: desnormalizado `OrderLine` + FK `restrictOnDelete` permite trazabilidad sin perder histórico.
- **Limpieza `Cart`**: fuera de transacción tras `COMMIT` (YAGNI, no `Events`).
- **Sin anticipación**: no `ConfirmPaymentAction`, no rutas, no `DTOs/Events/Listeners/Jobs`, no descuentos, no multi-gateway. No se crea DTO en esta fase.

## Evolución documentada (no anticipada)

- Rutas anónimas `POST /checkout` + `ConfirmPaymentAction` (descuento stock con `lock`) pertenecen a entrega posterior dentro de Spec 07 o Spec 08.
- `PaymentGateway` multi-gateway `payment_method → gateway` + `createPreference/webhook` en Fase 3.

## Tareas técnicas (Fase 2)

- [ ] `PlaceOrderAction` + inyección `Cart`/`ShippingCalculator`/`AuditRecorder` + uso `M2Calculator`.
- [ ] `FormRequest` si hay controlador futuro (fuera de esta Action, no anticipar).
- [ ] Tests `PlaceOrderTest` (behaviours arriba) + `make format` → `make lint` → `make stan` → `make test`.
- [ ] Actualizar `docs/arquitectura.md` (Pedidos Fase 2) y `docs/roadmap.md` al cerrar Fase 2 (no en este borrador).

## Nota de handoff

Fase 2 es **solo lógica `PlaceOrderAction`**; no implementar controladores/rutas, pago ni stock. TDD: test red `PlaceOrder` → `lockForUpdate` → `M2Calculator` → `bcmath` → `audit` → `Cart::clear` post-commit. Rama `feat/checkout-07-fase2` desde `main` (post 07.1 merge). Seguir `AGENTS.md`, `.ai/rules`, `PROJECT_PRINCIPLES.md` TDD/ADR, bcmath centavos.

