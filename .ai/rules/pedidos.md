---
paths:
  - 'app/Actions/ConfirmPaymentAction.php'
  - 'app/Actions/CancelOrderAction.php'
  - 'app/Actions/TransitionOrderStatusAction.php'
  - 'app/Enums/OrderStatus.php'
---

# Pedidos — máquina de estados y movimientos de stock (Spec 08)

## `order.status` se escribe en UN solo lugar
`TransitionOrderStatusAction` es el único camino (regla 166): valida contra la máquina de estados de `OrderStatus`, persiste y audita `order.status_changed` con `previous`/`new`. Ningún controlador ni Action escribe `status` directamente — `ConfirmPaymentAction` y `CancelOrderAction` la invocan. El grafo vive en el **enum** (`transicionesPermitidas()`/`puedeTransicionarA()`), no en la Action, porque es conocimiento del estado: `pending_payment → paid → shipped → delivered`, `cancelled` desde los tres primeros, `delivered` y `cancelled` finales, y ningún estado consigo mismo. La transición NO toca stock.

## La transacción abre bloqueando el pedido y RELEE su estado adentro
Sin eso la idempotencia de la regla 152 no existe: dos notificaciones concurrentes del mismo pago leen `PendingPayment` las dos y descuentan stock dos veces. MercadoPago **reintenta por diseño**, así que es el caso esperado, no el excepcional. El patrón es `Order::query()->whereKey(...)->lockForUpdate()->firstOrFail()` como primera línea de la transacción, y decidir sobre **ese** objeto, nunca sobre el que llegó por parámetro. Vale igual para `CancelOrderAction`: el lock del pedido es lo que serializa una cancelación contra una confirmación simultánea.

## Los productos se bloquean ORDENADOS por `product_id`
Descuento y restitución agrupan las líneas por producto, `ksort` sobre las cantidades y `->orderBy('id')->lockForUpdate()`. El orden determinístico es lo que evita el deadlock entre dos transacciones que tocan los mismos productos en secuencia distinta. Si agregás otro caso de uso que mueva stock, respetá el mismo orden.

## Las cantidades salen de `order_lines`, nunca del producto
La conversión m²→cajas quedó congelada al crear el pedido (regla 149). Restituir recalculando desde el producto devolvería una cantidad distinta si cambió `m2_por_caja`. El snapshot es la fuente de verdad.

## El stock negativo es correcto, no un bug a "corregir"
Regla 145: un pago **ya cobrado** nunca se rechaza por falta de stock. El pedido pasa a `paid`, el stock queda negativo y se audita `order.stock_negative` (reposición pendiente). El fundamento es del negocio: el comercio se abastece directo del fabricante, así que la mercadería siempre se consigue. `products.stock` es `integer` sin `CHECK >= 0`, a diferencia de `shipping_rates.costo_cents`. No agregues un guard que rechace el pago.

## Un pago sobre un pedido cancelado deja el pedido TRABADO a propósito
Regla 151: no pasa a `paid`, se audita `order.paid_after_cancel` y ahí queda. **No existe override y no hay que inventarlo**: es una decisión del dueño (el pago debe ser completo) y se resuelve fuera del sistema caso por caso. La asimetría con la regla 145 es intencional: allá el monto es correcto y solo falta mercadería; acá se cobró sobre un pedido dado de baja.

## Acciones de auditoría de esta spec
`order.status_changed` (toda transición), `order.paid` (con el **origen** en el payload: el webhook corre sin sesión, así que el actor es `null`), `order.stock_negative`, `order.paid_after_cancel`, `order.stock_restored`. Esta última no está nombrada en la spec: se agregó por ADR-004, que manda auditar los cambios de stock, para que la devolución deje rastro.

## Lo que NO está en la Action y hay que mirar al implementar 08.c
La restricción de la regla 159 —**la confirmación manual vale solo para pedidos de `transferencia`**— no vive en `ConfirmPaymentAction`: la spec la ubica en el panel, que es 08.c. Hoy `execute($orderDeMercadoPago, 'manual')` marca el pedido pagado y descuenta stock sin que nadie haya cobrado. Si el control queda solo en el controlador o en la Policy, se repite exactamente el patrón que HIG-06 tuvo que corregir en `PlaceOrderAction`, donde la validación la hacía el Form Request y la Action confiaba.

## Precisión sobre "nadie escribe `status`"
`PlaceOrderAction` sí escribe `status` al **crear** el pedido (`Order::create([...'status' => OrderStatus::PendingPayment...])`). No es una transición —es el estado inicial— y por eso no pasa por `TransitionOrderStatusAction`. La regla es sobre **cambiar** de estado, no sobre nacer en uno.
