# Spec 08 — Gestión de pedidos

- **Estado**: **borrador — pendiente de aprobación del dueño**
- **Base**: Spec 07 cerrada (101–128: `orders`/`order_lines`, `OrderStatus`, `PaymentGateway`, `PlaceOrderAction` con `lockForUpdate`/`bcmath`/`audit`, `CheckoutController` + `MercadoPagoGateway`), Spec 06 (93–100 envío por CP), Spec 03 (61–67 productos y stock), Spec 01 (roles admin/vendedor/depósito + `AuditRecorder`), ADR-003 (centavos + bcmath), ADR-004 (auditoría), ADR-005 (gestión de stock), ADR-006 (puertos).
- **Decisiones del dueño incorporadas (2026-09-10)**: stock reservado al crear el pedido; webhook con validación de firma **y** consulta a la API; ventas manuales por WhatsApp diferidas a una fase posterior.

## Conflicto con ADR-005 — leer antes de aprobar

La decisión de **reservar stock al crear el pedido revierte ADR-005**, que está aceptada desde el
2026-08-05 y decidió exactamente lo contrario: que el stock desciende al confirmarse el pago.
ADR-005 evaluó esta misma alternativa y la descartó con un argumento concreto sobre este negocio:

> Reservar temprano (carrito/checkout) congelaría stock por compras que nunca se pagan
> (transferencias que no llegan), que es el escenario más frecuente de no-cierre.

Ese argumento sigue siendo válido y es el costo real de la decisión tomada. La reversión está
razonada en **[ADR-012](../adr/ADR-012-reserva-stock-al-crear-pedido.md)**, que es *propuesta* y se
aprueba junto con esta spec. Aprobar la Spec 08 implica aprobar ADR-012; rechazar ADR-012 obliga a
reescribir las reglas 143–149 de esta spec según ADR-005.

Dato que ADR-005 no tenía: la decisión de agosto **nunca se implementó**. Hoy el stock no baja en
ningún momento, así que no hay comportamiento en producción que se esté rompiendo.

### Alcance real de la reversión

El supuesto de ADR-005 está escrito en varios documentos cerrados. Aprobar ADR-012 obliga a
enmendarlos —no a reescribirlos: la anotación de sincronía es el mecanismo que ya usó el proyecto
para el importador y para la 07.4—:

| Documento | Qué dice hoy | Qué exige la reversión |
|---|---|---|
| `docs/specs/00-dominio.md` **regla 21** | "El **stock desciende** al confirmarse el pago (ADR-005)" | **Enmienda de una regla numerada de la spec fundacional.** Es el punto más delicado de toda la reversión |
| `docs/specs/00-dominio.md:69` y `:163` | "El stock **no se reserva** en el carrito"; "Gestión del stock: al confirmar el pago" | La primera sigue siendo cierta (el carrito nunca reserva); la segunda se enmienda |
| `docs/specs/05-carrito.md:13`, `:58` | "el stock desciende solo al confirmar el pago (ADR-005)" | Enmienda: el carrito sigue sin reservar, pero el pedido sí |
| `docs/specs/07-checkout-fase2.md` regla 113 | "No descuenta stock (ADR-005 → `ConfirmPaymentAction` Spec 08)" | Se invierte: `PlaceOrderAction` pasa a descontar (regla 143) |
| `docs/ubiquitous-language.md` | No tiene términos para reserva ni vencimiento | Incorporar **Reserva de stock** y **Vencimiento de pedido** al glosario |

Ninguno de estos documentos se toca mientras la Spec 08 sea borrador: hoy describen correctamente
la decisión vigente.

## Objetivo

Cerrar el circuito operativo del pedido: que un pago realmente confirmado mueva el pedido a
`paid` sin intervención manual, que el stock refleje la realidad en todo momento, y que el
depósito tenga una pantalla desde donde despachar. Hoy el pedido nace en `PendingPayment` y se
queda ahí para siempre, aunque el cliente haya pagado.

## Contexto — qué falta hoy exactamente

Verificado contra MercadoPago sandbox el 2026-09-10 (pedido #10, pago aprobado):

- Nada escucha a MercadoPago: el pedido queda `PendingPayment` con el pago acreditado.
- `OrderStatus` declara cinco casos pero **solo se usa `PendingPayment`**; no hay transiciones.
- **El stock no se descuenta nunca**. `PlaceOrderAction` lo valida (`cantidad > stock` lanza
  `DomainException`) pero jamás lo modifica. Se puede vender la misma caja infinitas veces.
- No hay pantalla de pedidos: ni para admin, ni para depósito.
- La transferencia no tiene forma de confirmarse.

## No-objetivos explícitos

Sin ventas manuales por WhatsApp (fase posterior, ver *Evolución documentada*). Sin descuentos
(Spec 09). Sin emails al cliente. Sin devoluciones ni notas de crédito. Sin movimientos de stock
como entidad propia (ADR-005: eso es *Extract Stock*, Fase 3). Sin multi-gateway genérico.

## Máquina de estados

```
                  ┌──────────────────┐
                  │ pending_payment  │  ← PlaceOrderAction (stock ya reservado)
                  └────────┬─────────┘
             ┌─────────────┼──────────────┐
             ▼             ▼              ▼
        ┌────────┐   ┌───────────┐  ┌───────────┐
        │  paid  │   │ cancelled │  │ cancelled │
        └───┬────┘   │ (manual)  │  │ (vencido) │
            ▼        └───────────┘  └───────────┘
      ┌──────────┐         └──── restitución de stock ────┘
      │ shipped  │
      └────┬─────┘
           ▼
     ┌───────────┐
     │ delivered │
     └───────────┘
```

`paid → cancelled` queda **fuera de alcance**: cancelar un pedido ya cobrado implica devolver
dinero, y eso es otra spec.

## Reglas de negocio (continúan la numeración global desde 142, último número usado por la Spec 06.2)

### Reserva de stock

143. **El stock se reserva al crear el pedido**: `PlaceOrderAction` descuenta `product.stock`
     dentro de la misma transacción y del mismo `lockForUpdate` que ya usa para validar. Quien
     compra tiene la mercadería asegurada. Esto **extiende la regla 110** (Spec 07.2), que solo
     validaba; la Spec 07.2 no se reabre, se anota la extensión.
144. La validación `cantidad > stock` de la regla 110 se mantiene tal cual y sigue siendo la
     primera línea de defensa: el descuento ocurre después, sobre las filas ya bloqueadas.
145. **Restitución de stock** al pasar a `cancelled`, sea manual o por vencimiento: se devuelve
     exactamente `order_lines.cantidad` a `product.stock`, dentro de una transacción con
     `lockForUpdate`. Un pedido cancelado dos veces no restituye dos veces (regla 152).
146. La restitución usa las cantidades congeladas en `order_lines`, **nunca** recalcula desde el
     producto: el precio y la conversión m²→cajas ya quedaron fijados al crear el pedido.

### Vencimiento de pedidos impagos

147. Un pedido `PendingPayment` **vence** pasado un plazo configurable desde su creación
     (`config('orders.expiracion_horas')`, por defecto **24 horas**), pasa a `cancelled` y
     restituye stock. Sin esto la reserva de la regla 143 congelaría mercadería para siempre por
     cada carrito abandonado en MercadoPago.
148. El vencimiento lo aplica un comando `orders:expire-unpaid` idempotente, pensado para correr
     periódicamente. Procesa pedido por pedido en su propia transacción: un pedido que falle no
     impide vencer al resto.
149. El comando **nunca** toca pedidos que no estén en `PendingPayment`, y registra en `audit`
     cada vencimiento (`order.expired`) con el detalle de lo restituido.

### Confirmación de pago

150. **`ConfirmPaymentAction`** es el único camino a `paid`. Recibe el pedido y el origen de la
     confirmación (`mercadopago` o `manual`), valida la transición, cambia el estado y registra
     `order.paid` en `audit` con el actor. **No toca stock**: ya se reservó en la regla 143.
151. La acción valida que el pedido esté en `PendingPayment`. Un pedido `cancelled` (por
     vencimiento, típicamente) que reciba una confirmación de pago **no pasa a `paid`**: se
     registra `order.paid_after_cancel` en `audit` y se avisa en el panel, porque implica plata
     cobrada sin stock reservado y necesita intervención humana.
152. **Idempotencia**: confirmar un pago ya confirmado es un no-op silencioso. MercadoPago
     reintenta las notificaciones, así que esto no es un caso raro sino el caso normal.

### Webhook de MercadoPago

153. **`POST /webhook/mercadopago`** (`webhook.mercadopago`, sin `auth`, exento de CSRF, sin
     sesión). Responde **200 siempre que la notificación sea legítima**, incluso si no hay nada
     que hacer: un 4xx/5xx hace que MercadoPago reintente indefinidamente.
154. **Validación de firma obligatoria**: se verifica el header `x-signature` (con `x-request-id`
     y el `data.id`) contra `MERCADOPAGO_WEBHOOK_SECRET` usando comparación en tiempo constante.
     Firma ausente o inválida → **401**, sin procesar nada, con registro en `audit`.
155. **El contenido de la notificación no se cree nunca**: del payload se toma únicamente el ID
     del pago, y el estado real se consulta contra la API de MercadoPago
     (`PaymentClient::get()`). La decisión de marcar `paid` se toma con esa respuesta, jamás con
     lo que llegó por HTTP.
156. El pedido se localiza por `external_reference` (regla 123), que ya contiene el `order->id`.
     Referencia inexistente → 200 + `audit`, sin excepción: puede ser una notificación de otra
     aplicación o una prueba.
157. **El monto se verifica**: si el pago aprobado no coincide con `order.total_cents`, el pedido
     **no** pasa a `paid`; se registra `order.payment_amount_mismatch` en `audit` y queda visible
     en el panel. Esta regla nace de un defecto real: hasta el 2026-09-10 la preferencia omitía el
     envío y MercadoPago cobraba el subtotal.
158. Solo `status === 'approved'` confirma. `pending`/`in_process` → 200 sin cambios;
     `rejected`/`cancelled` → 200 sin cambios, el pedido sigue `PendingPayment` y el cliente puede
     reintentar con la regla 126.

### Confirmación manual de transferencia

159. El admin marca un pedido `transferencia` como pagado desde el panel, lo que invoca el mismo
     `ConfirmPaymentAction` con origen `manual`. Queda auditado con el usuario que lo hizo.
160. La confirmación manual está restringida a **admin**. El vendedor ve los pedidos pero no
     confirma cobros.

### Panel y vista depósito

161. **`GET /admin/pedidos`**: listado con filtros por estado y búsqueda por email o ID.
     Accesible a admin y vendedor. Muestra los pedidos que requieren atención humana (reglas 151
     y 157) destacados.
162. **`GET /admin/pedidos/{pedido}`**: detalle con líneas, totales, datos de envío, medio de
     pago, estado y traza de auditoría del pedido.
163. **Vista depósito** (`GET /admin/despacho`): solo pedidos `paid`, ordenados por antigüedad,
     con lo que hace falta para preparar el envío (productos, cantidades, CP, dirección). No
     muestra importes: el depósito no necesita ver plata.
164. **`paid → shipped`** lo puede hacer admin o depósito. **`shipped → delivered`**, también.
     Ambas transiciones quedan auditadas.
165. **`pending_payment → cancelled` manual** es solo de admin, y restituye stock (regla 145).
166. Toda transición de estado pasa por una única `TransitionOrderStatusAction` que valida contra
     la máquina de estados: ningún controlador escribe `order.status` directamente.

## Matriz de permisos

| Acción | Público | admin | vendedor | depósito |
|---|---|---|---|---|
| `POST /webhook/mercadopago` | ✓ (firma) | — | — | — |
| `GET /admin/pedidos`, `GET /admin/pedidos/{pedido}` | — | ✓ | ✓ | — |
| Confirmar pago manual (transferencia) | — | ✓ | — | — |
| Cancelar pedido impago | — | ✓ | — | — |
| `GET /admin/despacho` | — | ✓ | — | ✓ |
| `paid → shipped`, `shipped → delivered` | — | ✓ | — | ✓ |

## Casos borde

- Webhook duplicado o reintentado → no-op idempotente (regla 152), 200.
- Webhook de un pago aprobado sobre un pedido ya vencido y cancelado → regla 151, requiere
  intervención humana. **Es el riesgo estructural de la decisión de vencimiento**: alguien pagó
  y el stock ya volvió a la venta.
- Webhook con monto distinto al total → regla 157, no confirma.
- Pago aprobado en MercadoPago mientras el admin confirma la transferencia a mano → la segunda
  confirmación es no-op (regla 152).
- Cancelación y vencimiento simultáneos sobre el mismo pedido → `lockForUpdate` sobre el pedido
  serializa; la segunda ve el estado ya cambiado y no restituye.
- Producto borrado después de crear el pedido → la restitución de la regla 145 lo saltea sin
  fallar; `order_lines` conserva los datos congelados. La regla 67 ya impide borrar productos con
  pedidos, así que es defensa en profundidad.
- `MERCADOPAGO_WEBHOOK_SECRET` ausente → el webhook responde 401 a todo y lo registra: preferible
  a aceptar notificaciones sin verificar.

## Criterios de aceptación

- [ ] `PlaceOrderAction` descuenta stock en la misma transacción; test de dos pedidos concurrentes
      sobre el mismo producto donde el segundo falla por stock insuficiente.
- [ ] `TransitionOrderStatusAction` con la máquina de estados completa; test por cada transición
      válida y por cada inválida.
- [ ] `ConfirmPaymentAction` idempotente, auditada, con los dos orígenes; test de doble
      confirmación y de confirmación sobre pedido cancelado.
- [ ] Comando `orders:expire-unpaid` idempotente con restitución de stock y auditoría; test con
      pedidos dentro y fuera del plazo.
- [ ] Webhook: firma válida/inválida/ausente, consulta a la API (mockeada, **sin red**), monto
      coincidente y no coincidente, `external_reference` inexistente, estados distintos de
      `approved`, notificación duplicada.
- [ ] Panel de pedidos, detalle y vista depósito con la matriz de permisos cubierta por tests.
- [ ] Pint, PHPStan nivel 8, Pest verde, CI verde, PR a `main`.

## Riesgos y puntos abiertos

1. **No hay scheduler, y está documentado como decisión.** `bootstrap/app.php` no define
   `withSchedule` y `routes/console.php` solo tiene `inspire`; `docs/arquitectura.md` lo deja
   explícito al describir la limpieza de temporales del importador: *"Sin scheduler, porque ningún
   entorno del proyecto lo ejecuta"*. El comando de la regla 148 necesita algo que lo dispare, y en
   Render eso es un **Cron Job** aparte del web service —que además hoy ni siquiera corre las
   migraciones solo (`docs/deployment/staging.md §11`)—. Alternativa sin infraestructura nueva:
   disparar el vencimiento de forma perezosa al listar pedidos, más simple pero menos fiable.
   **Requiere decisión del dueño**; si se adopta el Cron Job, amerita ADR propia.
2. **El plazo de 24 horas es una propuesta, no un dato del negocio.** Una transferencia bancaria
   puede tardar más que un pago con tarjeta. Puede convenir un plazo distinto por medio de pago.
3. **La reserva de stock invierte el riesgo**: se elimina el sobreventa, pero aparece el carrito
   abandonado que congela mercadería hasta el vencimiento. Con catálogo chico y stock ajustado
   esto se nota.
4. **`MERCADOPAGO_WEBHOOK_SECRET` es una credencial nueva**, distinta del access token. Hay que
   generarla en el panel de MercadoPago y cargarla en Render. Se neutraliza en `phpunit.xml` como
   el resto (sincronía 2026-09-10).
5. **El webhook necesita URL pública en desarrollo**: mismo túnel que se usó para verificar la
   07.4.

## Evolución documentada (no anticipada)

- **Spec 08.2**: ventas manuales por WhatsApp (alta de pedido desde el panel, opcionalmente con
  link de pago de MercadoPago), diferida por decisión del dueño para asentar antes la máquina de
  estados.
- **Spec 09**: descuentos por medio de pago y por monto.
- **Fase 3**: *Extract Stock* (movimientos de stock como entidad con auditoría propia, ADR-005),
  que reemplazaría el descuento directo sobre `products.stock` de la regla 143.

## Tareas técnicas

- [ ] Este documento → aprobación del dueño, con las decisiones de los puntos abiertos 1 y 2.
- [ ] Rama `feat/pedidos-08` desde `main` (post merge del PR de sincronía).
- [ ] TDD en este orden: máquina de estados → reserva y restitución de stock → `ConfirmPaymentAction`
      → comando de vencimiento → webhook → panel y despacho.
- [ ] ADR nueva si se resuelve agregar Cron Job en Render (punto abierto 1).
- [ ] Al aprobar: enmendar los documentos listados en *Alcance real de la reversión* (regla 21 de Spec 00 incluida) y marcar ADR-012 como aceptada / ADR-005 como reemplazada.
- [ ] Actualizar `docs/arquitectura.md`, `docs/roadmap.md`, `docs/ubiquitous-language.md` y `.ai/rules/` al cerrar.

## Nota de handoff

La Spec 07.4 quedó verificada contra la API real el 2026-09-10; leer su sección *Sincronía
2026-09-10* antes de tocar `MercadoPagoGateway`: explica por qué `auto_return` es condicional y
por qué el envío viaja en `shipments.cost`. La regla 157 (verificación de monto) existe
justamente porque ese segundo defecto llegó a producir un cobro incorrecto en sandbox. Ningún test
puede alcanzar la API de MercadoPago: las credenciales están neutralizadas en `phpunit.xml`.
