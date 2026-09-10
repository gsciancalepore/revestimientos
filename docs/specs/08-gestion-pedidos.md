# Spec 08 — Gestión de pedidos

- **Estado**: **borrador — pendiente de aprobación del dueño**
- **Base**: Spec 07 cerrada (101–128: `orders`/`order_lines`, `OrderStatus`, `PaymentGateway`, `PlaceOrderAction` con `lockForUpdate`/`bcmath`/`audit`, `CheckoutController` + `MercadoPagoGateway`), Spec 06 (93–100 envío por CP), Spec 03 (61–67 productos y stock), Spec 01 (roles admin/vendedor/depósito + `AuditRecorder`), ADR-003 (centavos + bcmath), ADR-004 (auditoría), ADR-005 (gestión de stock), ADR-006 (puertos).
- **Decisiones del dueño incorporadas (2026-09-10)**: **stock descontado al confirmarse el pago** (ADR-005 ratificada, tras evaluar y descartar la reserva al crear el pedido); webhook con validación de firma **y** consulta a la API; ventas manuales por WhatsApp diferidas a una fase posterior.

## Decisión de stock — ADR-005 ratificada

Durante la definición de esta spec se evaluó **reservar stock al crear el pedido**, lo que habría
revertido ADR-005. Se descartó el mismo día: obligaba a un vencimiento automático de pedidos
impagos y, con él, a un scheduler que **ningún entorno del proyecto ejecuta** —`docs/arquitectura.md`
lo deja explícito—, además de enmendar la regla 21 de `00-dominio.md`. El análisis queda archivado
en [ADR-012](../adr/ADR-012-reserva-stock-al-crear-pedido.md), marcada como descartada.

**Vigente y implementada por esta spec: ADR-005.** El stock desciende al confirmarse el pago, el
carrito y el pedido `pending_payment` no comprometen stock, y se restituye al cancelar un pedido ya
pagado o despachado. Ninguna spec cerrada necesita enmienda: `00-dominio.md` regla 21,
`05-carrito.md` y `07-checkout-fase2.md` ya describen exactamente este comportamiento.

El riesgo que ADR-005 aceptó explícitamente —dos pagos casi simultáneos sobre el mismo producto sin
stock para ambos— se materializa en la **regla 145** de esta spec, que define qué hacer cuando eso
pasa.

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
                  │ pending_payment  │  ← PlaceOrderAction (no compromete stock)
                  └────────┬─────────┘
                     ┌─────┴──────┐
                     ▼            ▼
                ┌────────┐  ┌───────────┐
                │  paid  │  │ cancelled │  cancelación manual del admin:
                └───┬────┘  └───────────┘  no toca stock (nunca se descontó)
                    │
                    │  ConfirmPaymentAction descuenta stock
                    ▼
              ┌──────────┐
              │ shipped  │
              └────┬─────┘
                   ▼
             ┌───────────┐
             │ delivered │
             └───────────┘

    paid ──┐
           ├──► cancelled  con RESTITUCIÓN de stock (regla 146).
 shipped ──┘                La devolución del dinero es un trámite fuera del sistema.
```

`paid → cancelled` queda **fuera de alcance**: cancelar un pedido ya cobrado implica devolver
dinero, y eso es otra spec.

## Reglas de negocio (continúan la numeración global desde 142, último número usado por la Spec 06.2)

### Descuento y restitución de stock

143. **El stock desciende al confirmarse el pago** (ADR-005): `ConfirmPaymentAction` descuenta
     `product.stock` dentro de su propia transacción, con `lockForUpdate` sobre las filas de los
     productos involucrados. Ni el carrito ni un pedido `pending_payment` comprometen stock.
144. La validación de la regla 110 (`cantidad > stock` en `PlaceOrderAction`) se mantiene tal cual,
     pero **no es garantía**: el stock puede haberse agotado entre el pedido y el pago. Por eso
     `ConfirmPaymentAction` **revalida bajo lock** antes de descontar.
145. **Pago cobrado sin stock disponible**: si al confirmar el pago no alcanza el stock, el pedido
     **no pasa a `paid`** y la transacción no descuenta nada. Se registra
     `order.payment_without_stock` en `audit` y el pedido queda destacado en el panel como
     incidente. Es el riesgo que ADR-005 aceptó de forma explícita, y se resuelve con una persona,
     no automáticamente: hay plata cobrada y mercadería que no existe.
146. **Restitución de stock** al cancelar un pedido `paid` o `shipped`: se devuelve exactamente
     `order_lines.cantidad` a `product.stock`, en una transacción con `lockForUpdate`.
147. Cancelar un pedido `pending_payment` **no toca stock**, porque nunca se descontó.
148. La restitución usa las cantidades congeladas en `order_lines`, **nunca** recalcula desde el
     producto: la conversión m²→cajas ya quedó fijada al crear el pedido. Un pedido cancelado dos
     veces no restituye dos veces (regla 151).

### Confirmación de pago

149. **`ConfirmPaymentAction`** es el único camino a `paid`. Recibe el pedido y el origen de la
     confirmación (`mercadopago` o `manual`), valida la transición, **descuenta stock** (reglas
     143–145), cambia el estado y registra `order.paid` en `audit` con el actor. Estado y stock se
     mueven en la **misma transacción**: o pasan los dos, o no pasa ninguno.
150. La acción valida que el pedido esté en `PendingPayment`. Un pedido `cancelled` que reciba una
     confirmación de pago **no pasa a `paid`**: se registra `order.paid_after_cancel` en `audit` y
     queda destacado en el panel, porque implica plata cobrada sobre un pedido dado de baja.
151. **Idempotencia**: confirmar un pago ya confirmado es un no-op silencioso, sin descontar stock
     de nuevo. MercadoPago reintenta las notificaciones, así que esto no es un caso raro sino el
     caso normal.

### Webhook de MercadoPago

152. **`POST /webhook/mercadopago`** (`webhook.mercadopago`, sin `auth`, exento de CSRF, sin
     sesión). Responde **200 siempre que la notificación sea legítima**, incluso si no hay nada
     que hacer: un 4xx/5xx hace que MercadoPago reintente indefinidamente.
153. **Validación de firma obligatoria**: se verifica el header `x-signature` (con `x-request-id`
     y el `data.id`) contra `MERCADOPAGO_WEBHOOK_SECRET` usando comparación en tiempo constante.
     Firma ausente o inválida → **401**, sin procesar nada, con registro en `audit`.
154. **El contenido de la notificación no se cree nunca**: del payload se toma únicamente el ID
     del pago, y el estado real se consulta contra la API de MercadoPago
     (`PaymentClient::get()`). La decisión de marcar `paid` se toma con esa respuesta, jamás con
     lo que llegó por HTTP.
155. El pedido se localiza por `external_reference` (regla 123), que ya contiene el `order->id`.
     Referencia inexistente → 200 + `audit`, sin excepción: puede ser una notificación de otra
     aplicación o una prueba.
156. **El monto se verifica**: si el pago aprobado no coincide con `order.total_cents`, el pedido
     **no** pasa a `paid`; se registra `order.payment_amount_mismatch` en `audit` y queda visible
     en el panel. Esta regla nace de un defecto real: hasta el 2026-09-10 la preferencia omitía el
     envío y MercadoPago cobraba el subtotal.
157. Solo `status === 'approved'` confirma. `pending`/`in_process` → 200 sin cambios;
     `rejected`/`cancelled` → 200 sin cambios, el pedido sigue `PendingPayment` y el cliente puede
     reintentar con la regla 126.

### Confirmación manual de transferencia

158. El admin marca un pedido `transferencia` como pagado desde el panel, lo que invoca el mismo
     `ConfirmPaymentAction` con origen `manual`. Queda auditado con el usuario que lo hizo, y
     descuenta stock igual que la confirmación automática.
159. La confirmación manual está restringida a **admin**. El vendedor ve los pedidos pero no
     confirma cobros.

### Panel y vista depósito

160. **`GET /admin/pedidos`**: listado con filtros por estado y búsqueda por email o ID.
     Accesible a admin y vendedor. Muestra destacados los pedidos que requieren atención humana
     (reglas 145, 150 y 156).
161. **`GET /admin/pedidos/{pedido}`**: detalle con líneas, totales, datos de envío, medio de
     pago, estado y traza de auditoría del pedido.
162. **Vista depósito** (`GET /admin/despacho`): solo pedidos `paid`, ordenados por antigüedad,
     con lo que hace falta para preparar el envío (productos, cantidades, CP, dirección). No
     muestra importes: el depósito no necesita ver plata.
163. **`paid → shipped`** lo puede hacer admin o depósito. **`shipped → delivered`**, también.
     Ambas transiciones quedan auditadas.
164. **Cancelaciones, todas de admin**: `pending_payment → cancelled` no toca stock (regla 147);
     `paid → cancelled` y `shipped → cancelled` **restituyen** (regla 146). La devolución del
     dinero es un trámite **fuera del sistema** (panel de MercadoPago o transferencia bancaria):
     esta spec no automatiza reintegros, solo deja el pedido y el stock consistentes.
165. Toda transición de estado pasa por una única `TransitionOrderStatusAction` que valida contra
     la máquina de estados: ningún controlador escribe `order.status` directamente.

## Matriz de permisos

| Acción | Público | admin | vendedor | depósito |
|---|---|---|---|---|
| `POST /webhook/mercadopago` | ✓ (firma) | — | — | — |
| `GET /admin/pedidos`, `GET /admin/pedidos/{pedido}` | — | ✓ | ✓ | — |
| Confirmar pago manual (transferencia) | — | ✓ | — | — |
| Cancelar pedido impago (`pending_payment`, sin restitución) | — | ✓ | — | — |
| Cancelar pedido pagado o despachado (con restitución) | — | ✓ | — | — |
| `GET /admin/despacho` | — | ✓ | — | ✓ |
| `paid → shipped`, `shipped → delivered` | — | ✓ | — | ✓ |

## Casos borde

- Webhook duplicado o reintentado → no-op idempotente (regla 151), 200, sin descontar stock dos
  veces.
- **Pago aprobado sin stock suficiente** (regla 145) → el pedido no pasa a `paid`, no se descuenta
  nada y queda como incidente. Es el riesgo que ADR-005 aceptó de forma explícita y el caso borde
  más importante de esta spec: hay dinero cobrado y mercadería que no existe.
- Webhook con monto distinto al total → regla 156, no confirma.
- Webhook de un pago aprobado sobre un pedido cancelado a mano → regla 150, incidente.
- Pago aprobado en MercadoPago mientras el admin confirma la transferencia a mano → la segunda
  confirmación es no-op (regla 151), el stock se descuenta una sola vez.
- Dos pagos casi simultáneos sobre el mismo producto con stock para uno solo → `lockForUpdate`
  serializa; el segundo cae en la regla 145.
- Cancelación y confirmación de pago simultáneas sobre el mismo pedido → el lock sobre el pedido
  serializa; la segunda ve el estado ya cambiado y no actúa.
- Cancelar dos veces un pedido pagado → la restitución no se aplica dos veces (regla 148).
- Producto borrado después de crear el pedido → el descuento y la restitución lo saltean sin
  fallar; `order_lines` conserva los datos congelados. La regla 67 ya impide borrar productos con
  pedidos, así que es defensa en profundidad.
- `MERCADOPAGO_WEBHOOK_SECRET` ausente → el webhook responde 401 a todo y lo registra: preferible
  a aceptar notificaciones sin verificar.

## Criterios de aceptación

- [ ] `TransitionOrderStatusAction` con la máquina de estados completa; test por cada transición
      válida y por cada inválida.
- [ ] `ConfirmPaymentAction` descuenta stock bajo `lockForUpdate` en la misma transacción que el
      cambio de estado; test de rollback que verifica que ni estado ni stock se movieron.
- [ ] Regla 145: test de pago confirmado con stock insuficiente → pedido sigue `PendingPayment`,
      stock intacto, `audit` con `order.payment_without_stock`.
- [ ] `ConfirmPaymentAction` idempotente y auditada, con los dos orígenes; test de doble
      confirmación que verifica que el stock se descuenta una sola vez, y de confirmación sobre
      pedido cancelado.
- [ ] Restitución al cancelar un pedido `paid`/`shipped`, y ausencia de restitución al cancelar
      uno `pending_payment`; test de doble cancelación.
- [ ] Webhook: firma válida/inválida/ausente, consulta a la API (mockeada, **sin red**), monto
      coincidente y no coincidente, `external_reference` inexistente, estados distintos de
      `approved`, notificación duplicada.
- [ ] Panel de pedidos, detalle y vista depósito con la matriz de permisos cubierta por tests.
- [ ] Pint, PHPStan nivel 8, Pest verde, CI verde, PR a `main`.

## Riesgos y puntos abiertos

1. **Qué hacer cuando se cobró y no hay stock (regla 145).** El sistema deja el incidente
   registrado y visible, pero **la política de negocio no está definida**: ¿se reintegra el dinero,
   se ofrece otro producto, se espera reposición? Es una decisión del dueño, no técnica.
   **Requiere respuesta antes de implementar el panel**, porque define qué acciones ofrece la
   pantalla de incidentes.
2. **La devolución de dinero al cancelar un pedido pagado queda fuera del sistema** (regla 164).
   Hay que confirmar que eso es aceptable operativamente: el admin restituye stock desde el panel y
   devuelve la plata a mano por el canal que corresponda.
3. **Los pedidos `pending_payment` se acumulan sin límite.** Sin reserva de stock esto no hace
   daño —no congela mercadería—, pero ensucia el panel con el tiempo. No hace falta resolverlo en
   esta spec; alcanza con el filtro por estado de la regla 160.
4. **`MERCADOPAGO_WEBHOOK_SECRET` es una credencial nueva**, distinta del access token. Hay que
   generarla en el panel de MercadoPago y cargarla en Render. Se neutraliza en `phpunit.xml` como
   el resto (sincronía 2026-09-10).
5. **El webhook necesita URL pública en desarrollo**: mismo túnel `cloudflared` que se usó para
   verificar la 07.4.
6. **Staging podría no tener las migraciones de `orders` aplicadas** (ver `docs/roadmap.md`,
   discrepancia detectada el 2026-09-10). Verificar antes de probar el webhook contra staging.

## Evolución documentada (no anticipada)

- **Spec 08.2**: ventas manuales por WhatsApp (alta de pedido desde el panel, opcionalmente con
  link de pago de MercadoPago), diferida por decisión del dueño para asentar antes la máquina de
  estados.
- **Spec 09**: descuentos por medio de pago y por monto.
- **Fase 3**: *Extract Stock* (movimientos de stock como entidad con auditoría propia, ADR-005),
  que reemplazaría el descuento directo sobre `products.stock` de la regla 143.
- **Reserva de stock al crear el pedido**: evaluada y descartada el 2026-09-10 por el costo de la
  infraestructura que exige. El análisis está en ADR-012; si la sobreventa se vuelve un problema
  real, ese es el punto de partida.

## Tareas técnicas

- [ ] Este documento → aprobación del dueño, con las decisiones de los puntos abiertos 1 y 2.
- [ ] Rama `feat/pedidos-08` desde `main` (post merge del PR de sincronía).
- [ ] TDD en este orden: máquina de estados → `ConfirmPaymentAction` con descuento de stock →
      restitución al cancelar → webhook → panel y despacho.
- [ ] Ninguna spec cerrada necesita enmienda: ADR-005 sigue vigente y `00-dominio.md` regla 21,
      `05-carrito.md` y `07-checkout-fase2.md` ya describen este comportamiento.
- [ ] Actualizar `docs/arquitectura.md`, `docs/roadmap.md` y `.ai/rules/` al cerrar.

## Nota de handoff

La Spec 07.4 quedó verificada contra la API real el 2026-09-10; leer su sección *Sincronía
2026-09-10* antes de tocar `MercadoPagoGateway`: explica por qué `auto_return` es condicional y
por qué el envío viaja en `shipments.cost`. La regla 157 (verificación de monto) existe
justamente porque ese segundo defecto llegó a producir un cobro incorrecto en sandbox. Ningún test
puede alcanzar la API de MercadoPago: las credenciales están neutralizadas en `phpunit.xml`.
