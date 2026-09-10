# ADR-012 — Reserva de stock al crear el pedido

- **Estado**: **descartada (2026-09-10)** — propuesta y retirada el mismo día, antes de escribir
  código. **[ADR-005](ADR-005-gestion-stock.md) sigue vigente**: el stock desciende al confirmarse
  el pago.
- **Por qué se descartó**: al ver el alcance real de la reversión —enmendar la regla 21 de la spec
  fundacional `00-dominio.md`, más `05-carrito.md` y `07-checkout-fase2.md`— y sobre todo la
  infraestructura que exigía —un vencimiento automático de pedidos impagos, con un scheduler que
  **ningún entorno del proyecto ejecuta**—, el dueño optó por mantener ADR-005 por ser más viable
  en este momento del proyecto.
- **Se conserva** este documento, en lugar de borrarlo, porque el análisis de la disyuntiva sigue
  siendo útil: si en el futuro la sobreventa se vuelve un problema real, acá está el razonamiento
  completo y lo que costaría adoptarla.
- **Origen**: decisión del dueño del 2026-09-10 al definir el alcance de la Spec 08, revertida por
  el mismo dueño ese día.

## Contexto

ADR-005 decidió que **el stock desciende cuando el pedido queda pagado**, y evaluó explícitamente
la alternativa de descontar al generar el pedido, descartándola con este argumento:

> Reservar temprano (carrito/checkout) congelaría stock por compras que nunca se pagan
> (transferencias que no llegan), que es el escenario más frecuente de no-cierre.

Al definir la Spec 08 el dueño optó por la alternativa descartada: **reservar el stock al crear el
pedido**. Esta ADR existe para que esa reversión quede razonada y fechada, y no aparezca como una
contradicción silenciosa entre la Spec 08 y ADR-005.

Contexto adicional que ADR-005 no tenía disponible en agosto:

- El checkout con MercadoPago ya está implementado y verificado (Spec 07.4, 2026-09-10). El pago
  con tarjeta se resuelve en minutos, no en días: la ventana entre pedido y pago es corta para el
  medio de pago que se espera mayoritario.
- Se comprobó que hoy **el stock no se descuenta en ningún momento**: la regla de ADR-005 nunca
  llegó a implementarse, así que no hay comportamiento en producción que preservar.

## Decisión

**El stock se reserva al crear el pedido**, dentro de la misma transacción y del mismo
`lockForUpdate` que ya valida disponibilidad en `PlaceOrderAction`:

- `pending_payment` **sí** compromete stock.
- Se restituye al pasar a `cancelled`, sea por cancelación manual del admin o por vencimiento
  automático del pedido impago.
- `ConfirmPaymentAction` deja de tocar stock: solo cambia estado y audita.
- El carrito sigue **sin** reservar (sigue siendo anónimo y en sesión): la reserva empieza en el
  pedido, no antes.

## Justificación

- Elimina la sobreventa: quien completa un pedido tiene la mercadería asegurada. Con stock físico
  bajo y productos de una sola caja disponible, cobrarle a alguien algo que ya no existe es peor
  que inmovilizarlo unas horas.
- El caso que ADR-005 quería evitar —vender dos veces la misma caja entre dos pagos casi
  simultáneos— desaparece por construcción en lugar de depender de que la validación bajo lock
  llegue a tiempo.
- Hace que el stock mostrado en el catálogo refleje lo realmente disponible para comprar.

## Consecuencias

- **Obliga a un vencimiento automático de pedidos impagos.** Sin él, cada carrito abandonado en
  MercadoPago congela mercadería para siempre. Spec 08 regla 147: plazo configurable, 24 h por
  defecto.
- **Obliga a una pieza de infraestructura que hoy no existe.** `docs/arquitectura.md` deja
  constancia de que *ningún entorno del proyecto ejecuta un scheduler*: el comando de vencimiento
  necesita un Cron Job en Render, o un disparo perezoso. **Punto abierto, requiere decisión.**
- Aparece un caso nuevo que antes era imposible: un pago que se acredita **después** de que el
  pedido venció y el stock volvió a la venta. Spec 08 regla 151 lo trata como incidente que
  requiere intervención humana, no como transición automática.
- `PlaceOrderAction` (Spec 07.2, cerrada) se extiende. La regla 110 pasa de solo validar a validar
  y descontar.
- **Enmienda la regla 21 de `docs/specs/00-dominio.md`**, la spec fundacional del proyecto, que
  fija el descenso de stock al confirmarse el pago. También quedan desactualizadas afirmaciones
  en `05-carrito.md` y `07-checkout-fase2.md`. El detalle completo está en la Spec 08, sección
  *Alcance real de la reversión*.
- El stock deja de ser "lo que se cobró" y pasa a ser "lo comprometido". Para conciliar contra
  caja hay que mirar los pedidos `paid`, no el stock.

## Alternativas

- **Mantener ADR-005 (descontar al pagar)**: más simple, sin vencimientos ni cron, y el stock
  coincide con la caja. Se descarta porque acepta la sobreventa entre pedido y pago, que es
  justamente el riesgo que el dueño quiso eliminar.
- **Reserva con vencimiento corto por medio de pago** (por ejemplo 30 min para MercadoPago y 48 h
  para transferencia): más fiel al comportamiento real de cada medio. No se adopta ahora por
  YAGNI, pero es la evolución natural si el plazo único de 24 h resulta grueso; queda registrada
  como punto abierto 2 de la Spec 08.
