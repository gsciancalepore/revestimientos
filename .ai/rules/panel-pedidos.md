---
paths:
  - 'app/Http/Controllers/OrderController.php'
  - 'app/Http/Controllers/DispatchController.php'
  - 'app/Policies/OrderPolicy.php'
  - 'resources/views/admin/pedidos/**'
  - 'resources/views/admin/despacho/**'
---

# Panel de pedidos y vista depósito (Spec 08 fase 08.c, reglas 146 y 159-165)

## Autorización por Policy, NO por middleware de rol
Las rutas de pedidos y despacho quedan **fuera** del grupo `role:admin` de `routes/web.php`, a diferencia de usuarios, categorías, productos y tarifas. No es un descuido: cada acción tiene su propia fila en la matriz de permisos. El vendedor ve pedidos y **no** cobra ni cancela; el depósito despacha y **nunca** ve plata ni entra al listado. Un `role:admin` de grupo haría imposible las dos cosas. La navegación del panel usa las mismas Policies (`@can`), así que nadie ve un acceso que no puede abrir.

## Los destacados se derivan; no agregues una columna
Regla 161. *Reposición pendiente* = pedido `paid` con alguna línea cuyo producto está en negativo: **se apaga sola** cuando el admin repone, y esa es la propiedad que la hace útil como señal. *Incidente de pago* = existe `order.payment_amount_mismatch` o `order.paid_after_cancel` en `audit_logs`: **no se apaga**, y está bien, porque esos pedidos quedan trabados a propósito. Una marca de "incidente atendido" exigiría columna y migración para un flujo que todavía no existe (YAGNI); si aparece un caso concreto, se evalúa entonces.

## Las auditorías del webhook NO son incidentes de pago
`webhook.signature_invalid`, `webhook.ignored`, `webhook.order_not_found`, `webhook.payment_not_found` y `order.stock_restored` registran qué pasó, pero no destacan ningún pedido —tres de ellas ni siquiera tienen pedido asociado—. La lista vive en `Order::ACCIONES_DE_INCIDENTE` y hay un test que se pone rojo si alguien la amplía.

## La regla 159 vive en la Action, aunque la spec la ubicara en el panel
La confirmación manual solo vale para pedidos de `transferencia`, y el guard está en `ConfirmPaymentAction`, no solo en el controlador ni en la Policy. Dejarlo en la pantalla repetía exactamente el patrón que HIG-06 tuvo que corregir en `PlaceOrderAction`. El panel además no ofrece el botón, pero eso es comodidad, no la garantía.

## Las pantallas no lanzan por un producto faltante; las Actions sí
`Order::necesitaReposicion()` trata un producto ausente como "no aplica", porque es una pantalla: hacerla explotar dejaría el panel entero caído por una inconsistencia que la regla 67 y la FK `restrictOnDelete` vuelven imposible. Descontar y restituir **sí** lanzan y auditan ante esa misma inconsistencia (regla 149). La asimetría es deliberada.
