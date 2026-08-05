# ADR-005 — Gestión del stock: cuándo baja el stock

- **Estado**: aceptado (2026-08-05)
- **Contexto**: el stock es físico y único (compartido entre la web y las ventas de
  WhatsApp registradas manualmente). Hay que decidir en qué momento del ciclo de
  vida del pedido el stock desciende. Opciones evaluadas: al agregar al carrito,
  al iniciar checkout, al generar el pedido, al aprobar el pago, al despachar.

## Decisión

**El stock desciende cuando el pedido queda PAGADO** (pago confirmado):

- Tarjeta (MercadoPago): confirmación automática → baja en ese momento.
- Transferencia: confirmación manual del admin → baja al confirmar.
- Venta WhatsApp: se registra como pagada al cargarla (pago por fuera) → baja al
  registrarse.
- El carrito y el checkout **no reservan stock** (el carrito es anónimo y no
  persiste; un pedido pendiente de pago no compromete stock).
- Al **cancelar** un pedido pagado o despachado, el stock **se restituye**.
- El descuento y la restitución de stock ocurren dentro de la misma transacción que
  el cambio de estado, con bloqueo de fila del producto (locks de Postgres) para
  evitar ventas concurrentes por encima del stock real.

## Justificación

- El negocio es minorista con stock físico real y volumen bajo de pedidos
  pendientes; reservar temprano (carrito/checkout) congelaría stock por compras
  que nunca se pagan (transferencias que no llegan), que es el escenario más
  frecuente de no-cierre.
- Descontar al pagar mantiene el stock **consistente con la caja**: se descuenta lo
  que efectivamente se cobró.
- Riesgo aceptado: dos pedidos pagados casi simultáneos para el mismo producto con
  stock insuficiente. Mitigación: validación final dentro de la transacción con
  bloqueo de fila; el segundo pago falla y se informa al cliente.

## Consecuencias

- El estado `pendiente_de_pago` no garantiza disponibilidad (se informa al
  confirmar el pedido).
- El descuento de stock es parte del caso de uso `ConfirmPaymentAction` /
  `RegisterWhatsAppSaleAction`, no un listener suelto.
- El modelo de datos necesita estados de pedido explícitos y registros de la
  confirmación (quién y cuándo) para auditoría (ADR-004).

## Alternativas

- **Descontar al generar el pedido**: garantiza disponibilidad al comprador, pero
  compromete stock por pedidos no pagados; obligaría a reservas con vencimiento
  (complejidad que hoy no se justifica). Se revisará si crece la tasa de
  abandono/pedidos sin pago.
- **Descontar al despachar**: mantiene el stock máximo disponible pero corre el
  riesgo de vender más de lo que se tiene entre pago y despacho (roturas de
  caja/stock visual). Descartado para minorista con stock real.
