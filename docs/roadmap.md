# Roadmap

Última actualización: 2026-09-11 (**Spec 08 fase 08.b implementada**: webhook de MercadoPago autenticado por firma HMAC con comparación en tiempo constante, filtro por tipo antes de consultar, estado real consultado contra la API por el puerto `PaymentStatusQuery` —`PaymentClient` es `final` y no se puede mockear, así que la costura no pudo ser la que la regla 155 describía—, verificación de monto contra `total_cents` y 503 deliberado ante fallo transitorio para que MercadoPago reintente; 357 tests, cada regla verificada mutando la implementación, incluido un test de CSRF que no cubría nada porque Laravel saltea `ValidateCsrfToken` durante la suite. **Spec 08 fase 08.a implementada**: máquina de estados, descuento de stock al confirmarse el pago con relectura del pedido bajo lock, restitución al cancelar, stock negativo auditado como reposición pendiente; `PlaceOrderAction` alineado con el bloqueo ordenado que pedía la regla 143; 340 tests. **Spec Higiene 02 cerrada**: HIG-04 la auditoría de precio y stock guardaba el valor nuevo como anterior —`getOriginal()` leído después del `save()`—; HIG-06 `PlaceOrderAction` no validaba los datos del cliente que la regla 108 exige, se cumplía por accidente vía `StoreCheckoutRequest`; HIG-07 la revalidación bajo `lockForUpdate` no tenía cobertura y se podía borrar con la suite en verde; HIG-08 guard del reintento MP sobre pedido pagado; HIG-09 la regla 117 enmendada a `find` + redirect. Specs 07.2 y 07.3 enmendadas con su sincronía; 274 tests. Spec 07.4 **verificada de punta a punta contra la API real de MercadoPago** por primera vez: hasta ahora solo estaba probada con gateway fake, y la verificación destapó dos defectos que enmiendan la regla 123 —`auto_return` condicionado a back_url pública y costo de envío en `shipments.cost`—, más credenciales externas neutralizadas en `phpunit.xml` y el botón "Finalizar compra" que faltaba en el carrito; 261 tests. Spec 06 fase 2 cerrada: importador administrativo de tarifas por CP, 253 tests en verde, Pint/PHPStan alineados; Spec 07 cerrada: 07.1/07.2/07.3/07.4 implementadas y mergeadas a `main` — 07.4 `cb9fd2b`/PR #8 — 205 tests; Staging: `docs/deployment/staging.md` operativo `~0.3-0.7s`, `Render Oregon + Neon Oregon PG18 (18.6, us-west-2)` co-localizado, `Neon` 14 migraciones + seed `users=1`/`roles=3`/`categories=4`/`products=1` + `shipping_rates` + `orders`/`order_lines`, `RoadRunner 2w`, fixes `cb1002b`/`e56e62c`/`73d2945`/`bbfd1fd` TrustProxies + seed vacío §15.2/15.3 + latencia Oregon §15.4/ADR-010, deploy `https://revestimientos.onrender.com` operativo; `docker-compose.yml` se mantiene en `postgres:17` — bump a 18 se evalúa aparte; Spec 06 Envío cerrada 158 tests).

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
| 08 | Gestión de pedidos | Máquina de estados, `ConfirmPaymentAction` con descuento de stock (ADR-005), restitución al cancelar un pedido pagado, webhook de MercadoPago con validación de firma y verificación de monto, panel de pedidos y vista depósito | Orders | 🔨 **en curso** — aprobada por el dueño (2026-09-10), reglas 143–166, entrega **en 3 fases**. ✅ **08.a dominio** (rama `feat/pedidos-08a`): `OrderStatus` con la máquina de estados, `TransitionOrderStatusAction`, `ConfirmPaymentAction` con descuento bajo lock e idempotencia, `CancelOrderAction` con restitución — **340 tests**, Pint/PHPStan alineados. ✅ **08.b webhook** (rama `feat/pedidos-08b`): `POST /webhook/mercadopago` con firma HMAC, filtro por tipo, consulta a la API por el puerto `PaymentStatusQuery`, verificación de monto y 503 deliberado ante fallo transitorio — **357 tests**, siete mutaciones verificadas. Falta la prueba contra MercadoPago real (túnel + `MERCADOPAGO_WEBHOOK_SECRET`). ⏳ 08.c panel y despacho. Ventas WhatsApp diferidas a la Spec 08.2 |
| 08.2 | Ventas manuales por WhatsApp | Alta de pedido desde el panel, opcionalmente con link de pago de MercadoPago | Orders | pendiente (diferida por decisión del dueño, 2026-09-10) |
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

### Punto de retome — cierre del 2026-09-11

Estado exacto al terminar la jornada, para que cualquiera (persona o agente) retome sin reconstruir
contexto. **Leer esto primero.**

#### Qué hay en `main`

Todo lo de la jornada anterior más la **Spec Higiene 02 completa** (PR #15): auditoría de precio y
stock con el valor anterior real, validaciones de dominio en `PlaceOrderAction`, cobertura real de
la revalidación bajo lock y guard del reintento de MercadoPago. `main` quedó en **274 tests**.

#### Dos ramas sin mergear, en este orden

1. **`docs/entorno-local-y-lenguaje`** (solo documentación). Corrige el procedimiento de DNS de
   `desarrollo-local.md`, documenta dos trampas nuevas del entorno, incorpora al glosario los
   términos que 08.a volvió reales, y agrega los agentes al README y a `AGENTS.md`.
   **Va primero**: si entra después de la 08.a, `main` queda un rato con el glosario diciendo que
   "sin stock" es exactamente 0 mientras el código ya permite negativos.
2. **`feat/pedidos-08a`** (fase 08.a de la Spec 08). **340 tests**, Pint y PHPStan nivel 8 limpios,
   árbol limpio.

Ninguna está pusheada al momento de escribir esto. Los PRs se abren desde la web —**`gh` no está
instalado**, la nota anterior de este roadmap decía lo contrario y era falso— con
`https://github.com/gsciancalepore/revestimientos/compare/main...<rama>?expand=1`.

#### Qué implementa la 08.a y qué NO

Implementa las reglas **143-145, 147-152 y 166**: máquina de estados en `OrderStatus`,
`TransitionOrderStatusAction` como único camino a `order.status`, `ConfirmPaymentAction` con
descuento de stock bajo lock e idempotencia, `CancelOrderAction` con restitución. Dominio puro: sin
rutas, sin controladores, sin pantallas.

**No implementa** —y que falte es correcto— el webhook (08.b, reglas 153-158) ni el panel, la vista
depósito y la visibilidad del stock negativo (08.c, reglas 146 y 159-165).

**Lo que hay que mirar sí o sí antes de tocar esas Actions**: `.ai/rules/pedidos.md`. Ahí está lo
que no se deduce leyendo el código, incluido que **el stock negativo y el pedido trabado tras un
pago cancelado son decisiones del dueño, no defectos a corregir**.

#### Cómo se auditó, y por qué importa para la próxima fase

La 08.a pasó **dos veces** por el agente `revisor-entrega`. La primera la **bloqueó**: dos reglas del
corazón de la fase estaban bien implementadas pero **sin un solo test que las protegiera** —se podían
borrar enteras con los 327 tests en verde—, y en la cancelación ese agujero **perdía stock**. La
segunda pasada verificó los arreglos rompiendo el código ella misma y encontró cuatro huecos más,
todos cerrados. El veredicto final fue *apto con correcciones menores*.

La lección, que ya es la tercera vez que aparece en este repo (regla 123, regla 109, y ahora la 150):
**los gates en verde no dicen nada sobre si el test cubre la regla**. Correr `revisor-entrega` antes
de cada push no es opcional.

#### Punto abierto que el dueño tiene que resolver

`AGENTS.md` dice que `docs/specs/` **jamás** se edita salvo que la tarea lo pida explícitamente. Dos
commits de `feat/pedidos-08a` editan `docs/specs/08-gestion-pedidos.md`: la línea de Estado al
aprobarse la spec, y después los checkboxes más una sección de sincronía. El contenido es valioso
—la advertencia sobre la regla 159 es justo lo que salva a 08.c— pero **la autorización no está
escrita en ningún lado**. Hay que decidir si se ratifica o si se revierte esa parte.

#### Estado del entorno local

`APP_URL=http://localhost:8080`, Vite con hot reload, sin túnel. Dos cosas que costaron una tarde y
ahora están documentadas en `.ai/rules/general.md` y en el README:

- Tras un `wsl --shutdown`, los contenedores levantan sanos pero **los puertos publicados quedan
  muertos**. Se arregla con `docker compose up -d --force-recreate web assets mailpit`.
- El sitio es `http://localhost:8080`. Con `https://` o sin el puerto, el navegador da errores que
  parecen del servidor y no lo son.

#### Pendientes sin fecha

- Verificar si staging tiene aplicadas las migraciones de `orders` (ver la discrepancia más abajo);
  el dueño lo parkeó hasta el próximo deploy manual.
- Correr `verificador-spec-codigo` sobre las specs **01, 02 y 04**, que nunca se revisaron.
- **Candidato a agente para 08.b**: un QA de flujos que ejecute el procedimiento del túnel, dispare
  un pago real en el sandbox y verifique que el webhook movió el pedido a `paid` con el stock
  descontado. Es el único hueco que los tres agentes actuales no cubren: **nadie usa la aplicación**.
  Los dos defectos más caros del proyecto —el botón que faltaba en el carrito y MercadoPago cobrando
  el subtotal— los encontró una persona haciendo el flujo a mano, no un test.

#### Agentes disponibles (`.claude/agents/`, versionados)

| Agente | Cuándo | Qué hace |
|---|---|---|
| `revisor-spec` | Borrador de spec sin aprobar | Numeración de reglas, coherencia con ADRs, matriz de permisos, casos borde, decisiones de negocio implícitas |
| `verificador-spec-codigo` | Antes de construir sobre una spec cerrada | Verifica regla por regla que el código la implemente y que haya test que la cubra |
| `revisor-entrega` | Implementación terminada y en verde, **antes del push** | Audita el diff contra su spec; muta la implementación y comprueba que algún test se ponga rojo |

- **Próximo paso**: mergear las dos ramas en el orden de arriba y seguir con la **08.b** (webhook de MercadoPago, reglas 153–158), que necesita el
  túnel de `docs/deployment/desarrollo-local.md` y una credencial nueva,
  `MERCADOPAGO_WEBHOOK_SECRET`, a neutralizar en `phpunit.xml` como el resto.
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
