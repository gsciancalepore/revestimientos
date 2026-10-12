# Spec — Observabilidad 02: ampliación del contrato de logs v1 para el investigador

- **Estado**: **borrador (2026-10-12)**, pendiente de `revisor-spec` y de la aprobación del dueño.
  Tiene tres decisiones del dueño abiertas (§Decisiones pendientes del dueño). Hasta que se
  aprueben, no se implementa nada.
- **Fuentes**:
  - Pedido del dueño del 2026-10-12: Revestimientos necesita las specs de los cambios que pide el
    investigador, escritas para que las implemente un agente sin contexto previo.
  - Los requisitos del investigador: `~/incident-investigator/docs/requisitos.md`, que definen el
    usuario (el desarrollador atendiendo el reclamo de un cliente), once casos de uso (CU1 a CU11)
    y su cobertura con el contrato v1.
  - [Spec `observabilidad-01-contrato-logs`](observabilidad-01-contrato-logs.md), cerrada, que esta
    spec amplía. Mantiene sus decisiones del dueño del 2026-09-24, en especial:
    - **sin datos personales**;
    - un evento entra al contrato **solo si tapa un agujero que hoy impide investigar un
      incidente**;
    - local primero, planes gratuitos.
  - [ADR-013](../adr/ADR-013-capa-observabilidad.md). Esta spec sigue en la **etapa 1** (local).
    La etapa 2 (staging) no cambia y sigue sin spec.
  - El relevamiento del código del 2026-10-12 (§Contexto).

## Por qué esta spec no usa la numeración global de reglas

Por el mismo motivo que observabilidad-01: no cambia ninguna regla de negocio, solo qué deja
escrito el sistema mientras hace lo que ya hacía. Continúa la numeración propia de esa spec, desde
**OBS-10**. No hay enmiendas a reglas de negocio. Las enmiendas son a OBS-02, de la spec 01
(§Enmiendas).

## Compatibilidad: todo sigue siendo v1

Cada cambio es un **evento nuevo** o un **atributo opcional nuevo**, o una aclaración de una
descripción que no cambia el significado. La regla de compatibilidad del contrato los declara
compatibles, así que `schema_version` sigue en `"1"`.

La excepción es el **lugar de entrega** (OBS-10), que no es parte del schema sino de OBS-02. Hoy no
hay ningún consumidor implementado: el investigador todavía no tiene código. Por eso se cambia
ahora y no después.

## Objetivo

Que los casos de uso del investigador que hoy quedan **parciales** con el contrato v1 se puedan
responder solo leyendo los logs, sin agregar datos personales. Y que el investigador pueda leer el
contrato **sin ver ningún otro archivo** de `storage/logs/`.

## Alcance

**Entra**:
- el directorio propio del contrato (OBS-10);
- la retención (OBS-11);
- el evento de retorno desde MercadoPago (OBS-12);
- la referencia seudónima de sesión en `http.request` (OBS-13);
- el precio unitario en las líneas de `order.created` (OBS-14);
- el conocimiento del dominio en las descripciones del schema (OBS-15).

**No entra**:
- el investigador;
- los logs de staging (etapa 2 de ADR-013);
- cualquier cambio de regla de negocio;
- `actor_id` en los espejos de auditoría y `release` en cada línea (§Alternativas descartadas).

## Contexto — qué hay hoy exactamente (2026-10-12)

**Cómo usa los logs el investigador.** El usuario es el desarrollador de la tienda atendiendo el
reclamo de un cliente. Traduce la identidad del cliente a un número de pedido con el panel de admin,
y el agente recibe solo identificadores (`order_id`, `payment_id`) y una ventana de tiempo. Con el
contrato v1 quedan **parciales** estos casos de uso:

| Caso del investigador | Agujero | Lo tapa |
|---|---|---|
| CU1 "Pagué y el pedido sigue pendiente" | Después de `checkout.payment_started`, "abandonó en MercadoPago" y "pagó pero el webhook se perdió" se ven igual. Es el límite declarado del README del contrato | OBS-12 |
| CU6 "No pude comprar / me dio error" | `checkout.rejected` no se puede atar a un visitante: no lleva `order_id`, y la sesión no aparece en ninguna línea | OBS-13 |
| CU8 "El precio que pagué no es el que vi" | Las `lines` de `order.created` traen `product_id` y `cantidad`, pero no el precio congelado | OBS-14 |
| Todos | El investigador tiene que poder montar el directorio de logs en solo lectura, y hoy ese directorio mezcla el contrato con `browser.log` (Boost) y con `laravel.log` (logger de emergencia), que no tienen la garantía de OBS-06 | OBS-10 |
| Reclamos de más de dos semanas | La retención es de 14 días (`LOG_DAILY_DAYS`) | OBS-11 |

**Código involucrado**:
- **`config/logging.php`**: el canal `app_file` (líneas 73–80) es un `daily` con
  `path => storage_path('logs/app.jsonl')` y `days => env('LOG_DAILY_DAYS', 14)`. `.env.example`
  no define `LOG_DAILY_DAYS`.
- **`storage/logs/.gitignore`**: ignora todo menos a sí mismo.
- **`app/Http/Middleware/AssignRequestId.php`**: genera el `request_id` y escribe `http.request`
  en `terminate()` con `method`, `route`, `status`, `duration_ms` y `user_id` (solo con usuario
  interno autenticado). Se registra con `prepend`, antes que la sesión (`bootstrap/app.php:23`).
  En `terminate()` la sesión del request ya está disponible.
- **`app/Http/Controllers/CheckoutController.php`**:
  - `store` guarda `session(['order_id' => $order->id])`.
  - `success` (líneas 127–147) lee `session('order_id')`. Si no hay pedido en sesión, redirige al
    carrito.
- **`app/Services/MercadoPagoGateway.php`**: `preferencePayload()` (desde la línea 122) manda las
  tres `back_urls` (`success`, `failure` y `pending`) a la misma ruta, `checkout.success`
  (`GET /checkout/exito`). `auto_return` se agrega solo si esa URL es pública
  (`backUrlEsPublica`), así que **en local el cliente vuelve solo si toca "Volver al sitio" en
  MercadoPago**.
- **Retorno de MercadoPago**: al volver, MercadoPago agrega parámetros al query string, entre ellos
  `collection_status`, `payment_id` y `external_reference`. **No vienen firmados**: los puede
  escribir cualquiera que arme la URL a mano. Los nombres exactos se **verifican contra la
  documentación vigente de MercadoPago al implementar** (tarea 1).
- **`app/Actions/PlaceOrderAction.php`** (líneas 156–163): la auditoría de `order.created` guarda
  `lines` con `product_id` y `cantidad`. El precio congelado ya está a mano en
  `$line['precio_unitario_cents']` (línea 150), y se guarda en `order_lines` pero no en la
  auditoría.
- **`docs/observabilidad/log-schema.v1.json`**:
  - Los ítems de `lines` en `order.created` ya tienen `additionalProperties: true`.
  - `http.request` tiene `additionalProperties: false`.
- **`app/Logging/EventLog.php`**: `CLAVES_EXTERNAS` (`payment_id`, `tipo`, `mp_request_id`,
  `external_reference` y `status`) se truncan a 64 caracteres.
- **Sesión**: `SESSION_DRIVER=redis` y `SESSION_LIFETIME=120`. El cliente es anónimo (regla 27),
  y el carrito vive en sesión, sin tabla (regla 81, Spec 05). El id de sesión solo se regenera en el
  login de usuarios internos (`AuthenticatedSessionController:29`).
- **Tests**: `tests/Pest.php` tiene `canalDeContrato()` (construye el canal real con la ruta en un
  directorio temporal) y `lineasDelContrato()` (lee `app-*.jsonl` de ese directorio y valida cada
  línea contra el schema). Los tests del contrato están en `tests/Feature/Observabilidad/`.
- **CI** (`.github/workflows/ci.yml`, líneas 72–90): compara los hashes de **todo**
  `storage/logs/`, recursivo, antes y después de la suite.

## Reglas

**OBS-10 — Directorio propio del contrato.**
- El canal `app_file` escribe en `storage/logs/contrato/app-AAAA-MM-DD.jsonl`.
- En ese directorio no hay **ningún** otro archivo que escriba la aplicación, el framework o un
  paquete. La única excepción es su `.gitignore`.
- El directorio existe en un clon limpio: se versiona con un `.gitignore` propio, que ignora todo
  menos a sí mismo. Así nadie lo crea por accidente con otro dueño, por ejemplo Docker como `root` al
  montarlo.
- El consumidor recibe la ruta de **ese** directorio y lee `app-*.jsonl`.
- `storage/logs/` sigue alojando `browser.log` y, si aparece, `laravel.log`. Ninguno es parte del
  contrato.

**OBS-11 — Retención.** El canal `app_file` conserva **90 días** por defecto. Sigue siendo
configurable con `LOG_DAILY_DAYS`, que pasa a figurar en `.env.example`. *(Sujeto a la decisión D2.)*

**OBS-12 — `checkout.returned`: el cliente volvió desde MercadoPago.**
- **Cuándo**: en `GET /checkout/exito` (`success`), cuando el request trae el parámetro
  `collection_status`. Se emite **antes** de cualquier redirect, incluido el que manda al carrito si
  la sesión no tiene pedido. Sin ese parámetro, por ejemplo al volver de una transferencia o al
  recargar la página sin query string, no se emite.
- **Nivel**: `info`.
- **`attributes`**:

  | Clave | Tipo | Origen | Nota |
  |---|---|---|---|
  | `order_id` | entero o `null` | `session('order_id')` | `null` si el cliente volvió en otro navegador o se le venció la sesión |
  | `status` | string o `null` | query `collection_status` | Valor externo, truncado a 64 |
  | `payment_id` | string o `null` | query `payment_id` | Valor externo, truncado a 64. Los valores ausentes o `"null"` se escriben como `null` |
  | `external_reference` | string o `null` | query `external_reference` | Valor externo, truncado a 64 |

- **Significado, que va en la descripción del schema**: el **navegador** del cliente volvió desde
  MercadoPago y dijo traer ese estado. **No confirma ningún pago**: los parámetros no vienen
  firmados. La confirmación es `order.paid`, que llega por el webhook después de consultar la API
  (Spec 08).
  - Un `checkout.returned` con `status` aprobado y sin `order.paid` indica un webhook demorado o
    perdido.
  - La ausencia de `checkout.returned` **no prueba un abandono**: en local no hay `auto_return`, y
    el cliente puede cerrar la pestaña después de pagar.
- **El README del contrato** reemplaza el límite "abandonó en MercadoPago y el webhook nunca llegó
  se ven igual" por este texto, más acotado: si el cliente volvió, se distingue; si no volvió, siguen
  viéndose igual.

**OBS-13 — `session_ref` en `http.request`.** *(Sujeto a la decisión D1.)*
- **Qué es**: los primeros 16 caracteres hexadecimales de
  `hash_hmac('sha256', $sessionId, config('app.key'))`.
- **Dónde**: en `http.request` como atributo opcional, **solo si el request tiene sesión
  iniciada**. Va únicamente en `http.request`: los demás eventos del mismo request se atan por
  `request_id`.
- **Prohibido**: el id de sesión crudo no aparece nunca en ninguna línea. Es una credencial (la
  cookie de sesión).
- **Significado, que va en la descripción del schema**:
  - Agrupa los requests de un **mismo navegador** mientras dure su sesión (120 minutos de
    inactividad).
  - No identifica a una persona, y no sirve para buscar a un cliente.
  - Cambia si el usuario interno inicia sesión, si la sesión vence, o si cambia `APP_KEY`.
- **Para qué la usa el investigador**: a partir del `order.created` de un pedido, salta por
  `request_id` a su `http.request`, toma el `session_ref`, y encuentra los intentos previos del
  mismo navegador, incluidos los `checkout.rejected`.
  - Si el cliente **nunca** llegó a crear un pedido, `session_ref` no ayuda a encontrarlo: el caso
    queda en ventana de tiempo, y así lo tiene que declarar el investigador.

**OBS-14 — Precio unitario en las líneas de `order.created`.**
- Cada ítem de `lines` suma `precio_unitario_cents` (entero), que es el precio congelado de esa
  línea al crear el pedido (reglas 102 y 111). En modo `M2` es el precio por **caja**, igual que en
  `order_lines`.
- Se agrega en el payload de auditoría de `PlaceOrderAction`, así que llega a `audit_logs` y, por el
  espejo, al log.
- En el schema se declara como propiedad opcional de cada ítem.

**OBS-15 — El conocimiento del dominio vive en las descripciones del schema.** El investigador no
lee la documentación de la tienda, solo el schema (ADR-013 y ADR-004 del investigador). Estas
descripciones de `x-events` se completan, citando la regla o ADR de origen. Ninguna cambia el
significado del evento; explicitan comportamiento que ya existe:
- **`order.status_changed`**:
  - Una transición a `cancelled` la hace siempre un admin (regla 165): no hay cancelación por parte
    del cliente ni cancelaciones automáticas.
  - Un pedido en `pending_payment` **no vence**: no hay scheduler en ningún entorno (ADR-012,
    descartada). Sigue así hasta que llega el pago o un admin lo cancela.
- **`order.created`**:
  - El stock se descuenta al confirmarse el pago, no al crear el pedido (ADR-005).
  - `lines[].precio_unitario_cents` es el precio congelado (OBS-14).
- **`order.stock_restored`**: la devolución del dinero de un pedido pagado y cancelado se hace
  fuera del sistema.
- **`checkout.payment_started`**: el límite se reescribe según OBS-12.

## Enmiendas a la spec observabilidad-01 (cerrada)

- **OBS-02**:
  - La ruta pasa de `storage/logs/app-AAAA-MM-DD.jsonl` a `storage/logs/contrato/app-AAAA-MM-DD.jsonl`
    (OBS-10).
  - La retención pasa de 14 a 90 días (OBS-11, sujeto a D2).
  - Se anota como sincronía fechada en esa spec, sin reescribir su texto.
- **README del contrato** (`docs/observabilidad/README.md`, §Cómo se entrega y §Límites): se
  actualiza la ruta, la retención y el límite de OBS-12.

## Matriz de permisos

No cambia ningún permiso de la aplicación.

| Actor | Lee `storage/logs/contrato/` | Ve `session_ref` | Ve `precio_unitario_cents` en la auditoría |
|---|---|---|---|
| Cliente anónimo | no | no | no |
| Admin | no | no | sí: ya ve la auditoría del detalle del pedido (Spec 08) |
| Vendedor, depósito | no | no | como hoy: la auditoría del detalle es solo para admin |
| Dueño, en su máquina | sí | sí | sí |
| `incident-investigator` (externo) | sí, solo ese directorio y en solo lectura | sí | sí, por el espejo |

## Casos borde

- **Recarga de `/checkout/exito` con el query string de MercadoPago**: emite otro
  `checkout.returned`. Se acepta: cada línea es una navegación real, y el investigador los agrupa
  por `order_id`.
- **Retorno en otro navegador o con la sesión vencida**: `order_id` es `null`. El investigador
  correlaciona por `external_reference`, que es el id del pedido según la regla 123, pero lo trata
  como valor no confiable.
- **`collection_status` presente con un valor inventado** (URL armada a mano): se escribe truncado.
  La descripción del schema ya dice que no confirma nada.
- **`payment_id=null` literal en el query** (MercadoPago lo manda así cuando no hubo pago): se
  escribe `null`.
- **Retorno de una transferencia**: no trae `collection_status`, así que no emite.
- **Request sin sesión** (`/up`, por ejemplo): `http.request` sin `session_ref`.
- **Webhook de MercadoPago**: pasa por el grupo `web` y puede tener una sesión nueva en cada
  notificación. Su `session_ref` no agrupa nada útil. Se acepta para no agregar un caso especial.
- **Login de un usuario interno**: el id de sesión se regenera y `session_ref` cambia. No afecta a
  los clientes, que son anónimos.
- **Rotación de `APP_KEY`**: cambian todos los `session_ref` desde ese momento. Es aceptado y queda
  declarado en la descripción.
- **Líneas viejas sin `precio_unitario_cents`**: los pedidos creados antes de esta spec no lo
  tienen. El atributo es opcional y el investigador tolera su ausencia.
- **Archivos anteriores en `storage/logs/app-*.jsonl`**: no se mueven ni se migran. Siguen
  existiendo hasta que el dueño los borre (tarea 8).

## Criterios de aceptación

1. **Directorio propio** (OBS-10):
   - con el canal `app` construido desde `config/logging.php` real, la ruta configurada de
     `app_file` está dentro de `storage/logs/contrato/`;
   - `storage/logs/contrato/.gitignore` existe en el repositorio;
   - el paso de CI de OBS-09 sigue en verde (cubre `storage/logs/` de forma recursiva).
2. **Retención** (OBS-11): sin `LOG_DAILY_DAYS` en el entorno, `config('logging.channels.app_file.days')`
   vale 90, y `.env.example` declara la variable.
3. **`checkout.returned`** (OBS-12):
   - un `GET /checkout/exito?collection_status=approved&payment_id=123&external_reference=7`, con
     `order_id` 7 en sesión, deja una sola línea `checkout.returned` con esos valores, y valida
     contra el schema;
   - el mismo request sin pedido en sesión deja la línea con `order_id: null` y redirige al
     carrito;
   - sin `collection_status`, no hay `checkout.returned`;
   - con valores de 300 caracteres, los tres quedan truncados a 64;
   - con `payment_id=null`, el atributo es `null`.
4. **`session_ref`** (OBS-13):
   - dos requests de la misma sesión tienen el mismo `session_ref`, de 16 caracteres hexadecimales;
   - dos sesiones distintas tienen `session_ref` distintos;
   - el id de sesión crudo no aparece en ninguna línea del archivo (búsqueda textual);
   - una línea de un request sin sesión no tiene `session_ref`;
   - todas las líneas validan contra el schema.
5. **Precio unitario** (OBS-14): crear un pedido de dos líneas deja `order.created` con
   `precio_unitario_cents` en cada ítem, igual al de `order_lines`, en el log y en `audit_logs`.
6. **Descripciones** (OBS-15): un test verifica que las descripciones de `order.status_changed`,
   `order.created`, `order.stock_restored`, `checkout.payment_started` y `checkout.returned`
   mencionan la regla o la ADR que citan (165, ADR-012, ADR-005 y OBS-12).
7. **Calidad**: Pint, PHPStan nivel 8 y la suite en verde. Cada regla OBS-10 a OBS-14 verificada
   **mutando la implementación**, con algún test en rojo por mutación.

## Tareas técnicas

1. **Verificar los parámetros de retorno** de MercadoPago (`collection_status`, `payment_id` y
   `external_reference`) contra la documentación vigente de Checkout Pro. Si difieren, se detiene la
   implementación y se pregunta al dueño (`AGENTS.md`, §Ambigüedad).
2. **Schema** (`docs/observabilidad/log-schema.v1.json`):
   - `checkout.returned` en el `enum` de `event`, con su bloque en `allOf` y su descripción en
     `x-events`;
   - `session_ref` opcional en `http.request`, con `pattern: ^[0-9a-f]{16}$`;
   - `precio_unitario_cents` opcional en los ítems de `lines` de `order.created`;
   - las descripciones de OBS-15;
   - `maxLength: 64` en los valores externos nuevos.
3. **Canal**: `config/logging.php` (ruta de `app_file` y `days` por defecto en 90),
   `storage/logs/contrato/.gitignore` y `LOG_DAILY_DAYS=90` en `.env.example`.
4. **`checkout.returned`**: en `CheckoutController::success`, antes del primer `return`. Los tres
   valores del query pasan por `EventLog`, que ya trunca `payment_id`, `status` y
   `external_reference`.
5. **`session_ref`**: en `AssignRequestId::terminate()`, con `$request->hasSession()`.
6. **Precio unitario**: el `map` de `lines` en `PlaceOrderAction::execute`.
7. **Tests** en `tests/Feature/Observabilidad/`, con `canalDeContrato()` y `lineasDelContrato()`.
8. **Sincronías**, en commit `docs:` propio:
   - observabilidad-01 (§Sincronía, enmienda de OBS-02);
   - `docs/observabilidad/README.md`;
   - `docs/arquitectura.md` §Observabilidad;
   - `.ai/rules/observabilidad.md`, por la ruta nueva;
   - glosario: `session_ref` y "retorno desde MercadoPago" como términos técnicos;
   - roadmap y runbook del README, con dos pasos a mano del dueño: borrar los
     `storage/logs/app-*.jsonl` viejos y avisar al investigador para que vuelva a copiar el schema.

## Decisiones pendientes del dueño

- **D1 — ¿`session_ref` es aceptable bajo "sin datos personales"?**
  - **A favor**: no es ninguno de los datos que lista OBS-06; el investigador no puede revertirlo a
    la cookie (necesitaría `APP_KEY`); y vence con la sesión.
  - **En contra**: agrupa la navegación de un visitante anónimo, que es un dato de comportamiento.
  - **Recomendación**: aceptarlo, porque es la única forma de cerrar CU6 sin pedir datos al cliente.
    Si se rechaza, OBS-13 sale de la spec y CU6 queda declarado como parcial.
- **D2 — Retención de 90 días.**
  - Son archivos chicos y solo locales.
  - El número sale de que un reclamo puede llegar semanas después de la compra. Cualquier valor
    mayor a 14 cumple el objetivo.
- **D3 — Nombre del directorio**: `contrato/`, en español, como el resto del repo.

## Alternativas descartadas

- **`actor_id` en los espejos de auditoría**: `audit_logs` ya lo guarda, y por la regla 165 se sabe
  que toda cancelación es de un admin. Saber **cuál** admin no cambia la respuesta a ningún reclamo
  de cliente. No tapa un agujero: no entra.
- **`release` (versión desplegada) en cada línea**: en local no hay despliegues. Se reevalúa en la
  etapa 2 de ADR-013.
- **`session_ref` en todos los eventos**: es redundante, porque `request_id` ya ata cada evento a
  su `http.request`. Ponerlo solo ahí sigue el patrón del resumen por request (OBS-04).
- **Leer `storage/logs/` entero y filtrar `app-*.jsonl` del lado del investigador**: la frontera de
  datos quedaría en el código del consumidor y no en el sistema de archivos, el mismo defecto que
  ADR-013 descartó para el agente dentro del repo (hallazgo B2).

## Nota de handoff para el agente implementador

Para empezar, leé en este orden:
1. `AGENTS.md`;
2. `PROJECT_PRINCIPLES.md`;
3. esta spec;
4. la spec observabilidad-01 entera (las reglas OBS-01 a OBS-09 siguen vigentes);
5. `docs/observabilidad/README.md`;
6. `.ai/rules/observabilidad.md`;
7. `grep -rin 'log\|checkout\|sesión' .ai/rules`.

Reglas:
- **Rama** `feat/observabilidad-02-ampliacion-contrato`, desde `main` actualizado. TDD, una regla
  por vez: test en rojo → implementación → refactor.
- **Orden sugerido**: OBS-10 y OBS-11 (configuración) → OBS-14 (un `map`) → OBS-12 → OBS-13 →
  OBS-15 (schema y descripciones).
- **Todo corre en Docker**: `make format` → `make lint` → `make stan` → `make test`. Nunca dos
  suites en paralelo.
- **No agregar dependencias.** `hash_hmac` es nativo de PHP.
- **Antes del push**, pasar `revisor-entrega` (`.claude/agents/`): su chequeo de mutaciones es parte
  del criterio 7.
- **Commits** en español, con Conventional Commits y **sin trailers de atribución a herramientas de
  IA**. Las sincronías de documentos van en un commit `docs:` aparte.
