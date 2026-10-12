# Spec — Observabilidad 02: ampliación del contrato de logs v1 para el investigador

- **Estado**: **implementada (2026-10-12)** en la rama `feat/observabilidad-02-ampliacion-contrato`,
  pendiente de `revisor-entrega` y del PR a `main` (§Sincronía 2026-10-12 — implementación). Fue
  **aprobada (2026-10-12)** por el dueño, tras dos pasadas de `revisor-spec`. La segunda fue
  "aprobable con correcciones menores", y las correcciones ya estaban aplicadas en la v3. La
  aprobación confirmó D1, con su alcance a staging, y la enmienda de la regla 113.
- **Historia del borrador**:
  - **v1**: la primera pasada de `revisor-spec` la marcó "necesita otra vuelta, corta". Los
    hallazgos fueron:
    - dos bloqueantes de declaración (B1, la regla 113; B2, OBS-01 y OBS-04);
    - ocho importantes (I1–I8): `checkout.returned` con `"null"` literal, con arreglos y con
      `order_id` cruzado; `.gitignore` sin versionar; la retención y su variable compartida; el
      alcance de `session_ref` a staging; el HMAC sin test; el handoff;
    - siete menores (M1–M7).
  - **v2**: aplica todos esos hallazgos y anota las decisiones del dueño D1–D3 del mismo día. La
    segunda pasada la dio "aprobable con correcciones menores":
    - N1: el caso borde "volvió sin pagar" contradecía al criterio 3;
    - N2: `git ls-files` no corre en el contenedor;
    - M-N1 a M-N3.
  - **v3**: aplica N1, N2 y M-N1 a M-N3.
- **Fuentes**:
  - Pedido del dueño del 2026-10-12: Revestimientos necesita las specs de los cambios que pide el
    investigador, escritas para que las implemente un agente sin contexto previo.
  - Los requisitos del investigador (`~/incident-investigator/docs/requisitos.md`):
    - definen el usuario: el desarrollador atendiendo el reclamo de un cliente;
    - definen once casos de uso (CU1 a CU11) y su cobertura con el contrato v1.
  - [Spec `observabilidad-01-contrato-logs`](observabilidad-01-contrato-logs.md), cerrada, que esta
    spec amplía. Mantiene sus decisiones del dueño del 2026-09-24, en especial:
    - **sin datos personales**;
    - un evento entra al contrato **solo si tapa un agujero que hoy impide investigar un
      incidente**;
    - local primero, planes gratuitos.
  - [ADR-013](../adr/ADR-013-capa-observabilidad.md). Esta spec sigue en la **etapa 1** (local).
    La etapa 2 (staging) no cambia y sigue sin spec.
  - Las decisiones del dueño del 2026-10-12 (§Decisiones del dueño).
  - El relevamiento del código del 2026-10-12 (§Contexto).

## Numeración y enmiendas

- **Numeración**: sigue la propia de observabilidad-01, desde **OBS-10**, por el mismo motivo que
  esa spec: no agrega reglas de negocio, cambia qué deja escrito el sistema mientras hace lo que ya
  hacía.
- **Una enmienda a una regla de negocio**: OBS-14 cambia el payload de auditoría de `order.created`
  que fija la **regla 113** (Spec 07.2). Ese payload es dato de negocio, porque queda en
  `audit_logs` y el admin lo ve en el detalle del pedido. Es el mismo caso que las reglas 150 y 151
  en observabilidad-01.
- **Enmiendas a reglas cerradas de observabilidad-01**: OBS-01, OBS-02 y OBS-04.
- Todas se detallan en §Enmiendas.

## Compatibilidad: todo sigue siendo v1

Cada cambio del schema es un **evento nuevo**, un **atributo opcional nuevo** o una aclaración de
una descripción que no cambia el significado. La regla de compatibilidad del contrato los declara
compatibles, así que `schema_version` sigue en `"1"`.

El **lugar de entrega** (OBS-10) no es parte del schema sino de OBS-02. Hoy no hay ningún consumidor
implementado: el investigador todavía no tiene código. Por eso se cambia ahora y no después.

## Objetivo

Que los casos de uso del investigador que hoy quedan **parciales** con el contrato v1 se puedan
responder solo leyendo los logs, sin agregar datos personales. Y que el investigador pueda leer el
contrato **sin ver ningún otro archivo** de `storage/logs/`.

## Alcance

**Entra**:
- el directorio propio del contrato (OBS-10);
- la retención del canal del contrato (OBS-11);
- el evento de retorno desde MercadoPago (OBS-12);
- la referencia seudónima de sesión en `http.request` (OBS-13);
- el precio unitario en las líneas de `order.created` (OBS-14);
- el conocimiento del dominio en las descripciones del schema (OBS-15);
- las enmiendas de §Enmiendas.

**No entra**:
- el investigador;
- los logs de staging como fuente (etapa 2 de ADR-013);
- `actor_id` en los espejos de auditoría y `release` en cada línea (§Alternativas descartadas).

## Contexto — qué hay hoy exactamente (2026-10-12)

**Cómo usa los logs el investigador.** El usuario es el desarrollador de la tienda atendiendo el
reclamo de un cliente. Traduce la identidad del cliente a un número de pedido con el panel de admin,
y el agente recibe solo identificadores (`order_id`, `payment_id`) y una ventana de tiempo.

El contrato vive **solo en local** (etapa 1), así que en `storage/logs/contrato/` no hay reclamos
reales: hay **reproducciones** de incidentes hechas por el desarrollador. Con ellas se arman los
escenarios de evaluación del investigador.

Con el contrato v1 quedan **parciales** estos casos:

| Caso del investigador | Agujero | Lo tapa |
|---|---|---|
| CU1 "Pagué y el pedido sigue pendiente" | Después de `checkout.payment_started`, "abandonó en MercadoPago" y "pagó pero el webhook se perdió" se ven igual. Es el límite declarado del README del contrato | OBS-12 |
| CU6 "No pude comprar / me dio error" | `checkout.rejected` no se puede atar a un visitante: no lleva `order_id`, y la sesión no aparece en ninguna línea | OBS-13 |
| CU8 "El precio que pagué no es el que vi" | Las `lines` de `order.created` traen `product_id` y `cantidad`, pero no el precio congelado | OBS-14 |
| Todos | El investigador tiene que poder montar el directorio de logs en solo lectura, y hoy ese directorio mezcla el contrato con `browser.log` (Boost) y con `laravel.log` (logger de emergencia). Ninguno de los dos tiene la garantía de OBS-06 | OBS-10 |
| Escenarios reproducidos hace más de dos semanas | La retención es de 14 días | OBS-11 |

**Código involucrado**:
- **`config/logging.php`**:
  - El canal `app_file` (líneas 73–80) es un `daily` con
    `path => storage_path('logs/app.jsonl')` y `days => env('LOG_DAILY_DAYS', 14)`.
  - **La misma variable `LOG_DAILY_DAYS` gobierna el canal `daily`** (línea 95, `laravel.log`).
  - `.env.example` no define `LOG_DAILY_DAYS`.
- **`storage/logs/.gitignore`**: contiene `*` y `!.gitignore`. Excluye cualquier subdirectorio, y
  git no versiona archivos dentro de un directorio excluido.
- **`app/Http/Middleware/AssignRequestId.php`**:
  - Genera el `request_id` y escribe `http.request` en `terminate()`, con `method`, `route`,
    `status`, `duration_ms` y `user_id` (solo con usuario interno autenticado).
  - Se registra con `prepend`, antes que la sesión (`bootstrap/app.php:23`). En `terminate()` la
    sesión del request ya está disponible.
- **`app/Logging/EventLog.php`**:
  - `CLAVES_EXTERNAS` (`payment_id`, `tipo`, `mp_request_id`, `external_reference` y `status`) se
    truncan a 64 caracteres.
  - **Solo si son string** (líneas 38–41): un arreglo pasa sin truncar.
  - `record()` escribe en el **canal por defecto**, sea cual sea: `app` en local, y lo que tenga
    staging.
- **`app/Http/Controllers/CheckoutController.php`**:
  - `store` guarda `session(['order_id' => $order->id])` (línea 65) cada vez que crea un pedido,
    sin borrar el anterior. La sesión guarda el **último** pedido creado.
  - `success` (líneas 127–147) lee `session('order_id')`. Si no hay pedido en sesión, redirige al
    carrito.
- **`app/Services/MercadoPagoGateway.php`**:
  - `preferencePayload()` (desde la línea 123) manda las tres `back_urls` (`success`, `failure` y
    `pending`) a la misma ruta, `checkout.success` (`GET /checkout/exito`), con
    `external_reference = (string) $order->id` (regla 123).
  - `auto_return` se agrega solo si esa URL es pública (`backUrlEsPublica`), así que **en local el
    cliente vuelve solo si toca "Volver al sitio" en MercadoPago**.
- **Retorno de MercadoPago**:
  - Al volver, MercadoPago agrega parámetros al query string, entre ellos `collection_status`,
    `payment_id` y `external_reference`.
  - **No vienen firmados**: los puede escribir cualquiera que arme la URL a mano.
  - Si el cliente vuelve **sin haber pagado**, MercadoPago manda los valores como el **texto
    literal `"null"`**.
  - Los nombres y valores exactos se verifican contra la documentación vigente de MercadoPago al
    implementar (tarea 1).
- **`app/Actions/PlaceOrderAction.php`**:
  - El precio de cada línea se calcula en las líneas 104–114 (`$linesData[]['precio_unitario_cents']`).
  - La auditoría de `order.created` (líneas 156–163) arma `lines` con un `map` sobre `$linesData`,
    con la variable `$l`, y guarda solo `product_id` y `cantidad`.
- **`docs/observabilidad/log-schema.v1.json`**:
  - El `enum` de `event` es cerrado.
  - `http.request` tiene `additionalProperties: false`, y su descripción dice "Nunca lleva … las
    cookies".
  - Los ítems de `lines` en `order.created` tienen `additionalProperties: true`.
- **Sesión**:
  - `SESSION_DRIVER=redis` y `SESSION_LIFETIME=120`.
  - El cliente es anónimo (regla 27), y el carrito vive en sesión, sin tabla (regla 81, Spec 05).
  - El id de sesión cambia en el login (`AuthenticatedSessionController:29`, `regenerate()`) y en
    el logout (línea 41, `invalidate()`) de los usuarios internos, y cuando la sesión vence.
- **Tests**:
  - `tests/Pest.php` tiene `canalDeContrato()`, que construye el canal real con la ruta en un
    directorio temporal.
  - También tiene `lineasDelContrato()`, que lee `app-*.jsonl` de ese directorio y valida cada línea
    contra el schema.
  - Los tests del contrato están en `tests/Feature/Observabilidad/`.
- **CI** (`.github/workflows/ci.yml`, líneas 72–90): compara los hashes de **todo**
  `storage/logs/`, recursivo, antes y después de la suite.

## Reglas

**OBS-10 — Directorio propio del contrato.**
- El canal `app_file` escribe en `storage/logs/contrato/app-AAAA-MM-DD.jsonl`.
- En ese directorio no hay **ningún** otro archivo que escriba la aplicación, el framework o un
  paquete. La única excepción es su `.gitignore`.
- **El directorio existe en un clon limpio**, para que nadie lo cree por accidente con otro dueño,
  por ejemplo Docker como `root` al montarlo. Para eso:
  - se versiona `storage/logs/contrato/.gitignore`, con `*` y `!.gitignore`;
  - `storage/logs/.gitignore` suma `!contrato/`, porque sin esa línea git no versiona nada dentro
    del subdirectorio.
- El consumidor recibe la ruta de **ese** directorio y lee `app-*.jsonl`.
- `storage/logs/` sigue alojando `browser.log` y, si aparece, `laravel.log`. Ninguno es parte del
  contrato.

**OBS-11 — Retención del canal del contrato.**
- El canal `app_file` conserva **90 días** por defecto (decisión D2).
- Pasa a leer su **propia variable**, `LOG_CONTRATO_DAYS`, que se declara en `.env.example` con
  `90`.
- `LOG_DAILY_DAYS` sigue gobernando solo el canal `daily`, con sus 14 días.

**OBS-12 — `checkout.returned`: el navegador del cliente volvió desde MercadoPago.**
- **Cuándo**:
  - En `GET /checkout/exito` (`success`), cuando el request trae el parámetro `collection_status`,
    aunque su valor sea `"null"`.
  - Se emite **antes** de cualquier redirect, incluido el que manda al carrito si la sesión no tiene
    pedido.
  - Sin ese parámetro no se emite: por ejemplo, al volver de una transferencia o al recargar la
    página sin query string.
- **Nivel**: `info`.
- **Lectura de los parámetros**: cada uno se toma **solo si es un string escalar**. Si llega como
  arreglo (`?collection_status[]=…`), si está vacío o si es el texto literal `"null"`, se escribe
  `null`.
- **`attributes`**:

  | Clave | Tipo | Valor |
  |---|---|---|
  | `status` | string o `null` | `collection_status`, leído según la regla anterior. Valor externo, truncado a 64 |
  | `payment_id` | string o `null` | `payment_id`, leído igual. Valor externo, truncado a 64 |
  | `external_reference` | string o `null` | `external_reference`, leído igual. Valor externo, truncado a 64 |
  | `order_id` | entero o `null` | `session('order_id')`, **solo si coincide** con `external_reference`, con comparación estricta de strings: `(string) session('order_id') === $externalReference`, sobre el valor ya normalizado. Así `"07"` no coincide con `7`. Si no coincide, o si falta cualquiera de los dos, `null` (decisión de la v2, por el hallazgo I3) |

- **Por qué `order_id` exige la coincidencia**:
  - La sesión guarda el último pedido creado, y el cliente puede estar volviendo de un pedido
    anterior.
  - `external_reference` viene sin firma, así que solo no alcanza para afirmar un `order_id`.
  - La coincidencia de los dos es la única forma de que `order_id`, la clave por la que filtra
    siempre el consumidor (OBS-01), no apunte al pedido equivocado.
- **Significado, que va en la descripción del schema**, explicado sin citar specs:
  - El **navegador** del cliente volvió desde MercadoPago y dijo traer ese estado. **No confirma
    ningún pago**: los parámetros no vienen firmados. La confirmación es `order.paid`, que llega
    por el webhook después de consultar la API de MercadoPago.
  - `status: null` significa que volvió **sin un pago completado**, o que el valor no se pudo leer.
  - Un retorno con `status` aprobado y sin `order.paid` posterior indica un webhook demorado o
    perdido.
  - La **ausencia** de `checkout.returned` **no prueba un abandono**: en local no hay
    `auto_return`, y el cliente puede cerrar la pestaña después de pagar.
  - `order_id` es `null` cuando la sesión no coincide con `external_reference`. En ese caso
    `external_reference` es el único vínculo con el pedido, y no es confiable.
- **El README del contrato** reemplaza el límite "abandonó en MercadoPago y el webhook nunca llegó
  se ven igual" por este texto, más acotado: si el cliente volvió, se distingue; si no volvió, siguen
  viéndose igual.

**OBS-13 — `session_ref` en `http.request`.**
- **Qué es**: los primeros 16 caracteres hexadecimales de
  `hash_hmac('sha256', $sessionId, config('app.key'))`.
- **Dónde**:
  - Como atributo opcional de `http.request`, **solo si el request tiene sesión iniciada**.
  - Va únicamente en `http.request`: los demás eventos del mismo request se atan por `request_id`.
- **Alcance**: aparece en **todos los canales** donde se escribe `http.request`, staging incluido,
  con clientes reales. Ahí la retención la fija la plataforma, no OBS-11 (decisión D1).
- **No es la cookie**:
  - El id de sesión crudo es una credencial y no aparece nunca en ninguna línea.
  - `session_ref` es un seudónimo derivado con una clave que el consumidor no tiene, así que no se
    puede revertir a la cookie ni usar para suplantar la sesión.
  - Por eso no viola el "nunca cookies" de OBS-04, que se enmienda para decirlo (§Enmiendas).
- **Significado, que va en la descripción del schema**:
  - Agrupa los requests de un **mismo navegador** mientras dure su sesión (`SESSION_LIFETIME` de
    inactividad).
  - No identifica a una persona, y no sirve para buscar a un cliente.
  - Cambia cuando un usuario interno inicia o cierra sesión, cuando la sesión vence y cuando cambia
    `APP_KEY`.
- **Para qué la usa el investigador**:
  - A partir del `order.created` de un pedido, salta por `request_id` a su `http.request` y toma el
    `session_ref`.
  - Con eso encuentra los intentos previos del mismo navegador, incluidos los `checkout.rejected`.
  - Si el cliente **nunca** llegó a crear un pedido, `session_ref` no ayuda a encontrarlo: el caso
    queda en ventana de tiempo, y así lo tiene que declarar el investigador.

**OBS-14 — Precio unitario en las líneas de `order.created`.**
- Cada ítem de `lines` suma `precio_unitario_cents` (entero), que es el precio congelado de esa
  línea al crear el pedido (reglas 102 y 111). En modo `M2` es el precio por **caja**, igual que en
  `order_lines`.
- Se agrega en el payload de auditoría de `PlaceOrderAction`, así que queda en `audit_logs` y, por
  el espejo, en el log. Enmienda la regla 113 (§Enmiendas).
- En el schema se declara como propiedad opcional de cada ítem.

**OBS-15 — El conocimiento del dominio vive en las descripciones del schema.**
- El investigador no lee la documentación de la tienda, solo el schema (ADR-013, y ADR-004 del
  investigador).
- Estas descripciones de `x-events` se completan. Cada una **explica el comportamiento** y cita
  entre paréntesis la regla o ADR de origen, como ya hace `order.stock_negative` con la regla 145.
- Ninguna cambia el significado del evento: explicitan comportamiento que ya existe.
- Descripciones:
  - **`order.status_changed`**:
    - Una transición a `cancelled` la hace siempre un admin (regla 165): no hay cancelación por
      parte del cliente ni cancelaciones automáticas.
    - Un pedido en `pending_payment` **no vence**, porque no hay ningún proceso programado que lo
      cancele (ADR-012, descartada). Sigue así hasta que llega el pago o un admin lo cancela.
  - **`order.created`**:
    - Crear el pedido no descuenta stock: el stock se descuenta al confirmarse el pago (ADR-005).
    - `lines[].precio_unitario_cents` es el precio congelado de la línea; en productos por m², es
      el precio por caja.
  - **`order.stock_restored`**: se devuelve al stock lo de un pedido `paid` o `shipped` que se
      cancela (regla 165). La devolución del dinero se hace fuera del sistema.
  - **`checkout.payment_started`**: el límite se reescribe según OBS-12. Si el cliente volvió, hay
      un `checkout.returned`; si no volvió, el abandono y el webhook perdido siguen viéndose igual.

## Enmiendas a specs cerradas

Cada enmienda se anota como **sincronía fechada** en su spec, sin reescribir el texto original.

- **Regla 113 (Spec 07.2)**:
  - El payload de auditoría de `order.created` pasa de `lines: [product_id, cantidad]` a
    `lines: [product_id, cantidad, precio_unitario_cents]` (OBS-14).
  - El precio también queda en `audit_logs`, no solo en el log, igual que se decidió para el
    `payment_id` en las reglas 150 y 151.
  - **Confirmada por el dueño** al aprobar esta spec (2026-10-12).
- **OBS-01 (observabilidad-01)**: "las líneas de un pedido (`product_id` y `cantidad`)" pasa a
  incluir `precio_unitario_cents`.
- **OBS-02 (observabilidad-01)**:
  - La ruta pasa de `storage/logs/app-AAAA-MM-DD.jsonl` a
    `storage/logs/contrato/app-AAAA-MM-DD.jsonl` (OBS-10).
  - La retención pasa de 14 a 90 días, con la variable propia `LOG_CONTRATO_DAYS` (OBS-11).
- **OBS-04 (observabilidad-01)**:
  - `http.request` suma `session_ref` como atributo opcional (OBS-13).
  - El "nunca incluye … las cookies" se mantiene, con la aclaración de que `session_ref` es un
    seudónimo irreversible sin la clave, y no la cookie.

## Matriz de permisos

No cambia ningún permiso de la aplicación.

| Actor | Lee `storage/logs/contrato/` | Ve `session_ref` | Ve `precio_unitario_cents` en `audit_logs` |
|---|---|---|---|
| Cliente anónimo | no | no | no |
| Admin | no | no | sí: ya ve la auditoría del detalle del pedido (Spec 08) |
| Vendedor, depósito | no | no | como hoy: la auditoría del detalle es solo para admin |
| Dueño, en su máquina | sí | sí | sí |
| `incident-investigator` (externo) | sí, solo ese directorio y en solo lectura | sí | no (lo ve en el log, en `order.created`) |

## Casos borde

- **`checkout.returned`**:
  - **Recarga de `/checkout/exito` con el query string de MercadoPago**: emite otro
    `checkout.returned`. Se acepta: cada línea es una navegación real, y el investigador los agrupa
    por `order_id` o `external_reference`.
  - **Retorno en otro navegador o con la sesión vencida**: `order_id` es `null` y
    `external_reference` es el único vínculo, no confiable.
  - **Retorno desde un pedido anterior** (el cliente creó A, después B, y vuelve de MercadoPago por
    A): la sesión dice B y `external_reference` dice A. No coinciden, así que `order_id` es `null`.
  - **Volvió sin pagar**: MercadoPago manda `collection_status=null` y `payment_id=null`, pero
    `external_reference` trae el id real del pedido. El evento se emite:
    - `status` y `payment_id` son `null`;
    - `external_reference` lleva el id;
    - si coincide con la sesión, `order_id` también.

    Es la señal "volvió sin pagar del pedido N" que necesita CU1.
  - **Parámetros como arreglo** (`?collection_status[]=x`): el valor es `null`, el evento se emite y
    la línea valida contra el schema.
  - **`collection_status` con un valor inventado** (URL armada a mano): se escribe truncado. La
    descripción del schema ya dice que no confirma nada.
  - **Retorno de una transferencia**: no trae `collection_status`, así que no emite.
- **`session_ref`**:
  - **Request sin sesión** (`/up`, por ejemplo): `http.request` sin `session_ref`.
  - **Webhook de MercadoPago**: pasa por el grupo `web` y puede tener una sesión nueva en cada
    notificación. Su `session_ref` no agrupa nada útil. Se acepta para no agregar un caso especial.
  - **Login o logout de un usuario interno**: el id de sesión cambia y `session_ref` también. No
    afecta a los clientes, que son anónimos.
  - **Rotación de `APP_KEY`**: cambian todos los `session_ref` desde ese momento. Es aceptado y queda
    declarado en la descripción.
- **Otros**:
  - **Líneas viejas sin `precio_unitario_cents`**: los pedidos creados antes de esta spec no lo
    tienen. El atributo es opcional y el investigador tolera su ausencia.
  - **Archivos anteriores en `storage/logs/app-*.jsonl`**: no se mueven ni se migran. Siguen
    existiendo hasta que el dueño los borre (tarea 9).
  - **`.env` local existente**: no tiene `LOG_CONTRATO_DAYS`, así que toma el default de 90 del
    config, que es el valor buscado.

## Criterios de aceptación

1. **Directorio propio** (OBS-10):
   - con el canal `app` construido desde `config/logging.php` real, la ruta configurada de
     `app_file` está dentro de `storage/logs/contrato/`;
   - en Pest: `storage/logs/.gitignore` contiene `!contrato/`, y `storage/logs/contrato/.gitignore`
     existe con `*` y `!.gitignore`;
   - en CI, no en Pest (el contenedor `app` no tiene `git`): un paso de `ci.yml` corre
     `git ls-files --error-unmatch storage/logs/contrato/.gitignore`;
   - el paso de CI de OBS-09 sigue en verde, porque cubre `storage/logs/` de forma recursiva.
2. **Retención** (OBS-11):
   - sin `LOG_CONTRATO_DAYS` en el entorno, `config('logging.channels.app_file.days')` vale 90. El
     test quita la variable de `$_ENV` y `$_SERVER` y vuelve a evaluar `config/logging.php`, para no
     depender del `.env` de quien lo corre;
   - `config('logging.channels.daily.days')` sigue en 14 sin `LOG_DAILY_DAYS`;
   - `.env.example` declara `LOG_CONTRATO_DAYS=90` y no declara `LOG_DAILY_DAYS`.
3. **`checkout.returned`** (OBS-12):
   - `GET /checkout/exito?collection_status=approved&payment_id=123&external_reference=7`, con
     `order_id` 7 en sesión, deja una sola línea `checkout.returned`, de nivel `info`, con
     `order_id: 7` y los tres valores, que valida contra el schema;
   - con `order_id` 8 en sesión y `external_reference=7`, la línea lleva `order_id: null`;
   - con `order_id` 7 en sesión y `external_reference=07`, la línea lleva `order_id: null`;
   - sin pedido en sesión, la línea lleva `order_id: null` y la respuesta redirige al carrito;
   - con `collection_status=null&payment_id=null&external_reference=7`, `status` y `payment_id` son
     `null`;
   - con `collection_status[]=x`, `status` es `null` y la línea valida contra el schema;
   - sin `collection_status`, no hay `checkout.returned`;
   - con valores de 300 caracteres, los tres quedan truncados a 64.
4. **`session_ref`** (OBS-13):
   - en un request con sesión, `session_ref` es igual a
     `substr(hash_hmac('sha256', <id de sesión>, config('app.key')), 0, 16)`;
   - dos requests de la misma sesión tienen el mismo `session_ref`;
   - dos sesiones distintas tienen `session_ref` distintos;
   - el id de sesión crudo no aparece en ninguna línea del archivo (búsqueda textual);
   - una línea de un request sin sesión no tiene `session_ref`;
   - todas las líneas validan contra el schema.
5. **Precio unitario** (OBS-14): crear un pedido con una línea en modo `Unidad` y otra en modo `M2`
   deja `order.created` con `precio_unitario_cents` en cada ítem, igual al de su `order_lines` (el
   de la línea `M2`, por caja), en el log y en `audit_logs`.
6. **Descripciones** (OBS-15): un test verifica que las descripciones de `order.status_changed`,
   `order.created`, `order.stock_restored` y `checkout.payment_started` contienen sus referencias de
   origen ("regla 165", "ADR-012", "ADR-005"). También verifica que `checkout.returned` y
   `http.request` declaran que no confirman pagos y que `session_ref` no es la cookie.
7. **Calidad**:
   - Pint, PHPStan nivel 8 y la suite en verde;
   - cada regla OBS-10 a OBS-14 verificada **mutando la implementación**, con algún test en rojo
     por mutación. Por ejemplo: cambiar el HMAC por un `hash` sin clave, o quitar la coincidencia
     de `order_id`.

## Tareas técnicas

1. **Verificar los parámetros de retorno** de Checkout Pro contra la documentación vigente de
   MercadoPago: `collection_status`, `payment_id`, `external_reference` y el `"null"` literal.
   - Si difieren de lo que dice esta spec, se detiene la implementación y se pregunta al dueño
     (`AGENTS.md`, §Ambigüedad).
2. **Schema** (`docs/observabilidad/log-schema.v1.json`). **El bloque de cada regla se agrega junto
   con su regla**, no al final: el `enum` de `event` es cerrado y `http.request` no admite
   atributos extra, así que sin el schema los tests de la regla no validan. Los bloques son:
   - OBS-12: `checkout.returned` en el `enum`, su bloque en `allOf` con `maxLength: 64` en los
     valores externos, y su descripción en `x-events`;
   - OBS-13: `session_ref` opcional en `http.request`, con `pattern: ^[0-9a-f]{16}$`, y la
     descripción de `http.request` actualizada;
   - OBS-14: `precio_unitario_cents` opcional en los ítems de `lines` de `order.created`;
   - OBS-15: solo las descripciones de su lista.
3. **Canal**:
   - en `config/logging.php`, la ruta de `app_file` y `days => env('LOG_CONTRATO_DAYS', 90)`;
   - `storage/logs/contrato/.gitignore` y `!contrato/` en `storage/logs/.gitignore`;
   - `LOG_CONTRATO_DAYS=90` en `.env.example`;
   - en `.github/workflows/ci.yml`, el paso `git ls-files --error-unmatch` del criterio 1.
4. **`checkout.returned`**: en `CheckoutController::success`, antes del primer `return`. La lectura
   escalar y la normalización de `"null"` van en un método privado del controlador. El truncado lo
   hace `EventLog`, que ya cubre `payment_id`, `status` y `external_reference`.
5. **`session_ref`**: en `AssignRequestId::terminate()`, con `$request->hasSession()`.
6. **Precio unitario**: en el `map` de `lines` de `PlaceOrderAction::execute`, con
   `'precio_unitario_cents' => $l['precio_unitario_cents']`.
7. **Tests** en `tests/Feature/Observabilidad/`, con `canalDeContrato()` y `lineasDelContrato()`.
8. **Sincronías**, en commit `docs:` propio:
   - Spec 07.2 (regla 113) y observabilidad-01 (OBS-01, OBS-02 y OBS-04), como sincronías fechadas;
   - `docs/observabilidad/README.md`:
     - §Cómo se entrega: la ruta y la retención;
     - §Qué no vas a encontrar nunca: la aclaración de `session_ref`;
     - §Límites: el texto nuevo de OBS-12;
   - `docs/arquitectura.md` §Observabilidad;
   - `.ai/rules/observabilidad.md`, por la ruta nueva y la lectura escalar de parámetros externos;
   - glosario: `session_ref` y "retorno desde MercadoPago" como términos técnicos;
   - roadmap y runbook del README.
9. **Pasos a mano del dueño**, anotados en el runbook:
   - borrar los `storage/logs/app-*.jsonl` viejos;
   - avisar al investigador para que vuelva a copiar el schema en
     `contracts/revestimientos/`.

## Decisiones del dueño (2026-10-12)

- **D1 — `session_ref` se acepta** bajo "sin datos personales". No es ninguno de los datos que lista
  OBS-06, no es reversible sin `APP_KEY` y vence con la sesión. Es la única forma de cerrar CU6 sin
  pedirle datos al cliente.
  - **Alcance que la v1 del borrador no explicitaba (hallazgo I6)**: el atributo sale en todos los
    canales, staging incluido, con clientes reales. Allá lo ya escrito se conserva lo que retenga la
    plataforma.
  - **Confirmado por el dueño** al aprobar esta spec (2026-10-12).
- **D2 — Retención de 90 días.**
  - **Motivo real**: el contrato es solo local, así que lo que se conserva son **reproducciones**
    de incidentes y no reclamos de clientes. 90 días permiten volver a un escenario reproducido
    mientras se arma la suite de evaluación del investigador.
  - La v1 del borrador lo justificaba con reclamos tardíos de clientes, que en local no existen
    (hallazgo I5).
  - Se aplica con una variable propia, para no alargar también `laravel.log`.
- **D3 — El directorio se llama `contrato/`**, en español, como el resto del repo.

## Alternativas descartadas

- **`actor_id` en los espejos de auditoría**: `audit_logs` ya lo guarda, y por la regla 165 se sabe
  que toda cancelación es de un admin. Saber **cuál** admin no cambia la respuesta a ningún reclamo
  de cliente. No tapa un agujero, así que no entra.
- **`release` (versión desplegada) en cada línea**: en local no hay despliegues. Se reevalúa en la
  etapa 2 de ADR-013.
- **`session_ref` en todos los eventos**: es redundante, porque `request_id` ya ata cada evento a
  su `http.request`. Ponerlo solo ahí sigue el patrón del resumen por request (OBS-04).
- **`order_id` de `checkout.returned` tomado de la sesión sin verificar**: con varios pedidos en la
  misma sesión apuntaría al equivocado (hallazgo I3).
- **`order_id` tomado de `external_reference`**: es un valor sin firma, y `order_id` es la clave por
  la que filtra el consumidor.
- **Seguir usando `LOG_DAILY_DAYS` para el canal del contrato**: subirla a 90 alargaría también
  `laravel.log` (hallazgo I5).
- **Leer `storage/logs/` entero y filtrar `app-*.jsonl` del lado del investigador**: la frontera de
  datos quedaría en el código del consumidor y no en el sistema de archivos. Es el mismo defecto
  que ADR-013 descartó para el agente dentro del repo (hallazgo B2 de esa ADR).

## Nota de handoff para el agente implementador

**Antes de empezar**: esta spec tiene que estar **aprobada y mergeada a `main`** por su propio PR
(rama `docs/observabilidad-02-spec`). Recién ahí se abre
`feat/observabilidad-02-ampliacion-contrato` desde `main` actualizado.

**Orden de lectura**:
1. `AGENTS.md`;
2. `PROJECT_PRINCIPLES.md`;
3. esta spec;
4. la spec observabilidad-01 entera (las reglas OBS-01 a OBS-09 siguen vigentes, con las enmiendas
   de esta spec);
5. ADR-013;
6. `docs/observabilidad/README.md`;
7. `.ai/rules/index.md`, las reglas cuyo glob cubre los archivos que vas a tocar
   (`observabilidad.md`, `controllers.md`, `actions.md`, `tests.md`, entre otras) y
   `grep -rin 'log\|checkout\|sesión\|session' .ai/rules`.

**Cómo trabajar**:
- TDD, una regla por vez: test en rojo → implementación con **su bloque de schema** → refactor.
- **Orden sugerido**:
  1. OBS-10 y OBS-11 (configuración);
  2. OBS-14 (un `map`);
  3. OBS-12;
  4. OBS-13;
  5. OBS-15 (solo descripciones).
- **Todo corre en Docker**: `make format` → `make lint` → `make stan` → `make test`. Nunca dos
  suites en paralelo.
- **No agregar dependencias.** `hash_hmac` es nativo de PHP.
- **Antes del push**, pasar `revisor-entrega` (`.claude/agents/`). Su chequeo de mutaciones es parte
  del criterio 7.
- **Commits** en español, con Conventional Commits y **sin trailers de atribución a herramientas de
  IA**. Las sincronías de documentos (tarea 8) van en un commit `docs:` aparte.

## Sincronía 2026-10-12 — implementación

Implementada en la rama `feat/observabilidad-02-ampliacion-contrato`, con TDD y una regla por vez,
cada una con su commit `feat(observabilidad)`. Suite: **579 tests en verde** (559 de base más 20 en
`tests/Feature/Observabilidad/AmpliacionContratoTest.php`), con Pint y PHPStan nivel 8 limpios. La
suite no escribe en `storage/logs/`.

**Mutaciones** (aplicadas con una copia de respaldo y restauradas, una por vez):
- OBS-12: 7. Seis murieron. La que sobrevivió fue quitar la comparación con `''`, y sobrevivía
  porque esa rama nunca se ejecuta: `ConvertEmptyStringsToNull` ya convierte el parámetro vacío en
  `null`. Se quitó del código y el test que cubre el vacío se conserva.
- OBS-13: 5, todas muertas. Fueron hash sin clave, otra clave, id crudo, sin la guarda de sesión y
  otro largo.
- OBS-14: 1, muerta. Fue usar el precio de lista en lugar del precio congelado por caja.
- OBS-10, OBS-11 y OBS-15 se verifican con tests directos sobre la configuración, los `.gitignore`,
  `.env.example` y las descripciones del schema.

**Cómo quedó**:
- `config/logging.php`: `app_file` escribe en `storage/logs/contrato/app.jsonl` con
  `days => env('LOG_CONTRATO_DAYS', 90)`.
- `storage/logs/.gitignore` suma `!contrato/`, y se versiona `storage/logs/contrato/.gitignore`. En
  CI, el paso "El directorio del contrato está versionado" corre
  `git ls-files --error-unmatch storage/logs/contrato/.gitignore`. Se verificó que falla antes de
  versionarlo.
- `CheckoutController::success` recibe `Request` y llama a `registrarRetornoDeMercadoPago()`, con
  `parametroDeRetorno()` para la lectura escalar.
- `AssignRequestId::terminate()` suma `session_ref` si `$request->hasSession()`.
- `PlaceOrderAction` suma `precio_unitario_cents` al `map` de `lines`.
- El schema suma `checkout.returned` (enum, `allOf` y `x-events`), `session_ref` en `http.request`,
  `precio_unitario_cents` en `lines` y las descripciones de OBS-15. Se editó como JSON con
  `indent=2` y `ensure_ascii=False`, que reproduce el formato del archivo byte a byte.

**Decisiones de implementación que la spec dejaba abiertas**:
- **Tarea 1, los parámetros de retorno de MercadoPago**: verificados en parte contra la
  documentación oficial de Checkout Pro ("Configure return URLs",
  `mercadopago.com.ar/developers/en/docs/checkout-pro/configure-back-urls`, consultada el
  2026-10-12).
  - **Confirmado**: el retorno trae `collection_status`, `payment_id` y `external_reference`, entre
    otros (`collection_id`, `status`, `payment_type`, `merchant_order_id` y `preference_id`).
    `collection_status` toma, por ejemplo, `approved`, `pending` o `rejected`.
  - **No documentado**: que el cliente que vuelve sin pagar reciba esos valores como `"null"`
    literal. El ejemplo oficial muestra `"null"` solo en `merchant_account_id`. La normalización
    del `"null"` es defensiva y no cambia nada si MercadoPago no lo manda.
  - **Queda pendiente** verificar con un retorno real desde el sandbox, junto con la del webhook
    real (roadmap): tiene que dejar un `checkout.returned` con esos valores.
- **Tests de la misma sesión**: en la suite, cada request sin cookie abre una sesión nueva. La
  "misma sesión" se arma reenviando la cookie con `withCookie(config('session.cookie'), $id)`.
- **Tests de retención**: `configDeLoggingSin()` quita las variables de `$_ENV`, `$_SERVER` y
  `putenv`, evalúa `config/logging.php` y las restaura, así el test no depende del `.env` local.
- **Dos tests existentes cambiaron** por la enmienda de la regla 113, porque afirmaban el payload
  exacto de `lines`: `PlaceOrderTest > audit order.created con payload` y
  `EventosCatalogoTest > crear un pedido deja una sola entrada order.created`.

**Sincronías hechas** (tarea 8):
- observabilidad-01 (OBS-01, OBS-02 y OBS-04) y Spec 07.2 (regla 113);
- `docs/observabilidad/README.md`, `docs/arquitectura.md`, glosario, `.ai/rules/observabilidad.md`
  (ruta nueva, lectura escalar y `session_ref`);
- `.ai/rules/tests.md`: el centinela del CP, por el #43;
- roadmap y runbook del README.

**Pendiente fuera de la suite** (tarea 9, en la máquina del dueño, después del merge):
- borrar los `storage/logs/app-*.jsonl` viejos;
- volver a copiar el schema en `incident-investigator/contracts/revestimientos/`, con el commit del
  merge.

