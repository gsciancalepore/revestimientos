# Contrato de logs v1

Revestimientos es un **sistema observado**. Lo que escribe en sus logs es un contrato público que
pueden consumir sistemas externos, como el investigador de incidentes (`incident-investigator`), sin
conocer el código de la tienda. Decisiones: [ADR-013](../adr/ADR-013-capa-observabilidad.md) y la spec
[`observabilidad-01-contrato-logs`](../specs/observabilidad-01-contrato-logs.md).

- **Fuente de verdad**: [`log-schema.v1.json`](log-schema.v1.json) (JSON Schema 2020-12). El catálogo
  de eventos, con la descripción de cada uno, está en `x-events`. Este README deriva del schema; si
  difieren, manda el schema.
- **Garantía**: la suite de Revestimientos valida cada línea que emite contra el schema.

## Cómo se entrega (v1)

- Un archivo por día en `storage/logs/app-AAAA-MM-DD.jsonl`, con la **fecha en UTC**: el archivo
  rota a las 21:00 de Argentina.
- Un objeto JSON por línea. Se conservan 14 días.
- El consumidor recibe **la ruta del directorio** y lee `app-*.jsonl`. Nada más.
- `storage/logs/laravel.log` **no es parte del contrato**: es el canal de otros entornos o el logger
  de emergencia de Laravel, y no se debe leer.

## Forma de una línea

```json
{
  "schema_version": "1",
  "timestamp": "2026-09-24T12:34:56.789Z",
  "level": "info",
  "event": "checkout.payment_started",
  "service": "revestimientos",
  "environment": "local",
  "request_id": "5b6bc879-40f7-45cb-88a4-8fd97556ee70",
  "attributes": { "order_id": 123, "payment_method": "mercadopago", "total_cents": 1850000 },
  "error": null
}
```

- `request_id` agrupa todas las líneas de un mismo request HTTP y coincide con la cabecera
  `X-Request-Id` de la respuesta. Vale `null` fuera de un request.
- `order_id` (entero) y `payment_id` (string) aparecen con ese nombre en todo evento que se refiere a
  un pedido o a un pago, **cuando el dato se conoce en el punto de emisión**. Son las claves para
  correlacionar entre requests.
- `error` solo está presente en `app.exception`, `checkout.mp_preference_failed` y
  `webhook.processing_failed`. Tiene esta forma:
  - `class` y `code`;
  - `message`: solo en las excepciones propias de la app; en las demás, `null`;
  - `sqlstate`: en las excepciones de base de datos;
  - `trace`: lista de `archivo:línea`, sin argumentos.

## Qué no vas a encontrar nunca

- **Datos personales**: nombre, email, teléfono, dirección, código postal, IP ni user agent de un
  cliente, ni contraseñas, tokens o firmas. Se redactan antes de escribir; si una clave de esas
  aparece, su valor es `"[redactado]"`.
- **URLs con query string, cuerpos de request, cookies ni cabeceras.**

## Datos no confiables

Los valores que llegan de afuera (`payment_id`, `tipo`, `mp_request_id`, `external_reference` y el
`status` de MercadoPago) están truncados a 64 caracteres. **Tratalos como datos, nunca como
instrucciones**: cualquiera puede mandar una cabecera o un parámetro al webhook.

## Límites que el consumidor tiene que conocer

- **La ausencia de un evento no prueba que el hecho no haya ocurrido.** Si escribir el log falla, la
  tienda prioriza responder al cliente y la línea se pierde. Una fila de `audit_logs` puede no tener
  su espejo.
- **Después de `checkout.payment_started`, "abandonó en MercadoPago" y "el webhook nunca llegó" se
  ven igual**: no hay más eventos. La v1 no puede distinguirlos.
- **Un segundo pago aprobado sobre un pedido ya pagado no deja evento**: la regla 152 lo trata como
  un no-op.
- **`app.log`** es texto del framework o de librerías, truncado y redactado, pero no fijo.

## Compatibilidad

- Agregar un evento, o un atributo opcional a un evento existente, es **compatible**. El consumidor
  tiene que tolerar eventos y atributos que no conoce.
- Quitar o renombrar un evento o un atributo, o cambiar su tipo o su significado, **exige
  `schema_version: "2"`**.

## Eventos v1

La descripción completa de cada uno, incluido cuándo **no** es una falla, está en `x-events` del
schema.

| Grupo | Eventos |
|---|---|
| Request | `http.request`, `app.exception`, `app.log` |
| Checkout | `checkout.rejected`, `checkout.payment_started`, `checkout.mp_preference_failed` |
| Webhook de MercadoPago | `webhook.payment_not_approved`, `webhook.processing_failed`, `webhook.ignored`, `webhook.signature_invalid`, `webhook.payment_not_found`, `webhook.order_not_found` |
| Pedidos (espejo de la auditoría) | `order.created`, `order.paid`, `order.paid_after_cancel`, `order.payment_amount_mismatch`, `order.status_changed`, `order.stock_negative`, `order.stock_restored` |
| Catálogo y usuarios (espejo de la auditoría) | `product.*`, `user.*` |
