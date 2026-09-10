# Roadmap

Última actualización: 2026-09-10 (Spec 07.4 **verificada de punta a punta contra la API real de MercadoPago** por primera vez: hasta ahora solo estaba probada con gateway fake, y la verificación destapó dos defectos que enmiendan la regla 123 —`auto_return` condicionado a back_url pública y costo de envío en `shipments.cost`—, más credenciales externas neutralizadas en `phpunit.xml` y el botón "Finalizar compra" que faltaba en el carrito; 261 tests. Spec 06 fase 2 cerrada: importador administrativo de tarifas por CP, 253 tests en verde, Pint/PHPStan alineados; Spec 07 cerrada: 07.1/07.2/07.3/07.4 implementadas y mergeadas a `main` — 07.4 `cb9fd2b`/PR #8 — 205 tests; Staging: `docs/deployment/staging.md` operativo `~0.3-0.7s`, `Render Oregon + Neon Oregon PG18 (18.6, us-west-2)` co-localizado, `Neon` 14 migraciones + seed `users=1`/`roles=3`/`categories=4`/`products=1` + `shipping_rates` + `orders`/`order_lines`, `RoadRunner 2w`, fixes `cb1002b`/`e56e62c`/`73d2945`/`bbfd1fd` TrustProxies + seed vacío §15.2/15.3 + latencia Oregon §15.4/ADR-010, deploy `https://revestimientos.onrender.com` operativo; `docker-compose.yml` se mantiene en `postgres:17` — bump a 18 se evalúa aparte; Spec 06 Envío cerrada 158 tests).

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
| 08 | Gestión de pedidos | Máquina de estados, `ConfirmPaymentAction` con descuento de stock (ADR-005), restitución al cancelar un pedido pagado, webhook de MercadoPago con validación de firma y verificación de monto, panel de pedidos y vista depósito | Orders | 📝 **borrador (2026-09-10)** — `docs/specs/08-gestion-pedidos.md`, reglas 143–166, pendiente de aprobación del dueño. Se entrega **en 3 fases** (08.a dominio, 08.b webhook, 08.c panel y despacho). Ventas WhatsApp diferidas a la Spec 08.2 |
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

- **Próximo paso**: aprobar el borrador de la **Spec 08** (`docs/specs/08-gestion-pedidos.md`,
  reglas 143–166), revisado por el agente `revisor-spec` y corregido el 2026-09-10. Sus puntos
  abiertos de negocio quedaron resueltos por el dueño.
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
