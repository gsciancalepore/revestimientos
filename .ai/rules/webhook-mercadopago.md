---
paths:
  - 'app/Http/Controllers/MercadoPagoWebhookController.php'
  - 'app/Actions/ProcessMercadoPagoNotificationAction.php'
  - 'app/Services/MercadoPagoSignatureVerifier.php'
  - 'app/Contracts/PaymentStatusQuery.php'
  - 'app/Services/MercadoPagoGateway.php'
---

# Webhook de MercadoPago (Spec 08 fase 08.b, reglas 153-158)

## El contenido de la notificación no se cree NUNCA
De lo que llega por HTTP se toma **solo el ID del pago**; el estado, el monto y el `external_reference` salen de consultar la API (regla 155). Cualquier decisión tomada con el cuerpo de la notificación es una decisión tomada con datos que el emisor controla. El filtro por tipo va **antes** de consultar: MercadoPago manda `merchant_order` y el IPN viejo por el mismo endpoint, y un `merchant_order` con firma perfectamente válida haría fallar la consulta porque su id no es el de un pago.

## El id del pago se lee del CUERPO, no de la query
PHP convierte el punto en guion bajo al parsear la query string, así que `?data.id=123` llega como `data_id`. `$request->query('data.id')` devuelve siempre vacío y la firma calculada sobre un id vacío nunca valida. El cuerpo (`data.id`) es la fuente; la query queda como fallback y se lee `data_id`.

## `PaymentClient` es `final`: la costura es el puerto, no el cliente
Misma trampa que `PreferenceClient` (07.4). Inyectar el cliente permite construirlo, no mockearlo: ningún test puede darle una respuesta. Por eso la consulta se expone como `App\Contracts\PaymentStatusQuery`, que `MercadoPagoGateway` implementa y los tests bindean en el contenedor con un doble. La regla 155 describía la otra costura; por qué no alcanzó está anotado en la §Sincronía 2026-09-11 de la spec. **No agregues `findPayment()` a `PaymentGateway`**: la transferencia bancaria no tiene pago remoto que consultar.

## El SDK tira excepción también cuando el pago no existe
`PaymentClient::get()` lanza `MPApiException` ante **cualquier** respuesta no-2xx, 404 incluido, así que "pago desconocido" no llega solo como `null`: hay que traducir el 404 a `null` dentro del gateway y propagar el resto. Sin esa traducción, una notificación de un pago que la cuenta no conoce —mezcla de sandbox y producción, o una prueba desde el panel— se responde 503 y MercadoPago la reintenta para siempre, cuando en realidad no había nada que hacer.

## 200 y 503 significan cosas distintas, y el 503 es deliberado
200 cuando se procesó **o cuando no había nada que hacer** (firma ok pero otro tipo, pedido inexistente, pago no aprobado): un 4xx/5xx ahí hace que MercadoPago reintente para siempre al pedo. **503 cuando la consulta a la API falla** por causa transitoria, a propósito, para que MercadoPago **sí** reintente: responder 200 ahí perdería el pago para siempre con el pedido en `PendingPayment`, que es justo el agujero que la Spec 08 vino a cerrar. "No hay nada que hacer" y "no pude averiguar si había algo que hacer" no son el mismo caso.

## Un test de request NO cubre la excepción de CSRF
Laravel saltea `ValidateCsrfToken` mientras corre la suite: `runningUnitTests()` corta antes que `inExceptArray()`. Un POST de test pasa igual **sin** la excepción registrada en `bootstrap/app.php`, así que ese test da un falso verde. Lo que sí se pone rojo al borrar la configuración es afirmar sobre `app(ValidateCsrfToken::class)->getExcludedPaths()`. Se descubrió mutando la implementación, no leyendo el test.

## El secreto ausente rechaza todo, y es lo correcto
`config('services.mercadopago.webhook_secret')` vacío → 401 a todas las notificaciones, auditado. Preferible a procesar sin verificar. La credencial es **distinta del access token**, se genera aparte en el panel de MercadoPago, y está neutralizada en `phpunit.xml` como el resto: los tests fijan el secreto con `config([...])` y nunca dependen de que el ambiente esté sin configurar.

## Las auditorías del webhook no son incidentes de pago
`webhook.signature_invalid`, `webhook.ignored`, `webhook.order_not_found` y `webhook.payment_not_found` registran qué pasó, pero **no destacan ningún pedido** y tres de ellas ni siquiera tienen pedido asociado. Los incidentes que la regla 161 destaca en el panel siguen siendo `order.payment_amount_mismatch` y `order.paid_after_cancel`.

## Un 503 en un test del webhook puede ser un doble que falta
Si un test no bindea `PaymentStatusQuery`, el gateway real llega al cerrojo de red (`Tests\RedProhibida`), que lanza; el `catch (Throwable)` del controlador lo convierte en **503**, indistinguible del 503 deliberado de la regla 153. El mensaje que dice qué doble falta queda en el log, no en el assert. Ante un 503 inesperado, revisá primero si falta `bindearConsulta(...)`.
