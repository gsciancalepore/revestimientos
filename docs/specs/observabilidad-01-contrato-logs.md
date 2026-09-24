# Spec — Observabilidad 01: contrato de logs v1

- **Estado**: **implementada (2026-09-24)**, rama `feat/observabilidad-01-contrato-logs`, pendiente de `revisor-entrega` y PR. Fue **aprobada (2026-09-24)** por el dueño, tras tres pasadas de `revisor-spec`. La tercera fue "aprobable con correcciones menores" y las correcciones ya están aplicadas.
- **Historia del borrador**:
  - **v1**: incluía un agente investigador dentro de este repo. `revisor-spec` la marcó "necesita
    otra vuelta".
  - **v2**: el dueño sacó el agente a un proyecto propio (§Por qué un contrato). `revisor-spec`
    volvió a marcarla "necesita otra vuelta, corta", con el bloqueante del vínculo pago↔pedido y los
    hallazgos I1–I10 y M1–M10.
  - **v3**: aplica esos hallazgos y las decisiones del dueño del mismo día. La tercera pasada la
    dio aprobable con correcciones menores (I1 firma de `ConfirmPaymentAction`, I2 segundo pago
    como límite declarado, M1–M6), que se aplicaron antes de aprobarla.
- **Fuentes**:
  - Decisiones del dueño del 2026-09-24:
    1. Revestimientos es el **sistema observado**, y el investigador de incidentes es un **sistema
       externo** que consume su observabilidad por interfaces bien definidas.
    2. Se empieza **solo con logs**, **en local** y con **planes gratuitos**.
    3. **Sin datos personales**, el código postal incluido.
    4. Un evento entra al contrato **solo si tapa un agujero que hoy impide investigar un
       incidente**.
    5. El `payment_id` se guarda **también en `audit_logs`** (enmienda las reglas 150 y 151).
    6. Las líneas ajenas al catálogo se conservan como **`app.log`**.
    7. El mensaje de una excepción se conserva **solo si es propia**.
  - [ADR-013](../adr/ADR-013-capa-observabilidad.md), que fija la capa ideal y las etapas. Esta spec
    es la etapa 1.
  - [ADR-004](../adr/ADR-004-observabilidad-estructura-reservada.md).
  - El relevamiento del código y de `storage/logs/laravel.log` del 2026-09-24 (§Contexto).

## Por qué esta spec no usa la numeración global de reglas

La numeración global está reservada a reglas de **negocio**. Esta spec cambia **qué deja escrito
el sistema** mientras hace lo que ya hacía. Hay una sola excepción, que se declara aparte como
enmienda: el `payment_id` que pasa a recibir `ConfirmPaymentAction` y que se guarda en la
auditoría (reglas 150 y 151, §Enmiendas). Por lo demás, sigue el precedente de `calidad-*.md` e
`identidad-visual-publica.md`, con reglas numeradas aparte con el prefijo `OBS-xx`.

## Por qué un contrato

El investigador de incidentes vive en otro repositorio (`incident-investigator`), está escrito en
Python y no conoce Laravel. Lo único que comparten los dos sistemas es **el formato y el
significado de cada línea de log**, y ese acuerdo se escribe como contrato versionado:

- **Forma**: un JSON Schema en `docs/observabilidad/log-schema.v1.json`. Es la **única fuente de
  verdad**, y el catálogo de eventos vive en sus descripciones.
- **Documentación**: `docs/observabilidad/README.md` deriva del schema y explica cómo consumirlo.
- **Entrega**: en la v1, archivos `.jsonl` en un directorio.

Una vez cerrada esta spec, OBS-05 queda como foto del catálogo al publicarse la v1. Los eventos que
se agreguen después viven en el schema.

**Compatibilidad**:
- Agregar un evento, o un atributo opcional a un evento existente, es **compatible**. El consumidor
  tiene que tolerar eventos y atributos que no conoce.
- Quitar o renombrar un evento o un atributo, o cambiar su tipo o su significado, **exige `v2`**.

## Objetivo

Que cualquier incidente reproducido en el entorno local se pueda reconstruir **solo leyendo los
logs**: qué requests hubo, qué hizo el sistema en cada uno, en qué orden, dónde falló y a qué
pedido y pago correspondía. Todo sin que ninguna línea, en ningún canal, contenga un dato personal
de un cliente.

## Alcance

**Entra**:
- el contrato v1;
- el formato y el destino de los logs en local;
- el aislamiento de los tests;
- el `request_id` y el resumen por request;
- los eventos del catálogo;
- la redacción de datos personales en todos los canales;
- las excepciones;
- la tolerancia a fallos de escritura;
- la enmienda de las reglas 124, 150 y 151.

**No entra**:
- el investigador;
- los logs de staging como fuente del investigador;
- el acceso a la base;
- errores agrupados, uptime, métricas y trazas (etapas 2 a 5 de ADR-013).

## Contexto — qué hay hoy exactamente (2026-09-24)

- **Configuración**: `config/logging.php` está como viene en el skeleton (`stack` → `single`), y
  escribe texto plano en `storage/logs/laravel.log`. Qué `LOG_CHANNEL` usa Render **no está
  documentado**: `docs/deployment/staging.md` §10 lo deja como punto a revisar. Esta spec supone el
  valor por defecto y cubre cualquier canal (OBS-06).
- **Entornos**: en local, PHP-FPM con nginx (`docker-compose.yml`). Octane con RoadRunner corre
  **solo en staging** (`docker/koyeb/Dockerfile`), y `main` despliega a staging al mergear. CI usa
  `setup-php` (`.github/workflows/ci.yml`) y no carga `docker/php/php.ini`.
- **Tres `Log::error`, todos por fallos de MercadoPago**:
  - `CheckoutController:64`, en `store`. Lo prescribe literalmente la **regla 124** (Spec 07.4).
  - `CheckoutController:93`, en el reintento de la regla 126, que no prescribe ningún log.
  - `MercadoPagoWebhookController:74`. Ese `catch (Throwable)` envuelve **todo**
    `ProcessMercadoPagoNotificationAction::execute`, incluida la confirmación. Además de un fallo
    de la consulta del pago, atrapa deadlocks y excepciones de `ConfirmPaymentAction`.
- **Tests**: `phpunit.xml` no fija `LOG_CHANNEL`. De las 4141 líneas del log, 525 son entradas
  `testing.*`.
- **El log actual tiene datos personales**, por tres vías:
  - `QueryException` con el SQL y sus valores;
  - trazas con los argumentos de las llamadas (`zend.exception_ignore_args=0`);
  - el reporte por defecto de excepciones de Laravel (`Handler::reportThrowable`), que escribe
    `getMessage()` en el canal por defecto.

  Son datos de tests, pero el mecanismo es real.
- **`AuditRecorder::record()`** ya registra, con nombre estable, casi todos los hechos de negocio:
  `order.*`, `webhook.*`, `product.*` y `user.*`.
  - Ningún payload actual lleva datos personales.
  - `order.payment_amount_mismatch` **ya** lleva `payment_id`.
  - `order.paid` y `order.paid_after_cancel` **no** lo llevan, porque
    `ConfirmPaymentAction::execute(Order $order, string $origen)` no lo recibe (regla 150).
  - Los eventos de auditoría identifican el pedido con `subject_type`/`subject_id`, no con
    `order_id`.
- **Agujeros que impiden investigar**, uno por fila:

  | Agujero | Qué pasa hoy | Qué queda sin responder |
  |---|---|---|
  | Checkout rechazado | Los rechazos de `show` y `store` devuelven un 302 sin evento | "No pude comprar" |
  | Pago no aprobado | Sale en silencio (`ProcessMercadoPagoNotificationAction:37-39`) | Por qué un pedido sigue pendiente |
  | Pago sin su pedido | Falta `payment_id` en los eventos de confirmación | El incidente de pago de la regla 151 (plata cobrada sobre un pedido cancelado) no tiene el dato que hace falta para devolverla |
  | Derivación a pagar | No deja rastro | "Llegó a MercadoPago" contra "se cayó antes de llegar" |

- **Límite declarado de la v1**: "abandonó en MercadoPago" y "el webhook nunca llegó" **se siguen
  viendo igual**: el pedido quedó derivado y después no pasó nada. Distinguirlos exigiría la señal
  de retorno (`back_urls`, que viaja en el query string y en local no existe porque `auto_return`
  necesita una URL pública) o consultar a MercadoPago. Queda fuera de esta etapa.
- **`request_id` de MercadoPago**: el webhook trae una cabecera `x-request-id` propia, que la
  auditoría de `webhook.signature_invalid` guarda con la clave `request_id`.
- **Valores externos sin firma**: `webhook.ignored` guarda `tipo` y `payment_id` sacados de un GET
  sin firma. Cualquiera puede escribir texto arbitrario en el log con
  `GET /webhook/mercadopago?type=…&id=…`.
- **Laravel**:
  - El `Context` inyecta sus datos en `extra`.
  - `ignore_exceptions` solo lo lee el driver `stack` (`LogManager.php:290`).
  - Si un canal no se puede construir, Laravel cae a un logger de emergencia que escribe texto
    plano en `storage/logs/laravel.log`.
  - `config/app.php` usa UTC.

## Reglas

**OBS-01 — Forma de la línea.** Cada entrada es **un objeto JSON en una sola línea**, con
exactamente estos campos de primer nivel:

| Campo | Tipo | Contenido |
|---|---|---|
| `schema_version` | string | `"1"` |
| `timestamp` | string | ISO 8601 en **UTC** con milisegundos y `Z` |
| `level` | string | `debug`, `info`, `warning`, `error` o `critical`. Los niveles `notice`, `alert` y `emergency` de Monolog se escriben como `info`, `critical` y `critical` |
| `event` | string | patrón `^[a-z]+(\.[a-z_]+)+$` |
| `service` | string | `"revestimientos"` |
| `environment` | string | `APP_ENV` |
| `request_id` | string o `null` | OBS-03; `null` fuera de un request HTTP |
| `attributes` | objeto | datos propios del evento, declarados en el schema |
| `error` | objeto o `null` | OBS-07 |

**Qué puede ir en `attributes`**:
- identificadores;
- montos en centavos;
- estados y códigos;
- duraciones;
- nombres de campos;
- las líneas de un pedido (`product_id` y `cantidad`);
- textos **fijos** que escribe la propia app.

**Identificadores transversales**: toda entrada que se refiere a un pedido lleva
`attributes.order_id` (entero), y toda entrada que se refiere a un pago de MercadoPago lleva
`attributes.payment_id` (string), **siempre que el dato se conozca en el punto de emisión**. Cuando
no se conoce, la clave va en `null` o se omite, según declare el schema para ese evento. Por
ejemplo, `webhook.processing_failed` no lleva `order_id` (OBS-05.6). Van además de `subject_type` y `subject_id` cuando la entrada es
un espejo de la auditoría. El consumidor filtra siempre por esas dos claves.

**Valores externos**: todo valor que llega del request o de MercadoPago se trunca a **64
caracteres** y el schema lo declara con ese `maxLength`. Son datos no confiables, porque terminan
en el prompt de un modelo de lenguaje del lado consumidor. Hoy son `payment_id`, `tipo`,
`mp_request_id`, `external_reference` y `status`.

**Descripciones**: cada evento del schema declara qué significa, incluido cuándo es un
**comportamiento previsto y no una falla**. Por ejemplo, `order.stock_negative` está aceptado por la
regla 145 (abastecimiento directo del fabricante). El consumidor usa esas descripciones como
conocimiento del dominio.

**OBS-02 — Canal de entrega v1.** En local:
- Las entradas van a `storage/logs/app-AAAA-MM-DD.jsonl`, con la fecha en UTC.
- La retención es de 14 días (`LOG_DAILY_DAYS`).
- El consumidor recibe la ruta del directorio y lee `app-*.jsonl`.
- El README del contrato aclara que **la ausencia de un evento no prueba que el hecho no haya
  ocurrido** (OBS-08 permite perder líneas).

**OBS-03 — Correlación.**
- Cada request HTTP recibe al entrar un `request_id` nuevo (UUID v4).
- Ese `request_id` aparece en todas las entradas de ese request y se devuelve en la cabecera
  `X-Request-Id`.
- Nunca se adopta un `X-Request-Id` entrante.
- Dos requests del mismo proceso nunca comparten `request_id` ni contexto.

**OBS-04 — Resumen por request.** Al terminar cada request HTTP se escribe `http.request`, con estos
`attributes`:
- `method`;
- `route` (el nombre de la ruta, o `null`);
- `status`;
- `duration_ms`;
- `user_id`, solo si hay un usuario interno autenticado.

Nunca incluye la URL con su query string, el cuerpo, las cookies ni las cabeceras.

**OBS-05 — Catálogo de eventos v1.** Un evento entra solo si tapa un agujero que hoy impide
investigar un incidente concreto.

1. **Espejo de la auditoría**: todo lo que registra `AuditRecorder`, con el mismo nombre como
   `event`.
   - `attributes` lleva `subject_type`, `subject_id`, los identificadores transversales de OBS-01 y
     el payload de auditoría, pasado por OBS-06.
   - **Nivel `warning`**: `order.payment_amount_mismatch`, `order.paid_after_cancel`,
     `order.stock_negative` y `webhook.signature_invalid`. **Nivel `info`**: el resto.
   - La entrada se escribe **solo si el cambio quedó confirmado**.
   - La fila de auditoría conserva sus claves. Solo en el log, el `request_id` del payload de
     `webhook.signature_invalid` se renombra a `mp_request_id`.
2. **`checkout.rejected`**: el cliente intentó avanzar hacia el pago y no pudo. Nivel `info`. Se
   emite en estos puntos, y en ningún otro:

   | Endpoint | `motivo` | Atributos extra |
   |---|---|---|
   | `GET /checkout` (`show`) | `carrito_vacio`, `no_comprable` | — |
   | `POST /checkout` (`store`) | `carrito_vacio` | — |
   | `POST /checkout`, rechazado por el Form Request | `validacion` | `campos`: los nombres de los campos que fallaron, nunca sus valores |
   | `POST /checkout`, `DomainException` de `PlaceOrderAction` | `dominio` | `detalle`: el texto fijo de la excepción |

   Un carrito que deja de ser comprable entre `show` y `store` sale como `no_comprable` en el
   primero y como `dominio` en el segundo. Es el mismo hecho visto en dos momentos, y se acepta así
   porque el `detalle` lo aclara.
3. **`checkout.payment_started`**: el pedido quedó creado y el cliente fue derivado a pagar.
   - `attributes`: `order_id`, `payment_method`, `total_cents`.
   - En MercadoPago se escribe al generarse la preferencia, en `store` y en el reintento de la regla
     126.
   - En transferencia se escribe una sola vez, en `store`. Volver a abrir `checkout.success` no lo
     repite.
   - Tapa "llegó a MercadoPago" contra "se cayó antes". **No** distingue el abandono de la pérdida
     del webhook (§Contexto).
   - Nivel `info`.
4. **`checkout.mp_preference_failed`**: no se pudo generar la preferencia, en `store` o en el
   reintento.
   - `attributes`: `order_id`, con `error` según OBS-07.
   - Nivel `error`.
   - Enmienda la regla 124 (§Enmiendas).
5. **`webhook.payment_not_approved`**: MercadoPago informó un pago cuyo estado no es `approved`.
   - `attributes`: `payment_id`, `status` y `order_id`.
   - `order_id` sale de `external_reference` si es numérico; si no, es `null`. No se verifica que
     el pedido exista.
   - Nivel `info`.
6. **`webhook.processing_failed`**: el procesamiento de una notificación falló y el webhook respondió
   503 para que MercadoPago reintente. Puede ser la consulta del pago, un deadlock o una excepción
   de la confirmación.
   - `attributes`: `payment_id`, con `error` según OBS-07.
   - El `order_id` no se conoce en ese punto y no se agrega: el `payment_id` alcanza para
     correlacionar con los demás eventos del mismo pago.
   - Nivel `error`.
   - Reemplaza al `mp webhook failed` actual.
7. **Pago ↔ pedido**: `order.paid` y `order.paid_after_cancel` llevan `payment_id` (§Enmiendas,
   reglas 150 y 151). Cuando el origen es la confirmación manual de una transferencia,
   `payment_id` es `null`.
8. **`app.log`**: toda línea que no es un evento del catálogo, sea del framework, de una librería o
   un `Log::` suelto.
   - `attributes.message`: el texto, truncado a 256 caracteres.
   - Pasa por OBS-06 y conserva su nivel.
   - Es **riesgo residual declarado**: su texto no es fijo. Un `Log::` nuevo en `app/` no debería
     caer acá: tiene que ser un evento del catálogo (regla durable, tarea 9).

**OBS-06 — Sin datos personales, en todos los canales.**
- **Qué no puede aparecer**: ninguna entrada, en **ningún canal** (el `app` de local y los de
  staging), contiene nombre, email, teléfono, dirección, código postal, IP ni user agent de un
  cliente, ni contraseñas, tokens, firmas, cookies o credenciales.
- **Red de seguridad**: un procesador de Monolog, registrado en todos los canales, trabaja **sobre
  el registro de Monolog** (`context` y `extra`) antes de que cualquier formateador lo escriba.
  - Reemplaza por `"[redactado]"` el valor de las claves de la lista, a cualquier profundidad.
  - La comparación es **exacta y sin distinguir mayúsculas**.
  - La lista vive en una sola constante: `customer_name`, `customer_email`, `customer_phone`,
    `shipping_address`, `shipping_cp`, `email`, `phone`, `password`, `password_confirmation`,
    `token`, `access_token`, `authorization`, `x-signature`, `cookie`, `ip`, `ip_address`,
    `user_agent`.
- **Límite**: la red de seguridad no protege texto libre. De eso se ocupan OBS-05.8 y OBS-07.

**OBS-07 — Errores sin texto peligroso.**
- **`app.exception`**: toda excepción que Laravel **reporta** se escribe como `app.exception`, nivel
  `error`, con su `request_id`. **Reemplaza** al reporte por defecto de Laravel en todos los
  canales: no queda otra línea con el mensaje crudo.
- **Se respeta la lista de lo que Laravel no reporta** (404, 403, 419, validación, autenticación).
  Su estado ya queda en `http.request`.
- **Forma del objeto `error`**:
  - `class`;
  - `code`;
  - `message`;
  - `sqlstate`;
  - `trace`: lista de `"archivo:línea"`, **sin argumentos**.
- **Qué pasa con `message`** (decisión del dueño):
  - Se conserva **solo si la excepción es propia**: una clase del namespace `App\` o una
    `DomainException` lanzada desde `app/`. No son todos textos fijos: algunos interpolan ids o
    estados (`ConfirmPaymentAction`, `CancelOrderAction`, `TransitionOrderStatusAction`). Lo que
    garantiza la regla es que **no llevan datos personales**, y la regla durable de la tarea 9 lo
    exige para todo mensaje nuevo.
  - Para toda otra excepción, `message` es `null`.
  - En las de base de datos (`QueryException`, `PDOException` y sus subclases), `message` también
    es `null`, pero se conserva `sqlstate`.
- **Defensa en profundidad**: `docker/php/php.ini` fija `zend.exception_ignore_args = On`.

**OBS-08 — El log nunca rompe el negocio.** Si escribir una entrada falla (permisos, disco lleno,
directorio inexistente), el request sigue exactamente igual y la falla no se propaga. El canal
`app` es un `stack` con `ignore_exceptions: true` sobre un `daily`.

**OBS-09 — Los tests no escriben en `storage/logs/`.** La suite completa no agrega ni modifica ningún
archivo en `storage/logs/`. Los tests del contrato configuran el canal `app` con una ruta temporal
y **validan cada línea contra `log-schema.v1.json`**.

## Enmiendas a specs cerradas

- **Regla 124 (Spec 07.4)**: `Log::error('mp preference failed', …)` pasa a ser el evento
  `checkout.mp_preference_failed` (OBS-05.4). El hecho registrado es el mismo, pero cambian el
  nombre y la forma, y **se pierde contenido**: la regla guardaba `$e->getMessage()`, y con OBS-07
  el mensaje de una excepción del SDK de MercadoPago pasa a `null`. Quedan la clase, el código y
  la traza. Es una decisión del dueño: ese texto no debe llegar a un modelo de terceros. Alcanza
  también al reintento de la regla 126.
- **Regla 150 (Spec 08)**: la firma pasa a ser
  `execute(Order $order, string $origen, ?string $paymentId = null)`. La acción lo valida junto con
  el origen, antes de abrir la transacción:
  - con origen `mercadopago`, un `payment_id` nulo o vacío (tras `trim`) lanza `DomainException`;
  - con origen `manual`, un `payment_id` no nulo lanza `DomainException`.

  El valor por defecto `null` deja válida la llamada manual tal como está
  (`OrderController:75`). Las llamadas con `mercadopago` y sin id, hoy unas 25 en
  `ConfirmPaymentTest` y `OrderPanelTest`, pasan a fallar a propósito y se adaptan (tarea 6).

  `order.paid` guarda `payment_id` en su payload de auditoría. Lo decidió el dueño: el dato queda en
  `audit_logs` y en el panel, no solo en el log.
- **Regla 151 (Spec 08)**: `order.paid_after_cancel` también guarda `payment_id`. Es el dato que hace
  falta para devolver el pago en MercadoPago, y la regla dice que ese caso se resuelve fuera del
  sistema.

Cada enmienda se anota como sincronía fechada en su spec, sin reescribir el texto original.

## Matriz de permisos

| Actor | Ve `X-Request-Id` | Lee `app-*.jsonl` | Ve `payment_id` en la auditoría |
|---|---|---|---|
| Cliente anónimo | sí (cabecera) | no | no |
| Admin | sí | no | sí (detalle del pedido, como hoy con la auditoría) |
| Vendedor, depósito | sí | no | como hoy: la auditoría del detalle es solo para admin (Spec 08) |
| Dueño (único contribuidor), en su máquina | sí | sí | sí |
| `incident-investigator` (externo) | — | sí, solo el directorio que se le pasa | no |

**Hasta dónde llega la garantía**: Revestimientos garantiza lo que escribe en sus logs. Que el
investigador lea solo ese directorio, y que no toque `.env` ni la base, es una propiedad **de su
código**, documentada en su repositorio. Revestimientos no puede garantizarla.

## Casos borde

- **`X-Request-Id` entrante**: nunca se adopta. El de MercadoPago queda como `mp_request_id`,
  truncado a 64 caracteres.
- **GET al webhook con parámetros arbitrarios**: `webhook.ignored` lleva `tipo` y `payment_id`
  truncados a 64 caracteres (OBS-01).
- **Mismo proceso, dos requests**: en local (FPM) cada request es un proceso limpio. En staging
  (Octane) el `Context` es `scoped`. El test de OBS-03 atiende dos requests en el mismo proceso de
  test. La verificación bajo Octane real queda para la etapa 2.
- **Transacción revertida**: si `PlaceOrderAction` falla bajo lock, no hay `order.created`. Sí hay
  `checkout.rejected` con motivo `dominio` y el `http.request` del 302.
- **Auditoría fuera de transacción** (`webhook.ignored`): se escribe en el momento.
- **`external_reference` no numérico en un pago no aprobado**: `order_id` es `null`.
- **Reintentos del webhook**: cada notificación deja su propio evento, y la secuencia se
  reconstruye por `payment_id`.
- **Día en UTC**: el archivo rota a las 21:00 de Argentina.
- **`/up`**: también genera `http.request`.
- **El log anterior**: `storage/logs/laravel.log` tiene datos personales, así que **se borra** como
  parte de la entrega.
- **Canal que no se puede construir**: si el canal `app` está mal configurado, Laravel cae al logger
  de emergencia y **recrea `laravel.log` en texto plano sin redactar**. Lo previene el criterio 1,
  que construye el canal `app` a partir de la configuración real y solo cambia la ruta. Si igual pasa, ese archivo no es parte del contrato y el
  consumidor no lo lee.
- **`.env` existente**: `make setup` no pisa un `.env` que ya existe (`cp -n`). El runbook indica
  poner `LOG_CHANNEL=app` a mano.
- **Segundo pago aprobado sobre un pedido ya pagado** (límite declarado): si el cliente paga dos
  veces, por ejemplo tras un reintento de la regla 126, la regla 152 hace de la segunda
  confirmación un no-op silencioso. Ese segundo `payment_id` no queda ni en `audit_logs` ni en el
  log; solo queda un `http.request`. Taparlo sería un incidente de pago nuevo, o sea una decisión
  de negocio fuera de esta spec.
- **Una fila de auditoría sin su espejo**: puede pasar si falla la escritura (OBS-08). Es aceptado y
  está declarado en el README del contrato (OBS-02).

## Criterios de aceptación

1. **Forma y correlación** (OBS-01, OBS-02, OBS-03): un request de prueba, con el canal `app`
   construido desde `config/logging.php` real y solo la ruta cambiada a un directorio temporal:
   - produce líneas que **validan contra el schema**;
   - las escribe en un archivo `app-AAAA-MM-DD.jsonl` con la fecha UTC;
   - todas llevan el mismo `request_id`, que coincide con la cabecera `X-Request-Id`.
2. **Tests fuera de `storage/logs/`** (OBS-09): el job de CI compara el listado y los hashes de
   `storage/logs/` antes y después de la suite, y falla si cambiaron.
3. **`request_id` propio** (OBS-03):
   - un request con `X-Request-Id: falso` responde con otro valor;
   - dos requests seguidos en el mismo proceso tienen `request_id` distintos y no comparten
     contexto.
4. **Resumen por request** (OBS-04): `http.request` de un `GET /catalogo?categoria=x` tiene
   `method`, `route`, `status` y `duration_ms`, y no contiene `categoria=x`.
5. **Espejo de la auditoría** (OBS-05.1):
   - crear un pedido deja una sola entrada `order.created`, con `order_id` y `subject_id` iguales al
     pedido;
   - un pedido que falla bajo lock no deja `order.created`, y deja `checkout.rejected` con motivo
     `dominio`;
   - el mapa de niveles se verifica para los cuatro eventos `warning` y para uno `info`.
6. **Checkout rechazado** (OBS-05.2):
   - cada fila de la tabla de puntos de emisión tiene su test;
   - `validacion` lista los campos y no los valores;
   - un test recorre **todos** los mensajes de `DomainException` de `PlaceOrderAction` y verifica
     que ninguno interpole valores.
7. **Derivación a pagar** (OBS-05.3, OBS-05.4):
   - un checkout por MercadoPago exitoso deja `checkout.payment_started`;
   - uno con la preferencia fallida deja `checkout.mp_preference_failed` y no deja
     `checkout.payment_started`;
   - uno por transferencia deja un solo `checkout.payment_started`, aunque `checkout.success` se
     abra dos veces.
8. **Webhook** (OBS-05.5 a OBS-05.7):
   - un pago `rejected` deja `webhook.payment_not_approved` con `payment_id`, `status` y `order_id`;
   - una consulta fallida deja `webhook.processing_failed` y la respuesta es 503;
   - una excepción dentro de `ConfirmPaymentAction` también deja `webhook.processing_failed`;
   - `order.paid` por webhook lleva `payment_id` y `order_id`, en el log y en `audit_logs`;
   - `order.paid_after_cancel` también los lleva;
   - una confirmación manual deja `payment_id: null`;
   - `mercadopago` sin `payment_id` y `manual` con `payment_id` lanzan `DomainException`, sin tocar
     el pedido ni el stock.
9. **`mp_request_id`** (OBS-05.1): `webhook.signature_invalid` lleva `mp_request_id` en el log, y la
   fila de auditoría conserva su clave `request_id`.
10. **Valores externos truncados** (OBS-01):
    - un `GET /webhook/mercadopago?type=<300 caracteres>&id=<300 caracteres>` deja
      `webhook.ignored` con los dos valores truncados a 64;
    - una cabecera `x-request-id` de 300 caracteres en un webhook con firma inválida queda truncada
      a 64.
11. **Redacción** (OBS-06, OBS-05.8): un
    `Log::info('x', ['customer_email' => 'centinela@ejemplo.test', 'datos' => ['phone' => '5491100000000'], 'product_name' => 'P'])`:
    - en el canal `app` sale como `app.log`, con los dos primeros valores en `"[redactado]"` y
      `product_name` intacto, y valida contra el schema;
    - en un canal `single` (el de staging), el texto escrito no contiene `centinela@ejemplo.test` ni
      `5491100000000`.
12. **Excepciones sin datos personales** (OBS-06, OBS-07):
    - un checkout completo con nombre, email, teléfono, CP y dirección de prueba no deja ninguno de
      esos valores;
    - una `QueryException` forzada en `PlaceOrderAction` con esos valores deja **una sola** entrada,
      `app.exception`, con `sqlstate`, `message: null` y una traza sin argumentos;
    - un `single` de staging tampoco contiene esos valores;
    - una `RuntimeException` de una librería deja `message: null`, y una `DomainException` de
      `app/` conserva su mensaje;
    - un 404 y un 403 no generan `app.exception`.
13. **`zend.exception_ignore_args`** (OBS-07): el valor está fijado en `docker/php/php.ini` y en
    los `ini-values` de `setup-php` en CI, y un test verifica `ini_get` en el entorno donde corre.
14. **El log no rompe el negocio** (OBS-08): con el directorio de logs sin permisos de escritura,
    un checkout responde igual y crea el pedido una sola vez.
15. **Calidad**: Pint, PHPStan nivel 8 y la suite en verde. Cada regla verificada mutando la
    implementación.

## Tareas técnicas

1. **El contrato**: `docs/observabilidad/log-schema.v1.json`, con el catálogo, las descripciones y
   los `maxLength`, y `docs/observabilidad/README.md`, que deriva del schema e incluye la regla de
   compatibilidad y la nota de OBS-02.
2. **Canales**:
   - Canal `app`: un `stack` con `ignore_exceptions: true` sobre un `daily` con el formateador
     propio de OBS-01.
   - `LOG_CHANNEL=app` en `.env.example` y `LOG_CHANNEL=null` en `phpunit.xml`.
   - Procesador de redacción (OBS-06), registrado en **todos** los canales de `config/logging.php`.
3. **Middleware global**: genera el `request_id`, lo pone en `Context`, lo devuelve en
   `X-Request-Id` y escribe `http.request` en `terminate`.
4. **Espejo de la auditoría**: `AuditRecorder` emite el espejo de OBS-05.1 después del commit
   (`DB::afterCommit` o equivalente, a verificar con `RefreshDatabase`), con el mapa de niveles, los
   identificadores transversales y el renombre de `mp_request_id`.
5. **Eventos de checkout y webhook**:
   - `checkout.rejected`, en los puntos de la tabla de OBS-05.2. `validacion` se engancha al Form
     Request.
   - `checkout.payment_started`, `checkout.mp_preference_failed`, `webhook.payment_not_approved` y
     `webhook.processing_failed`.
   - Quitar los tres `Log::error` actuales.
6. **`payment_id` en la confirmación**: la firma y la validación de §Enmiendas.
   `ProcessMercadoPagoNotificationAction` pasa el id y la confirmación manual no cambia. Los tests
   existentes que confirman con `mercadopago` se adaptan para pasar un id.
7. **Excepciones**:
   - `app.exception` vía `withExceptions` en `bootstrap/app.php`, con un callback que **detiene** el
     reporte por defecto;
   - `zend.exception_ignore_args = On` en `docker/php/php.ini` y en los `ini-values` de CI.
   - En `.github/workflows/ci.yml`, un paso que compara el listado y los hashes de `storage/logs/`
     antes y después de la suite (criterio 2).
8. **Validación en tests**: `opis/json-schema` en `require-dev` (aprobada por el dueño).
9. **Sincronías**:
   - Spec 07.4 (regla 124) y Spec 08 (reglas 150 y 151).
   - ADR-004 anota que ADR-013 la enmienda.
   - `arquitectura.md`.
   - Glosario, con términos técnicos: "evento de log", `request_id`, "contrato de logs" y "catálogo
     de eventos (de log)", distinguido del **Catálogo público**. También "incidente operativo",
     distinguido del **Incidente de pago**.
   - `.ai/rules/`, con una regla durable: todo `Log::` nuevo en `app/` es un evento del catálogo
     que pasa por el criterio de OBS-05, se declara en el schema y no lleva datos personales.
   - Roadmap y runbook del README.
10. **Borrar el log viejo**: `storage/logs/laravel.log` local.

## Decisiones del dueño sobre la implementación (2026-09-24)

- **`opis/json-schema` en `require-dev`: aprobada.** Los tests validan formalmente cada línea contra
  el contrato (tarea 8).
- **Carpeta nueva `app/Logging/`: aprobada**, para el formateador, el procesador de redacción y el
  mapa de niveles. Es una responsabilidad nueva y acotada. El middleware va en
  `app/Http/Middleware/`.

## Sincronía 2026-09-24 — implementación

Implementada en la rama `feat/observabilidad-01-contrato-logs`, en TDD. Suite: **546 tests en verde**
(500 de base más 46 del contrato en `tests/Feature/Observabilidad/`), con Pint y PHPStan nivel 8
limpios. Se aplicaron **31 mutaciones** a la implementación, una o más por regla OBS y por enmienda,
y todas pusieron algún test en rojo.

**Cómo quedó** (detalle en `docs/arquitectura.md` §Observabilidad y `.ai/rules/observabilidad.md`):
- `app/Logging/`: `EventLog`, `ContractFormatter`, `RedactPersonalData`, `ApplyRedaction` y
  `ErrorSerializer`.
- `app/Http/Middleware/AssignRequestId`.
- El contrato en `docs/observabilidad/`.

**Decisiones de implementación que la spec dejaba abiertas**:
- **`app.log` lleva también `context`**, redactado y con los textos truncados a 256 caracteres. OBS-05.8
  nombraba solo `attributes.message`, pero el criterio 11 exige ver el contexto redactado de una línea
  suelta, y sin él no se podía cumplir. Queda declarado en el schema. Es riesgo residual, igual que el
  mensaje.
- **La redacción se registra con un `tap` en cada canal.** Laravel solo lee `processors` en el driver
  `monolog`, mientras que el `tap` funciona en todos. El procesador del `Context` corre antes, así que
  la redacción ve también el `extra`.
- **Mensaje de `ConfirmPaymentAction` para el `payment_id`**: son dos `DomainException` con texto
  fijo, "La confirmación de MercadoPago requiere el id del pago." y "La confirmación manual no lleva
  id de pago.".
- **Criterio 2**: el paso de CI compara los hashes de `storage/logs/` antes y después de la suite.
  Localmente se verificó igual: la suite no agregó ninguna entrada `testing.*`.

**Hallazgos**:
- **`storage/logs/browser.log`** lo escribe Laravel Boost (paquete de desarrollo, `require-dev`)
  desde el navegador del desarrollador, en un canal que Boost arma en tiempo de ejecución, sin el
  `tap` de redacción. Guarda el user agent **del propio desarrollador**, no el de un cliente, y no
  existe en staging (`composer install --no-dev`). No se toca. Queda declarado como excepción de
  OBS-06 limitada a desarrollo.
- **`opis/json-schema` se instaló con `--ignore-platform-req=ext-sockets`.** El contenedor local no
  tiene esa extensión, que solo exige RoadRunner (staging). Es un problema previo del entorno local,
  ajeno a esta spec.
- **`composer audit`** reporta 4 advisories de `league/commonmark`. Ya existían antes de esta rama y
  no los trae la dependencia nueva.

**Pendiente fuera de la suite** (tarea 10 y runbook, en la máquina del dueño):
- borrar `storage/logs/laravel.log`;
- poner `LOG_CHANNEL=app` en el `.env` local;
- reconstruir la imagen `app` (`docker compose build app`) para que tome
  `zend.exception_ignore_args`.

## Sincronía 2026-09-24 — revisión de `revisor-entrega`

La primera auditoría **bloqueó** la entrega. El código estaba sano; fallaba la cobertura:

- **Bloqueante**: el test del criterio 1 hacía un `GET /catalogo`, que escribe una sola línea
  (`http.request`). Con un formateador que anulaba el `request_id` de cualquier otro evento, la
  suite seguía verde. Ahora el test usa un checkout, que escribe `order.created`,
  `checkout.payment_started` y `http.request`, y afirma el id en cada línea. Un test aparte afirma
  el `request_id` de `app.exception`.
- **8 mutaciones que sobrevivían**, ahora en rojo:
  - la redacción sin distinguir mayúsculas y la del `extra` (`Context`);
  - el `tap` en `stderr` y en `daily`: un test recorre `config('logging.channels')` y exige
    `ApplyRedaction` en todo canal que escribe;
  - `DomainException` lanzada fuera de `app/` y `PDOException` suelta, que no conservan su mensaje;
  - el truncado del `context` de `app.log`.
- **Criterio 13**: además del `ini_get`, un test lee `docker/php/php.ini` —el que copia la imagen de
  staging— porque CI no lo carga.

Suite: **554 tests**. **Corrección** a la sincronía anterior: la imagen `app` local ya está
reconstruida. De la tarea 10 quedan solo `LOG_CHANNEL=app` en el `.env` local y borrar
`storage/logs/laravel.log`.
