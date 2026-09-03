# Spec 07 — Checkout (Compra anónima y creación del pedido)

- **Estado**: borrador 07.1 (2026-09-03) — pendiente de aprobación del dueño (revisión 07.1 incorpora grafo explícito de estados, cantidad entera, `m2_por_caja` string, snapshot shipping y `PaymentGateway` minimalista)
- **Fuentes**: Spec 00 (reglas 13–14 carrito anónimo, 15–18 envío, 19–23 pedido, 24–26 pagos, 27 cliente anónimo), Spec 05 (reglas 81–92 carrito, 88 subtotal), Spec 06 (reglas 93–100 envío por CP, 100 total), ADR-003 (unidades m²/cajas, dinero centavos + bcmath), ADR-005 (stock al confirmar pago), ADR-006 (puertos ShippingCalculator/PaymentGateway), visión (compra anónima MercadoPago/transferencia)

## Objetivo

**Fase 1 — Estructura**: crear la **persistencia y los contratos** que soportan el checkout anónimo, sin implementar aún la lógica de creación de pedidos ni controladores. Es la base para que Spec 07 Fase 2 (lógica `PlaceOrderAction`) y Spec 08 (gestión) puedan congelar precios y operar sobre un modelo estable.

El checkout completo (Fase 2) permitirá a un cliente anónimo convertir su carrito en un **pedido** con `subtotal + shipping` congelados, eligiendo medio de pago (MercadoPago o transferencia con confirmación manual).

## Contexto

- El cliente web es **anónimo** (Spec 00:27, Spec 05:81): no hay cuenta ni historial. Solo `customer_name`, `customer_email`, `customer_phone` + `shipping_cp`/`shipping_address`.
- El **carrito** (`session('cart')`, Spec 05) expone `subtotal = Σ subtotal_línea` (regla 88) sin `total`. Spec 06 añade `total = subtotal + shipping` cuando hay cotización `disponible` (regla 100).
- **Stock** no se reserva en carrito (ADR-005); desciende al confirmar pago (Spec 00:21). El precio se **congela** al crear el pedido.
- Fase 1 respeta `PROJECT_PRINCIPLES.md:5/8` (simplicidad/YAGNI): no se crean `DTOs/`, `Events/`, `Listeners/`, `Jobs/` todavía (`docs/arquitectura.md:42`), solo `Orders/Payments` mínimo.

## Reglas de negocio (continúa numeración Specs 00–06)

101. **Pedido anónimo**: un pedido nace del carrito anónimo + datos del cliente (`customer_name`, `customer_email`, `customer_phone`) + `shipping_cp`/`shipping_address` + cotización `shipping_cost_cents` (si `disponible`, regla 96) y no requiere usuario autenticado.

102. **Congelado de precios**: al crear el pedido cada línea guarda snapshot del producto: `product_name`, `product_codigo`, `marca`, `unidad_venta` (`M2`|`Unidad`), `m2_por_caja`, `cantidad`, `precio_unitario_cents` (congelado: `precio_cents` o `precioCajaCents()` con bcmath, Spec 03:59), `subtotal_cents = cantidad × precio_unitario_cents` y `specs` JSONB snapshot. Los montos **siempre en centavos (int) + bcmath**, nunca floats (ADR-003).
    - `cantidad` = cantidad física de venta, **entero positivo**: `M2 → cantidad de cajas`, `Unidad → cantidad de unidades` (coherente con carrito regla 82). DB `unsignedInteger` + `CHECK (cantidad > 0)` garantiza `>0`; no se usa decimal para cantidad.
    - `m2_por_caja`: `DB decimal(8,2)` → `Eloquent string` → nunca `float`; Fase 2 lo usa con `bcmul/bcdiv` (ADR-003). Cast `string` preserva precisión.

103. **Totales congelados**: `subtotal_cents = Σ order_lines.subtotal_cents`, `shipping_cost_cents` es **snapshot del costo de envío utilizado al momento de crear el pedido** (`ShippingCalculator → ShippingQuote → Order.shipping_cost_cents`); no se recalcula posteriormente aunque `shipping_rates` cambie, garantizando consistencia histórica. `shipping_cost_cents` = cotización `disponible` o `0` si no aplica. `total_cents = subtotal_cents + shipping_cost_cents` con bcmath y `CHECK >=0`. Una vez creado, el pedido **no recalcula** si el catálogo cambia.
    - Fase 1: DB solo garantiza `precio_unitario_cents >=0`, `subtotal_cents >=0`, `shipping_cost_cents >=0`, `total_cents >=0` con `CHECK`. La relación matemática `subtotal_linea = cantidad × precio_unitario` y `total = subtotal + shipping` la garantiza `PlaceOrderAction` en Fase 2 con bcmath, no con `CHECK` SQL.

104. **Estados del pedido** (`OrderStatus`): valor interno en inglés tipado por `OrderStatus` enum `PendingPayment='pending_payment'`, `Paid='paid'`, `Shipped='shipped'`, `Delivered='delivered'`, `Cancelled='cancelled'` (TitleCase, `values()` + `label()` español), coherente con `ProductSaleUnit`/`UserRole`.
    - Grafo explícito:
      ```
      pending_payment ──► paid ──► shipped ──► delivered
            │            │         │
            └─cancelled  └─cancelled└─cancelled
      ```
    - `pending_payment` puede ir a `paid` o `cancelled`; `paid` a `shipped` o `cancelled`; `shipped` a `delivered` o `cancelled`; `delivered`/`cancelled` son terminales. `cancelled` es el único que puede venir desde `pending_payment`/`paid`/`shipped` (regla 00:22).
    - **Fase 1** solo define el `OrderStatus` enum como contrato inequívoco para las siguientes specs; **no implementa state machine ni validación de transiciones**.

105. **Medio de pago** (`payment_method` nullable string): `transferencia` o `mercadopago`. Fase 1 `nullable` (puede existir `null` porque aún no hay checkout); Fase 2 exigirá `required Rule::in(['transferencia','mercadopago'])`. Sin `CHECK` SQL en Fase 1.

106. **Integridad**: `orders.shipping_cp` varchar(4) `^[0-9]{4}$` conserva ceros (`0123`); `orders` y `order_lines` con `CHECK >=0`. `order_lines.product_id` FK `restrictOnDelete` (bloquea borrado de producto con pedidos, regla 67 futura) + snapshot desnormalizado `product_name/codigo/marca/...` para histórico; `order_id` FK `cascadeOnDelete`. `product_id` + snapshot no es redundancia: trazabilidad vs reconstrucción histórica.

107. **Fase 1 NO incluye**: `PlaceOrderAction`, controladores/rutas, validación stock/activo en transacción, descuento de stock, confirmación de pago, ni `Events/Listeners`. Solo persistencia + contratos. La estrategia para resolver `payment_method` hacia un gateway concreto y las operaciones específicas de cada medio de pago (createPreference, webhooks, confirm) pertenecen a Fase 2.

## Matriz de permisos

Fase 1 no expone rutas. Los modelos son internos.

| Acción | Público | admin | vendedor | depósito |
|---|---|---|---|---|
| Crear pedido (checkout) | — (Fase 2) | — | — | — |
| Ver/crear `orders`/`order_lines` (DB) | — | — | — | — |

## Casos borde

- `customer_email` inválido, `customer_name/phone` vacío, `shipping_cp` no `^[0-9]{4}$` → validación Fase 2 (422); Fase 1 solo persiste lo que Fase 2 valide.
- Producto con `precio_cents` cambiado entre carrito y pedido → línea congela el precio del momento de creación, no el vigente.
- `shipping_cost_cents` sin tarifa activa → `disponible=false` (Spec 06:96); Fase 2 decidirá si bloquea checkout (regla 100). `shipping_cost_cents` snapshot no cambia aunque la tarifa cambie después.
- `cantidad = 0` o negativa → `CHECK cantidad > 0` en `order_lines` + validación Fase 2.
- `m2_por_caja` null en modo `Unidad` → snapshot null, sin cálculo caja; en `M2` nunca null (Spec 03:59).
- `m2_por_caja` como `string` evita `float`; cálculos Fase 2 con `bcmul`.
- Concurrencia: Fase 1 no descuenta stock; Fase 2 lo hará en transacción con `lockForUpdate` (ADR-005).

## Criterios de aceptación (Fase 1 — Estructura)

- [ ] Migración `create_orders_table` con `customer_name`, `customer_email`, `customer_phone`, `shipping_cp` (4), `shipping_address` nullable, `shipping_cost_cents`, `subtotal_cents`, `total_cents` (bigint centavos), `status` (string enum), `payment_method` nullable, `timestamps`, índices `status`/`customer_email`, `CHECK >=0` (sin `CHECK` de fórmula).
- [ ] Migración `create_order_lines_table` con `order_id` FK cascade, `product_id` FK restrict, snapshot `product_name`, `product_codigo`, `marca`, `unidad_venta`, `m2_por_caja` decimal(8,2) nullable, `cantidad` unsignedInteger `CHECK (cantidad > 0)` entero positivo (`M2→cajas`, `Unidad→unidades`), `precio_unitario_cents`, `subtotal_cents`, `specs` jsonb, índices, `CHECK >=0` (sin `CHECK` de fórmula).
- [ ] Enum `App\Enums\OrderStatus` (`PendingPayment='pending_payment'`, `Paid='paid'`, `Shipped='shipped'`, `Delivered='delivered'`, `Cancelled='cancelled'`) con `values()` y `label()` español; grafo documentado, sin state machine en Fase 1.
- [ ] Modelos `Order` (`status` cast `OrderStatus`, casts centavos `integer`, `lines(): HasMany`, scopes `pendingPayment()`, `paid()`, `byEmail()`, `byStatus()`) y `OrderLine` (`order()/product(): BelongsTo`, casts `specs array`, `m2_por_caja string` nunca float).
- [ ] Factories `OrderFactory`/`OrderLineFactory` con estados por modo venta y por estado.
- [ ] Contrato `App\Contracts\PaymentGateway` (puerto) con únicamente `name(): string` + `App\Services\ManualTransferGateway` stub (`name() => 'transferencia'`) + binding en `AppServiceProvider` (excepción documentada: `Contracts/` para puertos externos; estrategia de resolución multi-gateway pertenece a Fase 2, sin `confirm`, `createPreference` ni webhooks en Fase 1).
- [ ] Tests Feature básicos: migraciones existen, relaciones, casts enum, scopes, `CHECK`/`FK`, **snapshot independiente** (crear producto → crear `OrderLine` con snapshot → modificar producto → `OrderLine` inalterado), `PaymentGateway` resuelve a `ManualTransferGateway`.
- [ ] Pint, PHPStan nivel 8 (`app/`) y Pest en verde; CI `lint→stan→test`.

Fase 2 (lógica) queda fuera de esta entrega y tendrá sus propios criterios (transacción, stock, congelado con bcmath, rutas).

## Decisiones arquitectónicas

- **Tablas `orders`/`order_lines`**: snapshot desnormalizado para congelar precio (Spec 00:23, regla 102). No se usa `carts` persistida. `order_lines` conserva `specs` y `m2_por_caja` para reconstruir histórico sin depender de `products`. `cantidad` entera evita inventar decimal.
- **Dinero**: `BIGINT` centavos + `bcmath` (`docs/arquitectura.md: Dinero y unidades`, ADR-003). `subtotal_cents = Σ cantidad × precio_unitario_cents` con `bcmul/bcadd` en Fase 2, no con `CHECK` SQL.
- **Enum**: valores internos inglés + `label()` español, igual que `ProductSaleUnit`/`UserRole` (`app/Enums`). Grafo explícito, sin máquina de estados en Fase 1.
- **Puerto `PaymentGateway`**: `app/Contracts/PaymentGateway` (interfaz minimalista `name(): string`) + `ManualTransferGateway` (adaptador inicial) con DI `bind()` (`AppServiceProvider`), análogo a `ShippingCalculator` pero en `Contracts/` por claridad de puertos externos (excepción aprobada, se documenta en `arquitectura.md`). Fase 2 definirá resolución `payment_method → gateway` y operaciones `createPreference/webhook/confirm`.
- **m2_por_caja**: `decimal(8,2) → string` preserva precisión; nunca `float`.
- **Sin anticipación**: no se crea `PlaceOrderAction`, controladores, `DTOs/Events/Listeners/Jobs`, descuentos ni reserva con vencimiento (ADR-005 diferida).

## Evolución documentada (no anticipada)

- Fase 2 creará `PlaceOrderAction` (transacción, validación `cantidad ≤ stock` + `activo`, bcmath, `audit_logs` `order.created`, `shipping_cost_cents` snapshot validado), controladores anónimos y `ConfirmPaymentAction`.
- Spec 08 añadirá estados, despacho y restitución de stock.

## Tareas técnicas (Fase 1)

- [ ] Migraciones `create_orders` y `create_order_lines` (campos arriba, `CHECK`, índices, FKs; `cantidad >0` entero, `m2_por_caja` decimal).
- [ ] Enum `OrderStatus`.
- [ ] Modelos `Order`/`OrderLine` (casts, relaciones, scopes; `m2_por_caja string`).
- [ ] Factories + `PaymentGateway` (`name()` solo) + `ManualTransferGateway` + binding.
- [ ] Tests Feature (`tests/Feature/Orders/`, `tests/Feature/Payments/PaymentGatewayContractTest`) — snapshot independencia, no "congelamiento durante checkout".
- [ ] Calidad: `make format` → `make lint` → `make stan` → `make test` (una suite, `ceramica_test`).
- [ ] Actualizar `docs/arquitectura.md` (Orders 07 Fase 1, `Contracts/`, grafo `OrderStatus`) y `docs/roadmap.md` al cerrar Fase 1 (no en este borrador).

## Nota de handoff para el agente implementador

Fase 1 es **solo estructura**: no implementar `PlaceOrderAction`, controladores, rutas, ni lógica de stock/pagos, ni state machine, ni `confirm`/`createPreference`/webhooks. TDD: migraciones → enum → modelos/factories → contratos → tests → calidad. Después de aprobar esta spec:

1. Rama `feat/pedidos-07-estructura` desde `main`.
2. `php artisan make:migration/enum/model` con `--no-interaction`.
3. Tests primero (red) luego modelos (green).
4. `make lint`/`stan`/`test` + `npm run build` si toca vistas.
5. PR a `main` con CI verde, actualizar `arquitectura.md`/`roadmap.md` solo al mergear (excepción `AGENTS.md:193`).

Reglas: `AGENTS.md` + `.ai/rules` (`grep -rin 'order\|pedido\|pago\|bcmath' .ai/rules`), `PROJECT_PRINCIPLES.md` TDD/ADR, bcmath siempre en centavos, controladores delgados (Fase 2).
