# Roadmap

Última actualización: 2026-09-12 (**Spec 08 completa**: las tres fases implementadas — dominio, webhook y panel — con 436 tests; queda solo la verificación del webhook contra MercadoPago real, con túnel y credencial. **Alcance del MVP cerrado**: las ventas manuales por WhatsApp quedan **fuera del MVP** por decisión del dueño, con la visión enmendada y el texto original conservado; lo único que falta para el MVP completo es verificar el webhook contra MercadoPago real (la fase 08.c quedó implementada el 2026-09-12). **Spec 08 fase 08.b implementada**: webhook de MercadoPago autenticado por firma HMAC con comparación en tiempo constante, filtro por tipo antes de consultar, estado real consultado contra la API por el puerto `PaymentStatusQuery` —`PaymentClient` es `final` y no se puede mockear, así que la costura no pudo ser la que la regla 155 describía—, verificación de monto contra `total_cents` y 503 deliberado ante fallo transitorio para que MercadoPago reintente; 378 tests, cada regla verificada mutando la implementación. `revisor-entrega` bloqueó la primera versión: el adaptador real del puerto no lo ejercitaba ningún test y se podía vaciar entero con la suite en verde, y la rama de "pago desconocido" era inalcanzable porque el SDK lanza excepción también en el 404. Se agregó además un cerrojo que impide que cualquier test salga a la red. **Spec 08 fase 08.a implementada**: máquina de estados, descuento de stock al confirmarse el pago con relectura del pedido bajo lock, restitución al cancelar, stock negativo auditado como reposición pendiente; `PlaceOrderAction` alineado con el bloqueo ordenado que pedía la regla 143; 340 tests. **Spec Higiene 02 cerrada**: HIG-04 la auditoría de precio y stock guardaba el valor nuevo como anterior —`getOriginal()` leído después del `save()`—; HIG-06 `PlaceOrderAction` no validaba los datos del cliente que la regla 108 exige, se cumplía por accidente vía `StoreCheckoutRequest`; HIG-07 la revalidación bajo `lockForUpdate` no tenía cobertura y se podía borrar con la suite en verde; HIG-08 guard del reintento MP sobre pedido pagado; HIG-09 la regla 117 enmendada a `find` + redirect. Specs 07.2 y 07.3 enmendadas con su sincronía; 274 tests. Spec 07.4 **verificada de punta a punta contra la API real de MercadoPago** por primera vez: hasta ahora solo estaba probada con gateway fake, y la verificación destapó dos defectos que enmiendan la regla 123 —`auto_return` condicionado a back_url pública y costo de envío en `shipments.cost`—, más credenciales externas neutralizadas en `phpunit.xml` y el botón "Finalizar compra" que faltaba en el carrito; 261 tests. Spec 06 fase 2 cerrada: importador administrativo de tarifas por CP, 253 tests en verde, Pint/PHPStan alineados; Spec 07 cerrada: 07.1/07.2/07.3/07.4 implementadas y mergeadas a `main` — 07.4 `cb9fd2b`/PR #8 — 205 tests; Staging: `docs/deployment/staging.md` operativo `~0.3-0.7s`, `Render Oregon + Neon Oregon PG18 (18.6, us-west-2)` co-localizado, `Neon` 14 migraciones + seed `users=1`/`roles=3`/`categories=4`/`products=1` + `shipping_rates` + `orders`/`order_lines`, `RoadRunner 2w`, fixes `cb1002b`/`e56e62c`/`73d2945`/`bbfd1fd` TrustProxies + seed vacío §15.2/15.3 + latencia Oregon §15.4/ADR-010, deploy `https://revestimientos.onrender.com` operativo; `docker-compose.yml` se mantiene en `postgres:17` — bump a 18 se evalúa aparte; Spec 06 Envío cerrada 158 tests).

## Definition of Done (aplica a TODAS las fases y specs)

Una fase/spec se considera terminada **solo** cuando cumple todo:

- [ ] Spec aprobada (y documentada en `docs/specs/`)
- [ ] Tests en verde (Pest: feature + unit según corresponda)
- [ ] PHPStan limpio (nivel 8, sin errores)
- [ ] Pint limpio (sin cambios pendientes)
- [ ] ADR actualizadas (si la fase introduce una decisión arquitectónica)
- [ ] `arquitectura.md` actualizada
- [ ] `roadmap.md` actualizado
- [ ] Sin TODOs
- [ ] Sin código comentado
- [ ] Sin warnings
- [ ] Merge a `main` vía Pull Request ( `main` es protegida y despliega a staging; no se hace push directo)

## Estado de fases

| Fase | Descripción | Estado |
|---|---|---|
| -2 | Constitución: `PROJECT_PRINCIPLES.md` | ✅ |
| -1 | Visión y lenguaje: `docs/vision.md`, `docs/ubiquitous-language.md` | ✅ (visión en revisión del dueño) |
| 0 | Dominio y arquitectura: spec 00, `arquitectura.md`, ADR-001..006 | ✅ |
| 1 | Fundación técnica: Docker, Laravel 12, calidad, CI | ✅ |
| 1b | Calidad de onboarding: spec `calidad-onboarding` (runbook, Makefile, README) | ✅ (2026-08-05) |
| 1c | Calidad de análisis estático: spec `calidad-analisis-estatico` (PHPStan↔Pest 3.8, gates) | ✅ (2026-08-05): PHPStan app-only, stubs eliminados, 52 tests en verde |
| 2 | Specs 01..09 (funcionales, TDD) | ⏳ siguiente |

## Fase 2 — Entregables funcionales

Cada spec se implementa en orden; cada una depende de la anterior
(autenticación → datos → catálogo → venta → operación).

| # | Spec | Contenido | Dominio | Estado |
|---|---|---|---|---|
| 01 | Autenticación y roles | Login admin (Breeze en `/admin`), usuarios internos, roles admin/vendedor/depósito (Spatie), Policies, auditoría de usuarios/roles | Users | ✅ cerrada (2026-08-05): 52 tests en verde, Pint/PHPStan alineados, CI verde |
| 02 | Panel + Categorías | Layout admin con sidebar, CRUD de categorías | Products | ✅ cerrada (2026-08-05): 79 tests en verde, Pint/PHPStan alineados. **Revisada (2026-08-05): categorías planas** (Porcelanatos, Cerámicas, Pastinas, Adhesivos; sin jerarquía) para el modelo de Spec 03 |
| 03 | Productos | Dos modos de venta (m² y unidad), atributos híbridos (columnas tipadas + `specs` JSONB por familia), código único, precios, ofertas, stock, imágenes | Products | ✅ cerrada (2026-08-05): 93 tests en verde, Pint/PHPStan alineados. **Nota (2026-09-06, sincronía SDD)**: la regla 67, diferida en su momento a "cuando exista la tabla `orders`", quedó **activada** con alcance `unidad_venta` + borrado de productos con pedidos (`Product::tienePedidos()`, `DomainException` en `UpdateProductAction`/`DeleteProductAction`). No reabre la spec |
| 04 | Catálogo público | Home, categorías, filtros, ficha con calculadora m²→cajas (modo m²), stock visible, ofertas | Products | ✅ cerrada (2026-08-06): 116 tests en verde, Pint/PHPStan alineados |
| 05 | Carrito | Carrito anónimo en sesión, líneas por producto, derivación m²→cajas con `M2Calculator` y 10 % desperdicio antes de `ceil`, validación `cantidad ≤ stock` e `activo`, acumulación/actualización/eliminar/vaciar, `subtotal` sí / `total` no, condición derivada no comprable (sin estado) | Orders | ✅ cerrada (2026-09-03): 135 tests en verde, Pint/PHPStan alineados |
| 06 | Envío | Tarifa única por CP exacto 4 dígitos, `ShippingCalculator` + `ManualShippingCalculator` con `shipping_rates` (CHECK ≥0, único parcial activo), cotización `disponible`/no disponible sin excepción, CRUD admin y `total = subtotal + shipping` en carrito | Orders | ✅ cerrada (2026-09-03): 158 tests en verde, Pint/PHPStan alineados |
| 06.2 | Envío — Importador de tarifas | Importación administrativa del CSV snapshot `codigo_postal,precio_envio`: validación total previa (422 sin persistir), temporal server-side + token con manifiesto en caché, preview con conteos, aplicación fila-por-fila en una única transacción, idempotente y sin borrados físicos | Orders | ✅ cerrada (2026-09-06): 253 tests en verde, Pint/PHPStan alineados |
| 07 | Checkout | Compra anónima, MercadoPago, transferencia con confirmación manual, creación del pedido | Orders + Payments | ✅ cerrada (2026-09-03): 07.1 estructura `orders`/`order_lines` + `OrderStatus` + `PaymentGateway`; 07.2 `PlaceOrderAction` (`Cart` + `lockForUpdate` + `bcmath` + `audit`); 07.3 HTTP `GET /checkout`, `POST /checkout`, `GET /checkout/exito` con `StoreCheckoutRequest` + `CheckoutController` delgado + `session order_id` (sin `{order}`), `shipping !disponible → 0` permitido, 12 tests Checkout, 14 migraciones, **196 tests**; ✅ 07.4 MercadoPago (2026-09-04; verificada contra la API real el 2026-09-10): `MercadoPagoGateway` (SDK `dx-php` pineado, `Preference` + `redirect away init_point`) + `mp_preference_id/mp_init_point` + `POST /checkout/mercadopago/reintentar` + `success` solo lectura (botón continuar/reintentar), 9 tests MP, **205 tests** |
| H02 | Higiene 02 — auditoría, validación y cobertura | Valor anterior real en la auditoría de precio y stock, validaciones de `PlaceOrderAction` que la regla 108 exige, cobertura real de la revalidación bajo lock, guard del reintento MP | Products + Orders | ✅ cerrada (2026-09-10): reglas HIG-04–HIG-09 implementadas, **274 tests** en verde, Pint/PHPStan alineados. Specs 07.2 y 07.3 enmendadas con su sincronía. Mergeada a `main` (PR #15) |
| 08 | Gestión de pedidos | Máquina de estados, `ConfirmPaymentAction` con descuento de stock (ADR-005), restitución al cancelar un pedido pagado, webhook de MercadoPago con validación de firma y verificación de monto, panel de pedidos y vista depósito | Orders | ✅ **implementada** (2026-09-12) — aprobada por el dueño (2026-09-10), reglas 143–166, entrega **en 3 fases**. ✅ **08.a dominio** (rama `feat/pedidos-08a`): `OrderStatus` con la máquina de estados, `TransitionOrderStatusAction`, `ConfirmPaymentAction` con descuento bajo lock e idempotencia, `CancelOrderAction` con restitución — **340 tests**, Pint/PHPStan alineados. ✅ **08.b webhook** (rama `feat/pedidos-08b`): `POST /webhook/mercadopago` con firma HMAC, filtro por tipo, consulta a la API por el puerto `PaymentStatusQuery`, verificación de monto y 503 deliberado ante fallo transitorio — **378 tests**, quince mutaciones verificadas y dos pasadas de `revisor-entrega`, la primera bloqueante. Falta la prueba contra MercadoPago real (túnel + `MERCADOPAGO_WEBHOOK_SECRET`). ✅ **08.c panel y despacho** (rama `feat/pedidos-08c`): panel con filtros y destacados derivados, detalle con auditoría solo para admin, confirmación manual restringida a transferencias **en la Action**, vista depósito con dos solapas sin importes, cancelaciones y regla 146 del stock negativo — **436 tests**. Auditada dos veces por `revisor-entrega`: la primera **bloqueó** la entrega por dos falsos verdes de la regla 146 —`assertSee('-3')` sobre la página entera del panel no podía fallar nunca, y la grilla del catálogo no tenía cobertura—, más un tercero que apareció al corregirlos: `paginate()` devuelve las filas ordenadas por id aunque se borre el `ORDER BY`. Queda **solo** la verificación del webhook contra MercadoPago real. Ventas WhatsApp fuera del MVP (2026-09-12) |
| 08.2 | Ventas manuales por WhatsApp | Alta de pedido desde el panel, opcionalmente con link de pago de MercadoPago | Orders | **fuera del MVP** (decisión del dueño, 2026-09-12; diferida antes, el 2026-09-10). Post-MVP: sigue siendo deseable y su enmienda está anotada en `docs/vision.md` §MVP punto 6. Hasta entonces el stock de una venta por WhatsApp se ajusta **a mano** desde el panel de productos, auditado por la regla 68 |
| 09 | Descuentos (opcional) | Por forma de pago y por monto de compra | Orders | pendiente |

## Fase 3 — Post-MVP (candidatas, sin compromiso)

- Extract Stock: movimientos y ajustes con auditoría (crea el dominio Inventory)
- Extract Customers: historial de compras por email/CP
- Tarifas de envío más ricas o API de cotización (implementación real del puerto)
- Compras a proveedores (nuevo dominio Suppliers)

## Notas de decisión

- El orden de la Fase 2 es deliberado: **el admin va antes que el catálogo público**
  para que la carga de datos sea la misma que en producción (sin datos demo
  artificiales ni seeders temporales).
- La Spec 01 introduce la **auditoría** (ADR-004) para usuarios y roles; el resto
  de las acciones críticas (precios, stock, pagos) se auditan en sus specs.
- La Spec 01 usa **Breeze 2.4.2 pineado** y conserva **Tailwind 4** (se restauró
  tras el instalador de Breeze, que lo baja a v3; ver ADR-007).

## Cómo continuar

### Punto de retome — cierre del 2026-09-11 (segunda jornada)

Estado exacto al terminar, para que cualquiera (persona o agente) retome sin reconstruir contexto.
**Leer esto primero.**

#### Qué hay en `main`

`main` está en **`0584dae`**, con **378 tests**, PHPStan nivel 8 sin errores y Pint limpio
(reverificado al cerrar). Contiene la Spec 08 **hasta la fase 08.b inclusive**:

- **08.a — dominio** (PR #17): reglas 143-145, 147-152 y 166. Máquina de estados en `OrderStatus`,
  `TransitionOrderStatusAction` como único camino a `order.status`, `ConfirmPaymentAction` con
  descuento de stock bajo lock e idempotencia, `CancelOrderAction` con restitución.
- **08.b — webhook** (PR #19): reglas 153-158. `POST /webhook/mercadopago` sin auth ni sesión y
  excluida de CSRF, autenticada por firma HMAC con `hash_equals`; filtro por tipo antes de
  consultar; el estado real se consulta contra la API por el puerto `PaymentStatusQuery`;
  verificación de monto contra `total_cents`; 503 deliberado ante fallo transitorio.
- **Enmienda de proceso** (PR #18): qué se puede tocar de `docs/specs/`, `docs/adr/` y
  `docs/roadmap.md`, y qué no. Ver `AGENTS.md` §Idioma y proceso.

**Nota de historia (2026-09-11)**: los PR #18 y #19 se mergearon a mano en orden inverso al
previsto (#19 antes que #18). No se perdió contenido —ambos tocaban hunks distintos— y el único
efecto fue que este punto de retome quedó un rato diciendo "340 tests" mientras el encabezado ya
decía 378. Se anota en vez de borrarse, porque explica esa incoherencia si alguien mira el
historial.

#### Lo próximo: fase 08.c

**No hace falta escribir spec**: la Spec 08 ya está aprobada y la 08.c son las reglas **146 y
159-165**, con criterios de aceptación y matriz de permisos ya redactados. Alcance: panel de
pedidos con filtros y destacados, detalle, confirmación manual de transferencia, vista depósito con
sus dos solapas, cancelaciones y visibilidad del stock negativo.

**Tres cosas que 08.a y 08.b dejaron anotadas para esta fase, y que conviene leer antes de
empezar**:

1. **La restricción de la regla 159 no está en `ConfirmPaymentAction`.** La confirmación manual vale
   solo para pedidos de `transferencia`, pero la spec ubica ese control en el panel. Hoy
   `execute($orderDeMercadoPago, 'manual')` marca pagado y descuenta stock sin que nadie haya
   cobrado. Si el control queda solo en el controlador o en la Policy, se repite exactamente el
   patrón que HIG-06 tuvo que corregir en `PlaceOrderAction`.
2. **Qué auditorías destacan un pedido y cuáles no.** La regla 161 deriva los destacados de
   `audit_logs`. Los incidentes de pago son `order.payment_amount_mismatch` y
   `order.paid_after_cancel`. **No** lo son `order.stock_restored` (08.a) ni las cuatro del webhook
   —`webhook.signature_invalid`, `webhook.ignored`, `webhook.order_not_found`,
   `webhook.payment_not_found`—, tres de las cuales ni siquiera tienen pedido asociado.
3. **La enmienda de la regla 146**: los Form Requests de la Spec 03 validan `min:0` en stock, así
   que con stock negativo el admin no puede guardar **ningún** cambio del producto. Hay que
   enmendarlo y anotarlo como sincronía.

#### Pendiente que ningún test puede cubrir: verificar el webhook de verdad

El webhook **está probado con dobles, no contra MercadoPago**. Los tests no alcanzan la red por
diseño y ahora hay un cerrojo que lo impide, así que —como enseñó la 07.4, donde seis días de CI
verde convivieron con MercadoPago cobrando el subtotal— eso **no** es lo mismo que estar verificado.

Para hacerlo hace falta, en este orden: levantar el túnel, generar la credencial nueva
`MERCADOPAGO_WEBHOOK_SECRET` en el panel de MercadoPago (es distinta del access token) y configurar
ahí la URL `https://<subdominio>.trycloudflare.com/webhook/mercadopago`. El procedimiento completo
está en `docs/deployment/desarrollo-local.md` §Webhook.

**Cuándo (decisión del dueño, 2026-09-12): al terminar la Spec 08 completa, no ahora.** El túnel se
levanta una sola vez y se verifica toda la spec junta, con el panel de 08.c ya disponible para ver
el resultado —pedido en `paid`, stock descontado, destacado de reposición pendiente— en lugar de
mirar la base a mano.

#### Cómo se auditó, y por qué importa para la próxima fase

Las dos fases pasaron por `revisor-entrega`, y **las dos fueron bloqueadas en la primera pasada**:

- **08.a**: dos reglas del corazón de la fase estaban bien implementadas pero **sin un solo test que
  las protegiera** —se podían borrar enteras con los 327 tests en verde—, y en la cancelación ese
  agujero perdía stock.
- **08.b**: `MercadoPagoGateway::findPayment()` —el único adaptador del puerto, donde vive la
  conversión pesos→centavos contra la que compara la regla 157— se podía **vaciar entero** con los
  357 tests en verde. Si esa conversión se rompe, **ningún pago se confirma nunca**: todos caen en
  `order.payment_amount_mismatch` y los pedidos quedan con la plata cobrada en `PendingPayment`.
  La lección nueva: **probar el puerto no prueba el adaptador**.

La lección de fondo, que ya es la cuarta vez que aparece (regla 123, regla 109, regla 150 y ahora
155): **los gates en verde no dicen nada sobre si el test cubre la regla**. Correr `revisor-entrega`
antes de cada push no es opcional, y la forma de usarlo es pedirle que **mute la implementación**.

**Hallazgo colateral de 08.b, que vale para todo el repo**: la suite **salía a la red de verdad**.
Neutralizar credenciales en `phpunit.xml` nunca lo impidió —con cualquier token el SDK sale igual a
internet y falla recién del otro lado—. Ahora `Tests\RedProhibida`, instalado desde `tests/Pest.php`
sobre `Feature` y `Unit`, corta cualquier salida con un mensaje que dice qué doble falta. Hay un
test en cada suite que lo protege. **No lo quites para "probar de verdad"**: la verificación contra
MercadoPago es manual y con túnel.

#### Punto abierto resuelto (2026-09-11): las specs editadas desde la 08.a

Quedaba por decidir si se ratificaban o se revertían los dos commits de `feat/pedidos-08a` que
editan `docs/specs/08-gestion-pedidos.md`. **Resuelto: se ratifican, y se enmienda `AGENTS.md`**
(PR #18).

La revisión mostró que el problema no era la 08.a sino la regla: `AGENTS.md` decía que
`docs/specs/` *jamás* se edita, mientras el bullet inmediatamente siguiente manda anotar la
sincronía **en la spec**. Cumplir uno obligaba a violar el otro. Y la práctica del repo nunca fue
la escrita: `ADR-005` lleva su enmienda anotada en el propio documento, y ocho commits de ramas de
implementación editaron specs, todos mergeados vía PR.

La enmienda separa lo que la regla protege (el contrato: reglas, criterios de aceptación, matriz de
permisos, alcance) de lo que es registro de avance (Estado, checkboxes, sincronía append-only), y
fija la forma: van en un commit `docs:` propio, **nunca dentro de uno `feat:`**.

#### Decisión de alcance resuelta (2026-09-12): WhatsApp queda fuera del MVP

`docs/vision.md` §MVP punto 6 incluía *"registro manual de ventas de WhatsApp para control de
stock"*, mientras el roadmap lo difería a una **Spec 08.2** desde el 2026-09-10. Los dos documentos
decían cosas distintas sobre qué entra en el MVP.

**Resuelto por el dueño: no entra.** La visión queda enmendada —el texto original **se conserva
tachado**, con el motivo y la fecha, según la regla de que las decisiones se marcan y no se
borran—. La funcionalidad sigue siendo deseable y conserva su spec prevista, pero **post-MVP**.

**Consecuencia asumida**: el stock de una venta por WhatsApp no baja solo. Se ajusta a mano desde el
panel de productos, que ya existe y deja el cambio auditado (Spec 03, regla 68). Lo que reabriría la
decisión es que ese ajuste se olvide lo suficiente como para vender algo sin stock real — que es,
además, una de las métricas de éxito declaradas en la visión.

**Con esto, el MVP queda cerrado en su alcance**: lo único que falta para tenerlo completo es la
**fase 08.c**. La Spec 09 (descuentos) está fuera: el roadmap la marca opcional y la visión no la
incluye.

#### Cómo se abren los PRs

**`gh` está instalado y autenticado** como `gsciancalepore` (v2.100.0), pero vive en
`~/.local/bin/gh` y **no está en el `PATH`** de una shell no interactiva: por eso `gh pr list`
falla con *command not found*. Invocarlo con la ruta completa, o con
`PATH="$HOME/.local/bin:$PATH" gh ...`. La vía web sigue sirviendo:
`https://github.com/gsciancalepore/revestimientos/compare/main...<rama>?expand=1`.

#### Estado del entorno local

`APP_URL=http://localhost:8080`, Vite con hot reload, **sin túnel** (hay que rearmarlo para probar
el webhook). Dos trampas que costaron una tarde y están documentadas en `.ai/rules/general.md` y en
el README:

- Tras un `wsl --shutdown`, los contenedores levantan sanos pero **los puertos publicados quedan
  muertos**. Se arregla con `docker compose up -d --force-recreate web assets mailpit`.
- El sitio es `http://localhost:8080`. Con `https://` o sin el puerto, el navegador da errores que
  parecen del servidor y no lo son.

#### Trampa de proceso, aprendida a la mala el 2026-09-11

**No usar `git checkout -- <archivo>` para revertir una mutación si el archivo tiene cambios sin
commitear**: restaura la versión commiteada y **borra el trabajo en curso**. Pasó dos veces durante
la 08.b, y la segunda dejó la suite corriendo contra código viejo, dando un "control en rojo" que
parecía un bug real. Commitear antes de mutar, o respaldar los archivos fuera del repo.

#### Pendientes sin fecha

- Verificar si staging tiene aplicadas las migraciones de `orders` (ver la discrepancia más abajo).
  **Decisión del dueño (2026-09-11): staging queda parkeado hasta alcanzar el MVP funcional; hasta
  entonces el foco es enteramente local.** Nada de la Spec 08 lo necesita: el túnel de la
  verificación con MercadoPago también corre en la máquina local.
- Correr `verificador-spec-codigo` sobre las specs **01, 02 y 04**, que nunca se revisaron.
- **Candidato a agente**: un QA de flujos que ejecute el procedimiento del túnel, dispare un pago
  real en el sandbox y verifique que el webhook movió el pedido a `paid` con el stock descontado. Es
  el único hueco que los tres agentes actuales no cubren: **nadie usa la aplicación**. Los dos
  defectos más caros del proyecto —el botón que faltaba en el carrito y MercadoPago cobrando el
  subtotal— los encontró una persona haciendo el flujo a mano, no un test.

#### Agentes disponibles (`.claude/agents/`, versionados)

| Agente | Cuándo | Qué hace |
|---|---|---|
| `revisor-spec` | Borrador de spec sin aprobar | Numeración de reglas, coherencia con ADRs, matriz de permisos, casos borde, decisiones de negocio implícitas |
| `verificador-spec-codigo` | Antes de construir sobre una spec cerrada | Verifica regla por regla que el código la implemente y que haya test que la cubra |
| `revisor-entrega` | Implementación terminada y en verde, **antes del push** | Audita el diff contra su spec; muta la implementación y comprueba que algún test se ponga rojo |

- **Próximo paso**: la fase **08.c** (panel de pedidos, confirmación manual de transferencia, vista
  depósito, cancelaciones y visibilidad del stock negativo; reglas 146 y 159-165). La spec ya está
  aprobada, así que no hace falta escribir nada nuevo. Antes de empezar, leer los tres avisos que
  08.a y 08.b dejaron para esta fase en el punto de retome.
- **Cerrado (2026-09-10)**: la **Spec Higiene 02** quedó implementada y mergeada (PR #15). Incluía el
  hallazgo que afectaba datos —la regla 68 guardaba el valor **nuevo** como "anterior" porque
  `UpdateProductAction` leía `getOriginal()` después del `save()`—; los dos registros corruptos en
  desarrollo **no son reparables** y se dejaron como estaban, porque `audit_logs` es inmutable por
  ADR-004. Las Specs 07.2 y 07.3 quedaron enmendadas con su sincronía: la 07.2 afirmaba una cobertura
  de concurrencia que no existía, y la regla 117 decía `findOrFail` + 404 donde el código hace `find`
  + redirect.
- **Aprobada (2026-09-10)**: la **Spec 08** (`docs/specs/08-gestion-pedidos.md`, reglas 143–166),
  revisada por `revisor-spec` y corregida. Sus puntos abiertos de negocio quedaron resueltos por el
  dueño. Se entrega en tres fases; la **08.a está implementada**.
- **Decisión de negocio (2026-09-10)**: **el pago debe ser completo**. Un pedido cuyo monto cobrado
  no coincide con el total (regla 157), o que recibe un pago estando cancelado (regla 151), queda
  **deliberadamente trabado**: no hay override para forzarlo a `paid`. Se resuelve fuera del sistema
  caso por caso; recién cuando ocurra uno concreto se evaluará si hace falta un mecanismo.
- **Decisión de negocio (2026-09-10)**: el comercio se abastece **directo del fabricante**, así que
  la mercadería siempre se consigue. Un pago cobrado con stock insuficiente **no** se rechaza: el
  pedido pasa a `paid`, el stock puede quedar negativo y se marca como reposición pendiente
  (reglas 145–146). El stock **sigue siendo una cantidad concreta y visible** en el catálogo: nunca
  se muestra ilimitado ni en negativo al cliente.
- **Nota (2026-09-10)**: se evaluó **reservar stock al crear el pedido** —lo que habría revertido
  ADR-005— y se **descartó** el mismo día por el costo de la infraestructura que exigía (vencimiento
  automático de pedidos impagos, con un scheduler que ningún entorno ejecuta). **ADR-005 queda
  ratificada** y la Spec 08 la implementa; el análisis de la alternativa queda archivado en ADR-012,
  marcada como descartada. Ninguna spec cerrada necesita enmienda.
- **Nota (2026-09-10)**: verificación manual de la 07.4 contra MercadoPago sandbox. El flujo
  `/carrito → /checkout → MercadoPago → /checkout/exito` funciona completo; el pedido queda en
  `PendingPayment` y el stock no se descuenta aunque el pago se apruebe, tal como fija la regla 128.
  Eso es precisamente el hueco que abre la Spec 08. Detalle en
  `docs/specs/07-checkout-fase4-mercadopago.md` §Sincronía 2026-09-10.
- **Nota (2026-09-10)**: probar MercadoPago en local exige `APP_URL` público (túnel `cloudflared`),
  porque MP rechaza back_urls en localhost. El webhook de la Spec 08 va a necesitar lo mismo.
- **Nota**: `chore/higiene-01` ya mergeado en `main` (`bc45b67`); Spec 07 completa mergeada a `main` (07.3 PR #6 `aac924b`, 07.4 PR #8 `5bb9ddd`).
- **Proceso**: spec aprobada por el dueño → rama nueva (`feat/...`) → TDD (red → green → refactor) →
  verificación local (Pint, PHPStan nivel 8, Pest) → Pull Request a `main` con CI en verde → merge (el CI
  valida la misma secuencia; `main` despliega a staging).
- **Nota (revisión 2026-08-05)**: la Spec 02 quedó revisada a **categorías
  planas** (se eliminó `parent_id` de `categories` con migración) y la **Spec 03
  quedó cerrada** (productos con dos modos de venta y atributos híbridos).
- **Nota (revisión 2026-08-06)**: la **Spec 04 quedó cerrada** — catálogo
  público: home con destacados, listados con filtros combinables y búsqueda,
  ficha con calculadora m²→cajas (Alpine), slug de producto y layout público
  `layouts/site`.
- **Nota (2026-09-03)**: la **Spec 05 quedó cerrada** — carrito anónimo en sesión (reglas 81–92, `Cart` + `M2Calculator` reuso, `subtotal` sí / `total` no), validación stock `cantidad ≤ stock` e `activo`, condición derivada no comprable sin estado, 19 tests nuevos (135 totales).
- **Nota (2026-09-03)**: la **Spec 06 quedó cerrada** — envío por CP exacto 4 dígitos con una tarifa activa por CP (`shipping_rates` CHECK ≥0, único parcial), `ShippingCalculator` + `ManualShippingCalculator` (cotización `disponible`/no disponible sin excepción, ceros iniciales, costo 0), CRUD admin y `total = subtotal + shipping` en carrito, 23 tests nuevos (158 totales).
- **Nota (2026-09-03)**: la **Spec 07 quedó cerrada** — 07.1 estructura `orders`/`order_lines` + `OrderStatus` + `PaymentGateway`; 07.2 `PlaceOrderAction` (`lockForUpdate` + `bcmath` + `audit` + `Cart::clear` post-commit); 07.3 HTTP `CheckoutController` + `StoreCheckoutRequest` + `session order_id` (sin `{order}`), `shipping !disponible → 0` permitido; 38 tests nuevos (196 totales).
- **Discrepancia detectada en la auditoría documental (2026-09-10) — sin resolver**: el repositorio
  tiene **15 migraciones**, pero los documentos no coinciden sobre cuántas están aplicadas en staging:
  `docs/deployment/staging.md` dice `11` (snapshot del 2026-09-01) y este roadmap decía `14`. Como las
  migraciones en staging son un **step manual** (`staging.md §11`, no van en el `CMD`), es posible que
  `orders`/`order_lines`/`add_mp_fields_to_orders` **no estén aplicadas** y que el checkout de staging
  esté roto. **Verificar con `php artisan migrate:status` contra Neon antes de dar staging por bueno.**
- **Contexto para agentes nuevos**: `.ai/rules/index.md` mapea las reglas
  durables del repo; el runbook de arranque está en el README.
