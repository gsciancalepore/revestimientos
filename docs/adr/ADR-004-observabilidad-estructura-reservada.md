# ADR-004 — Observabilidad: estructura reservada (no implementar en el MVP)

- **Estado**: aceptado (2026-08-05)
- **Contexto**: el dueño pidió pensar la observabilidad desde el principio (logs,
  eventos, auditoría, métricas) aunque no se implemente todavía, para no
  rediseñar después.

## Decisión

Se **reserva el diseño** (dónde vivirá cada cosa) pero **no se implementa nada** en
el MVP salvo lo que surja naturalmente:

1. **Logs estructurados**: canal `stack` + `daily` (config de Laravel); contexto en
   las entradas (`order_id`, `user_id`, `product_id`) vía contexto de logging.
   Nunca datos de tarjeta ni credenciales.
2. **Eventos de dominio**: `app/Events/` ya es parte del diseño de Actions
   (ej: `OrderPaid`); son la base del futuro trazado.
3. **Auditoría**: tabla `audit_logs` (actor, acción, subject_type/id, payload,
   created_at) reservada para acciones críticas: cambios de precio, ajustes de
   stock, confirmaciones de pago, cambios de rol. Se crea en la primera spec que
   tenga una acción crítica (Spec 03/07) con un Listener sobre los eventos de
   dominio.
4. **Métricas**: en la fase de despliegue: health check (`/up`), latencia y estado
   de colas. Sin dashboards en el MVP.

## Consecuencias

- Las Actions disparan eventos de dominio desde el día uno → auditoría y
  notificaciones futuras se agregan sin tocar los casos de uso.
- No hay costos de implementación en el MVP (YAGNI), pero no hay deuda de diseño.
- La tabla de auditoría y el listener se detallan en la spec que los introduzca.

## Alternativas

- **Implementar auditoría + métricas ahora**: descartado — sin usuarios reales no
  hay criterios de diseño (principio 7: no optimizar sin medir).
- **No pensar en observabilidad**: descartado — forzaría cambios estructurales
  (cambio de tabla, logging, eventos) sobre código ya estable.
