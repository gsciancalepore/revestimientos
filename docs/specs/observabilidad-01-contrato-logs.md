# Spec — Observabilidad 01: contrato de logs v1

- **Estado**: **borrador v2 (2026-09-24)**, pendiente de una segunda revisión de `revisor-spec` y de
  la aprobación del dueño. La v1 del borrador incluía al agente investigador dentro de este repo.
  El dueño lo sacó a un proyecto propio (§Por qué un contrato) y `revisor-spec` la marcó "necesita
  otra vuelta" (dos bloqueantes, B1 y B2). Esta versión aplica esos hallazgos.
- **Fuentes**: decisiones del dueño del 2026-09-24:
  - Revestimientos es el **sistema observado** y el investigador de incidentes es un **sistema
    externo** que consume su observabilidad por interfaces bien definidas.
  - Se empieza **solo con logs**, **en local**, con **planes gratuitos** y **sin datos personales**
    (el código postal incluido).
  - Un evento entra al contrato **solo si tapa un agujero que hoy impide investigar un incidente**.

  Además: [ADR-013](../adr/ADR-013-capa-observabilidad.md), que fija la capa ideal y las etapas
  (esta spec es la etapa 1); [ADR-004](../adr/ADR-004-observabilidad-estructura-reservada.md); y el
  relevamiento del código y de `storage/logs/laravel.log` del 2026-09-24 (§Contexto).

## Por qué esta spec no usa la numeración global de reglas

La numeración global está reservada a reglas de **negocio**. Esta spec no cambia lo que un actor
puede hacer ni lo que pasa con el dinero, el stock o los pedidos: cambia **qué deja escrito el
sistema** mientras hace lo que ya hacía. Sigue el precedente de `calidad-*.md` e
`identidad-visual-publica.md`, con reglas numeradas aparte con el prefijo `OBS-xx`.

## Por qué un contrato

El investigador de incidentes vive en otro repositorio (`incident-investigator`), en otro lenguaje
(Python) y no conoce Laravel. Lo único que comparten los dos sistemas es **el formato y el
significado de cada línea de log**. Ese acuerdo se escribe como contrato versionado:

- Un **JSON Schema** en `docs/observabilidad/log-schema.v1.json` define la forma de la línea.
- Un **catálogo de eventos** (§OBS-05) define qué significa cada uno.
- Un **canal de entrega**: en la v1, archivos `.jsonl` en un directorio.

Revestimientos garantiza el contrato con tests que validan contra ese schema. El investigador lo
consume sin mirar el código de Revestimientos. Un cambio incompatible exige `v2` y aviso: no se
cambia en silencio.

## Objetivo

Que cualquier incidente reproducido en el entorno local se pueda reconstruir **solo leyendo los
logs**: qué requests hubo, qué hizo el sistema en cada uno, en qué orden, dónde falló y a qué
pedido y pago correspondía, sin que ninguna línea contenga un dato personal de un cliente.

## Alcance

**Entra**:
- el contrato v1 (schema, catálogo de eventos y canal de entrega);
- el formato y el destino de los logs en local;
- el aislamiento de los tests;
- el `request_id` y el resumen por request;
- los eventos del catálogo;
- la redacción de datos personales en todos los canales;
- las excepciones;
- la tolerancia a fallos de escritura.

**No entra**:
- el investigador en sí, que vive en su propio repositorio;
- los logs de staging como fuente para el investigador;
- el acceso a la base de datos;
- errores agrupados, uptime, métricas y trazas (etapas 2 a 5 de ADR-013).

## Contexto — qué hay hoy exactamente (2026-09-24)

- **Configuración**: `config/logging.php` está como en el skeleton de Laravel (`stack` → `single`),
  con texto plano en `storage/logs/laravel.log`. Staging usa la misma configuración por defecto.
- **En local la app corre con PHP-FPM y nginx** (`docker-compose.yml`). Octane con RoadRunner corre
  **solo en staging** (`docker/koyeb/Dockerfile`), y `main` despliega a staging al mergear.
- **Llamadas a `Log::` en `app/`**: hay tres, todas `Log::error` por fallos de MercadoPago:
  - `CheckoutController:64` y `:93` (`mp preference failed`, con `order_id`). Esta llamada la
    prescribe literalmente la **regla 124** (Spec 07.4).
  - `MercadoPagoWebhookController:74` (`mp webhook failed`, con `payment_id`).
- **Tests**: `phpunit.xml` no fija `LOG_CHANNEL`, así que la suite escribe en el mismo archivo que
  el uso local. De 4141 líneas, 525 entradas son `testing.*`.
- **El log actual ya tiene datos personales** (hallazgo B1 de `revisor-spec`):
  - Una `QueryException` guardó el SQL con los valores de `customer_name`, `customer_email`,
    `customer_phone`, `shipping_cp` y `shipping_address`.
  - Las trazas en texto guardan los argumentos de las llamadas
    (`PlaceOrderAction->execute('Larga', 'larga@test.com', …)`), porque `zend.exception_ignore_args`
    está en `0` y `docker/php/php.ini` no lo fija.
  - Son datos de tests, pero el mecanismo de filtración es real.
- **`AuditRecorder::record()`** ya registra, con nombre estable, casi todos los hechos de negocio:
  - pedidos: `order.created`, `order.paid`, `order.paid_after_cancel`, `order.status_changed`,
    `order.stock_negative`, `order.stock_restored`, `order.payment_amount_mismatch`;
  - webhook: `webhook.ignored`, `webhook.signature_invalid`, `webhook.payment_not_found`,
    `webhook.order_not_found`;
  - `product.*` y `user.*`.

  Esos registros solo quedan en la base. Ningún payload actual lleva datos personales: `user.created`
  guarda el rol y `user.updated` solo los nombres de los campos.
- **Agujeros que impiden investigar**:
  - **Checkout rechazado**: el carrito vacío o no comprable, la validación y las `DomainException`
    de `PlaceOrderAction` devuelven un 302 y no dejan ningún evento.
  - **Pago no aprobado**: `ProcessMercadoPagoNotificationAction` sale en silencio cuando el estado
    no es `approved`, antes de buscar el pedido.
  - **Pago sin pedido asociado**: `order.paid` no lleva `payment_id` y el fallo del webhook no lleva
    `order_id`.
  - **Preferencia de MercadoPago generada con éxito**: no deja rastro. No se distingue "abandonó en
    MercadoPago" de "el webhook nunca llegó".
- **`request_id` de MercadoPago**: el webhook trae una cabecera `x-request-id` propia, y la
  auditoría de `webhook.signature_invalid` la guarda con la clave `request_id`
  (`MercadoPagoWebhookController:51-54`).
- **Contexto y zona horaria**:
  - El `Context` de Laravel 12 inyecta sus datos en `extra`, no en la raíz de la entrada.
  - `Illuminate\Log\Context\Repository` se registra como `scoped`.
  - `config/app.php` usa UTC.

## Reglas

**OBS-01 — Forma de la línea (contrato v1).** Cada entrada es **un objeto JSON en una sola línea**
con exactamente estos campos de primer nivel, y ninguno más:

| Campo | Tipo | Contenido |
|---|---|---|
| `schema_version` | string | `"1"` |
| `timestamp` | string | ISO 8601 en **UTC** con milisegundos y `Z` |
| `level` | string | `debug`, `info`, `warning`, `error` o `critical` |
| `event` | string | nombre del catálogo (OBS-05), en minúsculas con puntos |
| `service` | string | `"revestimientos"` |
| `environment` | string | valor de `APP_ENV` |
| `request_id` | string o `null` | OBS-03; `null` fuera de un request HTTP |
| `attributes` | objeto | datos propios del evento: solo identificadores, montos en centavos, estados y códigos |
| `error` | objeto o `null` | OBS-07 |

El JSON Schema de `docs/observabilidad/log-schema.v1.json` es la fuente de verdad de esta tabla.
Cada `event` del catálogo declara en el schema:
- qué `attributes` lleva;
- una **descripción** de qué significa, que incluye cuándo el hecho es un **comportamiento previsto
  y no una falla**. Por ejemplo, `order.stock_negative` es aceptado por la regla 145 (abastecimiento
  directo del fabricante).

El consumidor usa esas descripciones como conocimiento del dominio.

Los valores que llegan **de afuera** del sistema (`mp_request_id`, `external_reference`, el
`status` de MercadoPago) se escriben truncados a 64 caracteres y el schema los declara con ese
`maxLength`. Son datos no confiables: cualquiera puede mandar una cabecera, y su contenido termina
en el prompt de un modelo de lenguaje del lado consumidor.

**OBS-02 — Canal de entrega v1.** En local, las entradas van a archivos diarios
`storage/logs/app-AAAA-MM-DD.jsonl`:
- La fecha del archivo es en UTC.
- La retención es de 14 días (`LOG_DAILY_DAYS`).
- El investigador recibe la ruta del directorio y lee `app-*.jsonl`; nada más.

**OBS-03 — Correlación.**
- Cada request HTTP recibe al entrar un `request_id` nuevo (UUID v4).
- Ese `request_id` aparece en todas las entradas de ese request y se devuelve en la cabecera de
  respuesta `X-Request-Id`.
- **No se adopta** un `X-Request-Id` entrante (§Casos borde).
- Dos requests atendidos por el mismo proceso nunca comparten `request_id` ni contexto.

**OBS-04 — Resumen por request.** Al terminar cada request HTTP se escribe `http.request`, con
estos `attributes`:
- `method`;
- `route` (el nombre de la ruta, o `null`);
- `status`;
- `duration_ms`;
- `user_id`, solo si hay un usuario interno autenticado.

Nunca incluye la URL con su query string, el cuerpo, las cookies ni las cabeceras.

**OBS-05 — Catálogo de eventos v1.** Un evento entra al catálogo **solo si tapa un agujero que hoy
impide investigar un incidente concreto** (criterio del dueño). No se agregan eventos "por si
acaso". El catálogo v1:

1. **Espejo de la auditoría**: todo lo que registra `AuditRecorder`, con el mismo nombre como
   `event`.
   - `attributes` lleva `subject_type`, `subject_id` y el payload de auditoría, pasado por OBS-06.
   - **Nivel `warning`**: `order.payment_amount_mismatch`, `order.paid_after_cancel`,
     `order.stock_negative` y `webhook.signature_invalid`. **Nivel `info`**: el resto.
   - La entrada se escribe **solo si el cambio quedó confirmado**: si la transacción se revierte,
     no se escribe, igual que la fila de auditoría.
   - La **fila de auditoría no cambia**. En el log, la clave `request_id` del payload de
     `webhook.signature_invalid` se renombra a `mp_request_id` para no confundirla con la propia.
2. **`checkout.rejected`**: el cliente intentó avanzar en el checkout y no pudo.
   - `attributes.motivo` es un código fijo: `carrito_vacio`, `no_comprable`, `validacion` o
     `dominio`.
   - Con `validacion` se agregan `campos` (los nombres de los campos que fallaron, nunca sus
     valores).
   - Con `dominio` se agrega `detalle`: el mensaje de la `DomainException` de `PlaceOrderAction`.
     Esos mensajes son textos fijos sin datos del cliente; un test lo verifica.
   - Nivel `info`.
3. **`checkout.payment_started`**: el pedido quedó creado y el cliente fue derivado a pagar.
   - `attributes`: `order_id`, `payment_method`, `total_cents`.
   - En MercadoPago se escribe al generarse la preferencia, antes de redirigir. También al
     reintentar (`checkout.mercadopago.retry`).
   - En transferencia se escribe una sola vez, en `store`, al derivar al cliente a la página de
     instrucciones. Volver a abrir esa página no lo repite.
   - Nivel `info`.
4. **`checkout.mp_preference_failed`**: no se pudo generar la preferencia.
   - `attributes`: `order_id`, con el error según OBS-07.
   - Nivel `error`.
   - **Enmienda la regla 124** (Spec 07.4), que prescribía `Log::error('mp preference failed')`: el
     hecho que registra es el mismo, cambia el nombre y la forma.
5. **`webhook.payment_not_approved`**: MercadoPago informó un pago que no está `approved`.
   - `attributes`: `payment_id`, `status` y `order_id`.
   - `order_id` sale de `external_reference` si es numérico; si no, es `null`. No se verifica que
     el pedido exista (§Casos borde).
   - Nivel `info`.
6. **`webhook.processing_failed`**: la consulta del pago falló y el webhook respondió 503.
   - `attributes`: `payment_id`, con el error según OBS-07.
   - Nivel `error`.
   - Reemplaza al `mp webhook failed` actual.
7. **Pago ↔ pedido**: `order.paid` y `order.payment_amount_mismatch` llevan en `attributes` el
   `payment_id` y el `order_id` (`subject_id`). Cuando el origen es la confirmación manual de una
   transferencia, `payment_id` es `null`.

**OBS-06 — Sin datos personales, en todos los canales.**
- **Qué no puede aparecer**: ninguna entrada, en **ningún canal** (el `app` de local y los de
  staging), contiene nombre, email, teléfono, dirección, código postal, IP ni user agent de un
  cliente, ni contraseñas, tokens, firmas, cookies o credenciales.
- **Red de seguridad**: un procesador de Monolog, registrado en todos los canales, reemplaza por
  `"[redactado]"` el valor de las claves de esta lista, a cualquier profundidad de `attributes` y
  `error`.
  - La comparación es **exacta y sin distinguir mayúsculas**, nunca por subcadena, para no redactar
    `product_name` ni el `codigo_postal` de una tarifa de envío.
  - La lista vive en una sola constante: `customer_name`, `customer_email`, `customer_phone`,
    `shipping_address`, `shipping_cp`, `email`, `phone`, `password`, `password_confirmation`,
    `token`, `access_token`, `authorization`, `x-signature`, `cookie`, `ip`, `ip_address`,
    `user_agent`.
- **Límite**: la red de seguridad no protege texto libre. Para eso está OBS-07.

**OBS-07 — Errores sin texto peligroso.**
- **Cuándo**: toda excepción que Laravel **reporta** se escribe como `app.exception`, nivel
  `error`, con su `request_id`. Se respeta la lista de excepciones que Laravel no reporta (404, 403,
  419, validación, autenticación), porque `http.request` ya registra su estado.
- **Forma del objeto `error`**, en este evento y en los de OBS-05.4 y OBS-05.6:
  - `class`;
  - `message`;
  - `sqlstate`;
  - `trace`: lista de `"archivo:línea"`, **sin argumentos**.
- **Excepciones de base de datos** (`QueryException`, `PDOException` y sus subclases): `message`
  es `null` y solo queda `sqlstate`, porque su mensaje trae el SQL con los valores.
- **Defensa en profundidad**: `docker/php/php.ini` fija `zend.exception_ignore_args = On`, así que
  los argumentos no llegan a la traza ni aunque otro formateador la escriba.
- **Riesgo residual declarado**: el `message` de otras excepciones es texto libre. Si una librería
  mete un dato personal ahí, esta spec no lo detecta.

**OBS-08 — El log nunca rompe el negocio.** Si escribir una entrada falla (permisos, disco lleno,
directorio inexistente), el request sigue exactamente igual: misma respuesta, mismo efecto. La
falla no se propaga.

**OBS-09 — Los tests no escriben en `storage/logs/`.**
- La suite completa no agrega ni modifica ningún archivo en `storage/logs/`.
- Los tests del contrato configuran el canal `app` con una ruta temporal y **validan cada línea
  contra `log-schema.v1.json`**.

## Matriz de permisos

| Actor | Ve `X-Request-Id` | Lee `app-*.jsonl` | Nota |
|---|---|---|---|
| Cliente anónimo de la tienda | sí (cabecera de respuesta) | no | Un `request_id` no revela nada |
| Admin, vendedor, depósito | sí | no | Sin cambios en el panel |
| Dueño (único contribuidor), en su máquina | sí | sí | Acceso al sistema de archivos local |
| `incident-investigator` (sistema externo) | — | sí, solo el directorio que se le pasa | No toca la base, el código en ejecución ni la red de Revestimientos |

## Casos borde

- **`X-Request-Id` entrante**: MercadoPago lo manda y cualquiera podría mandar uno falso. Nunca se
  adopta como `request_id` propio. El de MercadoPago queda solo como `mp_request_id` (OBS-05.1).
- **Mismo proceso, dos requests**: en local (FPM) cada request es un proceso limpio. En staging
  (Octane) el `Context` es `scoped` y se vacía entre requests. El test de OBS-03 atiende dos
  requests en el mismo proceso de test. La verificación bajo Octane real queda para la etapa 2.
- **Transacción revertida**: si `PlaceOrderAction` falla bajo lock, no hay `order.created`. Sí hay
  `checkout.rejected` con motivo `dominio` y el `http.request` del 302.
- **Auditoría fuera de transacción** (por ejemplo `webhook.ignored`): se escribe en el momento.
- **`external_reference` no numérico en un pago no aprobado**: `order_id` es `null`. Sigue valiendo
  el evento, porque el `payment_id` alcanza para buscarlo en MercadoPago.
- **Pago aprobado y después rechazado, o reintentos del webhook**: cada notificación deja su propio
  evento, y el investigador reconstruye la secuencia por `payment_id`.
- **Día en UTC**: el archivo rota a las 21:00 de Argentina. Una pregunta por "anoche" puede abarcar
  dos archivos.
- **`/up`**: también genera `http.request`. En local el volumen es chico, y filtrarlo sería una
  excepción sin necesidad medida (principio 7).
- **El log anterior**: `storage/logs/laravel.log` tiene datos personales (B1). **Se borra** como
  parte de la entrega. Nunca fue parte del contrato y nadie lo lee.
- **`.env` existente**: `make setup` copia `.env.example` solo si no hay `.env` (`cp -n`). Quien ya
  tiene un `.env` tiene que poner `LOG_CHANNEL=app` a mano, y el runbook lo dice.

## Criterios de aceptación

1. Un request de prueba, con el canal `app` apuntando a una ruta temporal, produce líneas que
   **validan contra `log-schema.v1.json`**. Todas llevan el mismo `request_id`, que coincide con la
   cabecera `X-Request-Id` (OBS-01, OBS-03).
2. Correr la suite completa no crea ni modifica ningún archivo en `storage/logs/` (OBS-09).
3. Un request con `X-Request-Id: falso` responde con otro valor. Dos requests seguidos en el mismo
   proceso de test tienen `request_id` distintos y el segundo no hereda contexto del primero
   (OBS-03).
4. `http.request` de un `GET /catalogo?categoria=x` tiene `method`, `route`, `status` y
   `duration_ms`, y no contiene `categoria=x` (OBS-04).
5. Crear un pedido deja exactamente una entrada `order.created` con `subject_id` igual al pedido. Un
   pedido que falla bajo lock no deja ninguna y deja `checkout.rejected` con motivo `dominio`
   (OBS-05.1, OBS-05.2).
6. Cada motivo de `checkout.rejected` tiene su test. El de `validacion` lista los campos y no los
   valores (OBS-05.2).
7. Un checkout por MercadoPago exitoso deja `checkout.payment_started`. Uno con la preferencia
   fallida deja `checkout.mp_preference_failed` y no deja `checkout.payment_started` (OBS-05.3,
   OBS-05.4).
8. Un webhook con un pago `rejected` deja `webhook.payment_not_approved` con `payment_id`, `status`
   y `order_id`. Uno con la consulta fallida deja `webhook.processing_failed` y el endpoint responde
   503. `order.paid` por webhook lleva `payment_id` y `order_id` (OBS-05.5 a OBS-05.7).
9. `webhook.signature_invalid` lleva `mp_request_id` en el log, y la fila de auditoría conserva su
   clave `request_id` sin cambios (OBS-05.1).
10. Un `Log::info('x', ['customer_email' => 'a@b.c', 'datos' => ['phone' => '11'], 'product_name' => 'P'])`
    termina con los dos primeros valores en `"[redactado]"` y `product_name` intacto, tanto en el
    canal `app` como en uno de staging (OBS-06).
11. Un checkout completo con nombre, email, teléfono, CP y dirección de prueba no deja ninguno de
    esos valores en el log. Una `QueryException` forzada en `PlaceOrderAction` con esos mismos
    valores deja `app.exception` con `sqlstate`, `message: null` y una traza sin ningún argumento
    (OBS-06, OBS-07).
12. Con el directorio de logs sin permisos de escritura, un checkout responde igual que con el log
    sano y crea el pedido una sola vez (OBS-08).
13. Pint, PHPStan nivel 8 y la suite en verde. Cada regla verificada mutando la implementación.

## Tareas técnicas

1. **Schema**: `docs/observabilidad/log-schema.v1.json` y `docs/observabilidad/README.md`, con el
   catálogo legible, cómo se versiona y cómo lo consume un sistema externo.
2. **Canales**:
   - Canal `app` en `config/logging.php`: daily, con un formateador propio que produce la forma de
     OBS-01 (mueve el `request_id` desde `extra` y arma `attributes` y `error`), y con
     `ignore_exceptions` o un equivalente que cumpla OBS-08.
   - `LOG_CHANNEL=app` en `.env.example` y `LOG_CHANNEL=null` en `phpunit.xml`.
   - Procesador de redacción (OBS-06) en **todos** los canales.
3. **Middleware global**: genera el `request_id`, lo pone en `Context`, lo devuelve en
   `X-Request-Id` y escribe `http.request` en `terminate`.
4. **`AuditRecorder`**: emite el espejo de OBS-05.1 después del commit (`DB::afterCommit` o
   equivalente, a verificar con `RefreshDatabase`), con el mapa de niveles y el renombre de
   `mp_request_id`.
5. **Eventos nuevos y reemplazos**:
   - `checkout.rejected`, `checkout.payment_started`, `checkout.mp_preference_failed`,
     `webhook.payment_not_approved` y `webhook.processing_failed`.
   - `payment_id` en `order.paid` y en `order.payment_amount_mismatch`.
   - Quitar los tres `Log::error` actuales.
6. **Excepciones**: `app.exception` vía `withExceptions` en `bootstrap/app.php`, y
   `zend.exception_ignore_args = On` en `docker/php/php.ini`.
7. **Validación del schema en tests**: agregar una dependencia de desarrollo que valide JSON Schema
   (§Decisiones abiertas).
8. **Borrar el log viejo**: `storage/logs/laravel.log` local.
9. **Sincronías**:
   - Spec 07.4 (enmienda de la regla 124) y Spec 08 (`webhook.payment_not_approved` y `payment_id`
     en los eventos de pago, sin cambiar sus reglas).
   - ADR-004 anota que ADR-013 la enmienda.
   - `arquitectura.md`.
   - Glosario: "evento de log" y `request_id` como términos técnicos. "Incidente operativo" se
     distingue del **Incidente de pago** de negocio.
   - `.ai/rules/`, con una regla durable: todo evento nuevo pasa por el criterio de OBS-05, se
     declara en el schema y no lleva datos personales.
   - Roadmap y runbook del README (dónde están los logs y `LOG_CHANNEL=app` en un `.env` que ya
     existe).

## Decisiones abiertas para el dueño

- **Dependencia de desarrollo para validar el schema** (tarea 7), por ejemplo `opis/json-schema`.
  Sin ella, los tests verifican la forma a mano y el schema queda como documentación, que es
  justamente lo que el contrato busca evitar. Se propone agregarla, solo en `require-dev`.

## Notas

- La regla de no loguear dos veces el mismo hecho (borrador v1) quedó como nota: hoy no hay ningún
  caso concreto que testear. La cubre OBS-05, que reemplaza los `Log::error` en lugar de sumar
  eventos al lado.
