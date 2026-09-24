# Roadmap

Última actualización: 2026-09-24 (rama de la regla 167 integrada con `main` tras la Fase A de identidad visual (#36); `revisor-entrega` apto con correcciones menores, aplicadas: dos tests nuevos fijan el límite de 8 y el título/ausencia de la sección "Productos"; 505 tests, Pint/PHPStan limpios). Historial: 2026-09-20 (**Spec 04 enmendada con la regla 167** (sincronía, no reabre la spec): la home suma una sección "Productos" con los activos más recientes, sin exigir oferta —antes, con pocos productos cargados y ninguno en oferta, la home no mostraba nada—; convive con "Destacados con oferta" (regla 72, intacta). `APP_NAME` corregido a "Jireh Revestimientos" en `.env.example` (antes decía "Casa de Ceramicas", un placeholder que nunca se actualizó). 501 tests, Pint/PHPStan limpios). 2026-09-20 (**Spec `identidad-visual-publica` aprobada** tras revisión de `revisor-spec`; **Fase A (sistema base) implementada** — tokens de marca `brand`/`neutral`/`success`/`warning`/`danger`, tipografía `Instrument Sans` + `Fraunces`, layout público rediseñado, `producto.blade.php` corregido para pasar `:categorias` — 500 tests, Pint/PHPStan limpios. Faltan Fase B (home + catálogo), Fase C (ficha + carrito + checkout) y el recorrido visual manual del dueño). 2026-09-18 (**Higiene 03 completa y mergeada**: 03.a PR #30 y 03.b PR #32 el 2026-09-17, 499 tests; pendientes PRs `docs:`/`chore:` y la verificación del webhook real). 2026-09-17 (**Spec Higiene 03 fase 03.b implementada y auditada** (rama `fix/higiene-03b`, **499 tests**, `revisor-entrega` apto; falta push y PR): HIG-23 índices únicos, HIG-24 `UserPolicy` directa, HIG-25 throttle, HIG-26 reset, HIG-27 seeder, HIG-28 sidebar, HIG-29 catálogo, HIG-30 Spec 02, HIG-31 mínimo 8, HIG-32 deuda. **Spec Higiene 03 fase 03.a implementada**: la oferta se cobra (HIG-10, `precioVigenteCents()` punto único, `precioCajaCents()` intacto), auditoría de oferta (HIG-33), `external_reference` afirmado (HIG-11), M2 sin `m2_por_caja` rechazado (HIG-12), ficha unificada con `M2Calculator` sin ruta nueva (HIG-13, regla 75 preservada) y desperdicio sin truncar (HIG-14), más HIG-15 a HIG-22 y HIG-28a; 462 tests —436 base + 26 nuevos—, cada regla verificada mutando la implementación; sincronías anotadas en Specs 02, 03, 04, 05, 07.3 y 08 más glosario y `arquitectura.md`; PR #30 mergeado el 2026-09-17; falta la fase 03.b. 2026-09-15 (**Specs 01, 02, 04 y `calidad-onboarding` verificadas contra el código**: ninguna tenía reglas fantasma, pero las cuatro quedan sin poder darse por cerradas y aportan 30 hallazgos al borrador de Higiene 03 — el más grave, la oferta que se muestra y nunca se cobra, con la decisión del dueño de que **sí se cobra**. Repuesta la guía de formato de specs, que se había erosionado hasta desaparecer, y documentadas dos trampas del entorno. **Spec 08 completa**: las tres fases implementadas — dominio, webhook y panel — con 436 tests; queda solo la verificación del webhook contra MercadoPago real, con túnel y credencial. **Alcance del MVP cerrado**: las ventas manuales por WhatsApp quedan **fuera del MVP** por decisión del dueño, con la visión enmendada y el texto original conservado; lo único que falta para el MVP completo es verificar el webhook contra MercadoPago real (la fase 08.c quedó implementada el 2026-09-12). **Spec 08 fase 08.b implementada**: webhook de MercadoPago autenticado por firma HMAC con comparación en tiempo constante, filtro por tipo antes de consultar, estado real consultado contra la API por el puerto `PaymentStatusQuery` —`PaymentClient` es `final` y no se puede mockear, así que la costura no pudo ser la que la regla 155 describía—, verificación de monto contra `total_cents` y 503 deliberado ante fallo transitorio para que MercadoPago reintente; 378 tests, cada regla verificada mutando la implementación. `revisor-entrega` bloqueó la primera versión: el adaptador real del puerto no lo ejercitaba ningún test y se podía vaciar entero con la suite en verde, y la rama de "pago desconocido" era inalcanzable porque el SDK lanza excepción también en el 404. Se agregó además un cerrojo que impide que cualquier test salga a la red. **Spec 08 fase 08.a implementada**: máquina de estados, descuento de stock al confirmarse el pago con relectura del pedido bajo lock, restitución al cancelar, stock negativo auditado como reposición pendiente; `PlaceOrderAction` alineado con el bloqueo ordenado que pedía la regla 143; 340 tests. **Spec Higiene 02 cerrada**: HIG-04 la auditoría de precio y stock guardaba el valor nuevo como anterior —`getOriginal()` leído después del `save()`—; HIG-06 `PlaceOrderAction` no validaba los datos del cliente que la regla 108 exige, se cumplía por accidente vía `StoreCheckoutRequest`; HIG-07 la revalidación bajo `lockForUpdate` no tenía cobertura y se podía borrar con la suite en verde; HIG-08 guard del reintento MP sobre pedido pagado; HIG-09 la regla 117 enmendada a `find` + redirect. Specs 07.2 y 07.3 enmendadas con su sincronía; 274 tests. Spec 07.4 **verificada de punta a punta contra la API real de MercadoPago** por primera vez: hasta ahora solo estaba probada con gateway fake, y la verificación destapó dos defectos que enmiendan la regla 123 —`auto_return` condicionado a back_url pública y costo de envío en `shipments.cost`—, más credenciales externas neutralizadas en `phpunit.xml` y el botón "Finalizar compra" que faltaba en el carrito; 261 tests. Spec 06 fase 2 cerrada: importador administrativo de tarifas por CP, 253 tests en verde, Pint/PHPStan alineados; Spec 07 cerrada: 07.1/07.2/07.3/07.4 implementadas y mergeadas a `main` — 07.4 `cb9fd2b`/PR #8 — 205 tests; Staging: `docs/deployment/staging.md` operativo `~0.3-0.7s`, `Render Oregon + Neon Oregon PG18 (18.6, us-west-2)` co-localizado, `Neon` 14 migraciones + seed `users=1`/`roles=3`/`categories=4`/`products=1` + `shipping_rates` + `orders`/`order_lines`, `RoadRunner 2w`, fixes `cb1002b`/`e56e62c`/`73d2945`/`bbfd1fd` TrustProxies + seed vacío §15.2/15.3 + latencia Oregon §15.4/ADR-010, deploy `https://revestimientos.onrender.com` operativo; `docker-compose.yml` se mantiene en `postgres:17` — bump a 18 se evalúa aparte; Spec 06 Envío cerrada 158 tests).

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
| 2 | Specs 01..09 (funcionales, TDD) | ✅ **specs 01–08 cerradas** (2026-09-12). La 09 (descuentos) es opcional y quedó **fuera del MVP**; las ventas por WhatsApp (08.2) también, por decisión del dueño |
| 1d | Identidad visual pública: spec `identidad-visual-publica` (paleta, tipografía, layout público) | 🔄 **en curso** — aprobada (2026-09-20). **Fase A (sistema base) implementada**: tokens de marca (`brand`/`neutral`/`success`/`warning`/`danger`) en `resources/css/app.css`, tipografía `Instrument Sans` + `Fraunces` cargada correctamente (reemplaza el mismatch previo con `Figtree`), `layouts/site.blade.php` rediseñado, `producto.blade.php` corregido para pasar `:categorias` (regla 4) — 500 tests, Pint/PHPStan limpios. Faltan Fase B (home + catálogo) y Fase C (ficha + carrito + checkout), más el recorrido visual manual del dueño |

## Fase 2 — Entregables funcionales

Cada spec se implementa en orden; cada una depende de la anterior
(autenticación → datos → catálogo → venta → operación).

| # | Spec | Contenido | Dominio | Estado |
|---|---|---|---|---|
| 01 | Autenticación y roles | Login admin (Breeze en `/admin`), usuarios internos, roles admin/vendedor/depósito (Spatie), Policies, auditoría de usuarios/roles | Users | ✅ cerrada (2026-08-05): 52 tests en verde, Pint/PHPStan alineados, CI verde |
| 02 | Panel + Categorías | Layout admin con sidebar, CRUD de categorías | Products | ✅ cerrada (2026-08-05): 79 tests en verde, Pint/PHPStan alineados. **Revisada (2026-08-05): categorías planas** (Porcelanatos, Cerámicas, Pastinas, Adhesivos; sin jerarquía) para el modelo de Spec 03 |
| 03 | Productos | Dos modos de venta (m² y unidad), atributos híbridos (columnas tipadas + `specs` JSONB por familia), código único, precios, ofertas, stock, imágenes | Products | ✅ cerrada (2026-08-05): 93 tests en verde, Pint/PHPStan alineados. **Nota (2026-09-06, sincronía SDD)**: la regla 67, diferida en su momento a "cuando exista la tabla `orders`", quedó **activada** con alcance `unidad_venta` + borrado de productos con pedidos (`Product::tienePedidos()`, `DomainException` en `UpdateProductAction`/`DeleteProductAction`). No reabre la spec |
| 04 | Catálogo público | Home, categorías, filtros, ficha con calculadora m²→cajas (modo m²), stock visible, ofertas | Products | ✅ cerrada (2026-08-06): 116 tests en verde, Pint/PHPStan alineados. **Nota (2026-09-20, sincronía SDD)**: se agrega la **regla 167** —sección "Productos" en la home con los 8 activos más recientes, sin exigir oferta—, que convive con la regla 72 sin enmendarla. No reabre la spec |
| 05 | Carrito | Carrito anónimo en sesión, líneas por producto, derivación m²→cajas con `M2Calculator` y 10 % desperdicio antes de `ceil`, validación `cantidad ≤ stock` e `activo`, acumulación/actualización/eliminar/vaciar, `subtotal` sí / `total` no, condición derivada no comprable (sin estado) | Orders | ✅ cerrada (2026-09-03): 135 tests en verde, Pint/PHPStan alineados |
| 06 | Envío | Tarifa única por CP exacto 4 dígitos, `ShippingCalculator` + `ManualShippingCalculator` con `shipping_rates` (CHECK ≥0, único parcial activo), cotización `disponible`/no disponible sin excepción, CRUD admin y `total = subtotal + shipping` en carrito | Orders | ✅ cerrada (2026-09-03): 158 tests en verde, Pint/PHPStan alineados |
| 06.2 | Envío — Importador de tarifas | Importación administrativa del CSV snapshot `codigo_postal,precio_envio`: validación total previa (422 sin persistir), temporal server-side + token con manifiesto en caché, preview con conteos, aplicación fila-por-fila en una única transacción, idempotente y sin borrados físicos | Orders | ✅ cerrada (2026-09-06): 253 tests en verde, Pint/PHPStan alineados |
| 07 | Checkout | Compra anónima, MercadoPago, transferencia con confirmación manual, creación del pedido | Orders + Payments | ✅ cerrada (2026-09-03): 07.1 estructura `orders`/`order_lines` + `OrderStatus` + `PaymentGateway`; 07.2 `PlaceOrderAction` (`Cart` + `lockForUpdate` + `bcmath` + `audit`); 07.3 HTTP `GET /checkout`, `POST /checkout`, `GET /checkout/exito` con `StoreCheckoutRequest` + `CheckoutController` delgado + `session order_id` (sin `{order}`), `shipping !disponible → 0` permitido, 12 tests Checkout, 14 migraciones, **196 tests**; ✅ 07.4 MercadoPago (2026-09-04; verificada contra la API real el 2026-09-10): `MercadoPagoGateway` (SDK `dx-php` pineado, `Preference` + `redirect away init_point`) + `mp_preference_id/mp_init_point` + `POST /checkout/mercadopago/reintentar` + `success` solo lectura (botón continuar/reintentar), 9 tests MP, **205 tests** |
| H02 | Higiene 02 — auditoría, validación y cobertura | Valor anterior real en la auditoría de precio y stock, validaciones de `PlaceOrderAction` que la regla 108 exige, cobertura real de la revalidación bajo lock, guard del reintento MP | Products + Orders | ✅ cerrada (2026-09-10): reglas HIG-04–HIG-09 implementadas, **274 tests** en verde, Pint/PHPStan alineados. Specs 07.2 y 07.3 enmendadas con su sincronía. Mergeada a `main` (PR #15) |
| H03 | Higiene 03 — oferta, calculadora y cobertura | La oferta se cobra, calculadora única, roturas 500 y cobertura | Products + Orders + Payments | ✅ **completa y mergeada** (03.a PR #30, 462 tests; 03.b PR #32 el 2026-09-17, 499 tests, `revisor-entrega` apto): HIG-10–HIG-33. Pendientes: PRs `docs:` (higiene documental) y `chore:` (`npm ci`) |
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

## Cómo se escribe una spec nueva

Toda spec sigue el mismo formato, con estas secciones en este orden (modelos:
`docs/specs/00-dominio.md` y `docs/specs/01-autenticacion-roles.md`):

**objetivo · contexto · reglas de negocio · matriz de permisos · casos borde ·
criterios de aceptación · tareas técnicas**

Además:

- **La numeración de las reglas es global y corrida**, no por spec: cada regla
  nueva continúa donde terminó la última spec cerrada. Al 2026-09-20 van del 1
  al 167 (la 167 es la sincronía de la Spec 04), sin huecos ni duplicados;
  verificar el último número real antes de numerar, en vez de confiar en este
  dato si pasó tiempo.
- **Proceso**: spec aprobada por el dueño → rama nueva (`feat/...`) → TDD
  (red → green → refactor) → verificación local (Pint, PHPStan nivel 8, Pest) →
  Pull Request a `main` con CI en verde → merge.
- **Antes de que el dueño la apruebe**, el borrador pasa por el agente
  `revisor-spec` (`.claude/agents/`), que verifica numeración, coherencia con
  las ADRs y las specs cerradas, cobertura de la matriz de permisos, casos borde
  y criterios testables.
- **Contexto para agentes nuevos**: `.ai/rules/index.md` mapea las reglas
  durables del repo; el runbook de arranque está en el README.

**Por qué esto es una sección propia (2026-09-15)**: esta guía existía desde el
`532dcb7` (2026-08-05) pero vivía **dentro del bullet "Próximo paso"**, que se
reescribe cada vez que arranca una spec nueva. Se erosionó en tres reescrituras
sucesivas —`4f565dd` se llevó la lista de secciones, `3f4f5e6` los archivos de
ejemplo, `a8bd92d` la frase entera— hasta desaparecer del repositorio el
2026-09-03. Nadie la borró a propósito: era guía durable estacionada dentro de
un texto efímero. Se repone acá, fuera del alcance de esa reescritura, para que
no vuelva a pasar.

## Cómo continuar

### Punto de retome — 2026-09-24: regla 167 lista para PR, identidad visual Fase A mergeada

**Leer esto primero.** Los PRs `docs:` (#33) y `chore:` (#34) del punto anterior están mergeados,
igual que la spec `identidad-visual-publica` (#35) y su Fase A (#36). La rama
`feat/catalogo-04-regla-167-home` (regla 167, sección "Productos" en la home) integró `main` después
de #36 y queda lista para su PR.

**Lo próximo, en orden**:

1. PR de la regla 167 (`feat/catalogo-04-regla-167-home`).
2. Análisis de la capa de observabilidad ideal del proyecto, como base para un agente de IA que
   investigue incidentes a partir de los logs (primera etapa: solo logs).
3. Identidad visual Fase B (home + catálogo, incluida la sección "Productos" de la regla 167, que
   todavía usa la paleta `stone`/`orange`) y Fase C (ficha + carrito + checkout).
4. **Verificación del webhook contra MercadoPago real** (único pendiente del MVP), sin cambios
   respecto del punto del 2026-09-18.

### Punto de retome — 2026-09-18: Higiene 03 mergeada, faltan PRs docs:/chore: y webhook real

**Leer esto primero.** La Spec Higiene 03 está **completa y mergeada** (03.a PR #30, 03.b PR #32 el
2026-09-17, **499 tests**, `revisor-entrega` apto en ambas fases). La rama `fix/higiene-03b` ya se
eliminó (mergeada, sin nada pendiente en ella).

**Lo próximo, en orden**:

1. PR `docs:` (rama `docs/higiene-documental`): higiene documental de Higiene 03 §Fuera de alcance
   (nota de idioma en AGENTS.md, "jerárquicas" en Spec 02:18, runbook del README) más la
   actualización de este roadmap. Autorizado por la spec, sin spec nueva; pasó por `revisor-spec`
   (aprobable con correcciones menores, aplicadas).
2. PR `chore:` (rama `chore/npm-ci`, en paralelo): `npm install` → `npm ci` en `Makefile:52`,
   `Makefile:58` y `docker-compose.yml:81`. `composer.json:50` queda fuera a propósito: es el
   script `setup` del skeleton, flujo no usado en este repo.
3. **Verificación del webhook contra MercadoPago real** (único pendiente del MVP): túnel +
   credenciales `TEST-`, procedimiento en `docs/deployment/desarrollo-local.md` §Webhook. HIG-11 ya
   protege `external_reference`. Requiere acceso del dueño al panel de MercadoPago.

### Punto de retome — 2026-09-17: fase 03.a implementada y mergeada (PR #30), falta 03.b

**Leer esto primero.** La Spec Higiene 03 quedó aprobada por el dueño el 2026-09-16 (tras dos vueltas
de `revisor-spec`: la primera bloqueó por PA-1/PA-2 abiertos, texto de regla 87 ausente y HIG-28 en
dos fases; la segunda pidió 5 precisiones que se aplicaron). La fase **03.a está implementada** en la
rama `fix/higiene-03a` (cortada desde `main`, 9 commits `fix:` + 1 `docs:` de aprobación): HIG-10 a
HIG-22 más HIG-28a y HIG-33, **462 tests** (436 base + 26 nuevos), Pint limpio, PHPStan nivel 8 sin
errores, cada regla verificada mutando la implementación. Sincronías append-only anotadas en Specs
02, 03, 04, 05, 07.3 y 08 (reglas 87, 92, 68, 153+matriz y 44), más fila de `precio vigente` en el
glosario y `arquitectura.md` al día. La regla 75 **no** se enmendó: HIG-13 la preserva (la ficha se
re-renderiza en el servidor con query params, sin ruta nueva).

**Lo próximo, en orden**: `fix/higiene-03b` (HIG-23–HIG-32, solo tests + índices únicos) → PRs paralelos
`docs:` (higiene documental) y `chore:` (`npm ci`) → verificación del webhook contra MercadoPago real
con túnel (HIG-11 ya protege `external_reference`).

### Punto de retome — 2026-09-15: verificación de las specs 01, 02, 04 y calidad-onboarding

**Leer esto primero.** Se corrió `verificador-spec-codigo` sobre las cuatro specs que la auditoría
documental del 2026-09-12 había dejado pendientes. `main` sigue en `2ae65ff` con **436 tests**,
PHPStan nivel 8 sin errores y Pint limpio.

**Ninguna de las cuatro tenía reglas fantasma**: todo lo que las specs dicen está escrito. Lo que
apareció son **30 hallazgos**, y las cuatro specs quedan **sin poder darse por cerradas**. Los más
graves, para dimensionar:

- **La oferta se muestra y nunca se cobra.** `precio_oferta_cents` no aparece ni en `Cart.php` ni en
  `PlaceOrderAction.php`: un producto exhibido con 25 % OFF se cobra al precio de lista entero, y la
  diferencia viaja al pedido, a MercadoPago y a la factura. **Decisión del dueño (2026-09-15): la
  oferta se cobra.** La regla 87 queda enmendada —su definición de `precio_vigente_cents` debe
  contemplar la oferta activa— con sincronía en las Specs 04 y 05.
- **La calculadora de la ficha es una segunda implementación** en JavaScript que diverge del carrito
  en una caja entera (209 divergencias en 30.000 combinaciones), contra la regla 75 que fija a
  `M2Calculator` como único lugar del redondeo.
- **Tres defectos que hoy devuelven 500**: vaciar el campo "Orden" de una categoría, una dirección de
  entre 256 y 500 caracteres en el checkout, y la ruta `DELETE /admin/usuarios/{user}` que la regla 37
  prohíbe y que apunta a un método inexistente.
- **El carrito le muestra al cliente "quedan −3 cajas"**: la verificación de la regla 146 cubrió
  grilla, ficha y `Cart.php`, pero no la línea del carrito.
- **Sin cobertura**: `UserPolicy` (el middleware `role:admin` tapa el 403 en los seis tests de
  denegación, así que la Policy se puede vaciar en verde), el throttle de login, el un-solo-uso del
  reset, el `DatabaseSeeder`, el sidebar y el orden del catálogo.

**Lo próximo**: escribir el borrador de **Spec Higiene 03** con estos hallazgos más los cuatro de la
familia 07 ya documentados abajo. **No se implementa nada hasta que el dueño lo apruebe.** Conviene
proponerle un corte de alcance: 30 hallazgos es mucho para una sola spec, y la higiene documental
pura no necesita spec.

**Caso borde anotado, sin urgencia (decisión del dueño, 2026-09-15)**: un admin no puede
autodesactivarse —guard implementado y testeado— pero **sí puede cambiarse el rol a vendedor**, con el
mismo efecto de dejar el sistema sin administradores. No debería poder; queda fuera del alcance de
Higiene 03.

**Incidente de la jornada**: la base de desarrollo se vació con un `migrate:fresh --env=testing`
durante la verificación. Se reconstruyó con `make migrate && make seed`; **las tarifas de envío las
recarga el dueño desde su CSV**. La trampa quedó documentada en `.ai/rules/tests.md` y en el README,
que es donde faltaba.

### Punto de retome — 2026-09-12, tarde: verificación del webhook contra MercadoPago real

**Leer esto primero si retomás la prueba del webhook.**

**Qué se verificó y funciona** (detalle completo en `docs/specs/08-gestion-pedidos.md` §Sincronía
2026-09-12 — verificación del webhook contra MercadoPago real): conectividad por el túnel, firma
HMAC —incluido el rechazo con 401 al alterar un byte—, filtro por tipo, consulta a la API real con
el 404 traducido a 200, y la lectura del id desde la query string. Todo lo que depende del código,
anda.

**Qué falta**: un **pago aprobado en sandbox**. Es el único tramo sin probar. No es un problema de
integración: durante la sesión el pago **entraba como producción** aunque la preferencia se crea con
credenciales `TEST-`. Al lograrlo hay que verificar cuatro cosas — notificación con `type=payment` e
id real, pedido en `paid`, stock descontado por la cantidad exacta, y `order.paid` en `audit_logs`
con `origen: mercadopago` y actor `null`.

**Estado del entorno al cerrar la sesión: NORMAL**, verificado con el chequeo del runbook —
`APP_URL=http://localhost:8080`, `public/hot` apuntando a `localhost:5173`, `cloudflared` detenido y
la web local respondiendo 200. El túnel se levantó para la prueba y se bajó al terminar.

Aun así, **no confíes en esta línea**: la sesión anterior la dejó diciendo "en modo túnel" y a los
diez minutos ya era falsa. El estado real se averigua con los tres comandos de
`docs/deployment/desarrollo-local.md` §En qué estado está el entorno ahora mismo. Para levantar el
túnel de nuevo, §Procedimiento; para bajarlo, §Volver al estado normal.

**La URL del túnel cambia en cada arranque**, así que hay que reconfigurarla en el panel de
MercadoPago cada vez. El secreto no cambia.

**Para la Spec Higiene 03 se suma un quinto punto**: un GET al webhook devuelve **405** donde la
regla 153 exige **200** — verificado con una entrega real de MercadoPago. El arreglo es chico
(aceptar GET y responder 200 a lo que no sea `payment`), pero es código y necesita spec aprobada.

### Punto de retome — cierre del 2026-09-12 (tercera jornada)

**Leer esto primero.** Estado al cortar la sesión:

- **`main` = `f529a4c`**, con la **Spec 08 completa** (08.a dominio, 08.b webhook, 08.c panel y
  despacho). **436 tests**, PHPStan nivel 8 sin errores, Pint limpio.
- **PR #24 abierto y sin mergear**: auditoría documental completa del repositorio, más los
  hallazgos de la verificación de la familia Spec 07. Es lo único pendiente de merge.
- **El alcance del MVP quedó cerrado**: WhatsApp fuera (decisión del 2026-09-12), Spec 09 opcional.

**Lo próximo, en orden**:

1. **Mergear el PR #24.**
2. **Verificar el webhook contra MercadoPago real** — es lo único que le falta al MVP. Túnel +
   `MERCADOPAGO_WEBHOOK_SECRET`, procedimiento completo en `docs/deployment/desarrollo-local.md`
   §Webhook. Decisión del dueño: se hace ahora que la Spec 08 está completa, para ver el circuito
   desde el panel en vez de mirar la base a mano.
3. **Rehacer la verificación de las specs 01, 02, 04 y calidad-onboarding** (el agente se cortó por
   accidente; ver más abajo).
4. **Escribir el borrador de Spec Higiene 03** con los cuatro hallazgos de la familia 07 que están
   documentados más abajo, y con lo que aporte el punto 3. **No se implementa nada hasta que el
   dueño lo apruebe.**

Lo anterior a esta jornada queda como referencia histórica:

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

#### Auditoría documental completa (2026-09-12)

Se revisaron los **61 documentos** del repositorio contra el código. Lo sano: cero enlaces rotos,
cero rutas citadas que no existan, **numeración global de reglas intacta (1 a 166, sin huecos ni
duplicados)**, los conteos de infraestructura coinciden (15 migraciones, 23 tablas) y el índice de
`.ai/rules` cubre todos sus archivos con globs que resuelven.

Lo corregido está en el commit de la auditoría. Lo que **queda abierto**, porque marcarlo a ciegas
sería inventar documentación:

- **Siete specs cerradas conservan ~93 casillas sin marcar**: `01` (15), `04` (20), `07` (15),
  `07.2` (13), `07.3` (10), `07.4` (10) y `calidad-onboarding` (9). Cada una hay que verificarla
  contra el código antes de marcarla — que es exactamente el trabajo de `verificador-spec-codigo`.
  La Spec Higiene 01 era el caso extremo de esto (decía *borrador* con todo implementado) y ya
  quedó corregida.

#### Verificación spec↔código del 2026-09-12 — hallazgos de la familia Spec 07

Se corrió `verificador-spec-codigo` sobre `07-checkout.md`, `07.2`, `07.3` y `07.4`. **Las 28 reglas
(101–128) tienen código que las implementa: no hay ninguna regla fantasma**, y las tres secciones de
sincronía están al día. Pero aparecieron cuatro cosas que **no** conviene dejar dormidas. Son el
insumo para un borrador de **Spec Higiene 03**, igual que Higiene 02 nació de esta misma verificación.

**H03-01 — `external_reference` y el `paymentUrl()` real no los cubre ningún test** (regla 123).
Los cinco tests que inspeccionan el payload solo afirman `auto_return`, `back_urls.success` y
`shipments`; los de `store`/`retry` bindean fakes que **sobrescriben `paymentUrl()` entero**, así que
el cuerpo de `MercadoPagoGateway::paymentUrl()` (`app/Services/MercadoPagoGateway.php:91-107`) nunca
se ejecuta en la suite. Si `'external_reference' => (string) $order->id` (`:137`) desaparece, los 436
tests siguen verdes y **cada pago aprobado cae en `webhook.order_not_found`**: el pedido queda en
`PendingPayment` para siempre, el stock nunca baja y el cliente pagó. Es la misma forma que
`revisor-entrega` bloqueó en 08.b —*probar el puerto no prueba el adaptador*—, que en la 07.4 quedó
sin corregir. **Es el más grave de los cuatro.**

**H03-02 — un producto `M2` sin `m2_por_caja` se vende a precio cero** (regla 110).
La 07.2 dice explícitamente que ese caso debe lanzar `DomainException`; el código hace
`($product->precioCajaCents() ?? 0)` en `app/Actions/PlaceOrderAction.php:97-99`, o sea **precio
cero**. `products.m2_por_caja` es nullable y **sin `CHECK`**, así que cualquier camino que no pase
por el Form Request —seeder, import, corrección en la base— lo produce. El pedido se crea con total
= envío, MercadoPago cobra el envío y la auditoría registra `subtotal_cents: 0` sin error alguno.
**No hay sincronía que enmiende esto**: el código y la spec dicen cosas distintas, y la diferencia es
plata. Hay que decidir cuál de las dos gana.

**H03-03 — `shipping_address` valida `max:500` contra una columna `varchar(255)`**
(`app/Http/Requests/Checkout/StoreCheckoutRequest.php:25` vs
`database/migrations/2026_09_03_162109_create_orders_table.php:22`). Una dirección de 256 a 500
caracteres pasa el 422, entra a la Action y revienta con `QueryException`; `CheckoutController::store`
solo captura `DomainException`, así que el cliente ve un **500 con el carrito lleno**. Se arregla
subiendo la columna o bajando la validación.

**H03-04 — la vista `success` omite 4 de los 7 campos de línea que enumera la regla 119**
(`resources/views/checkout/success.blade.php:20-25`: faltan `product_codigo`, `marca`,
`precio_unitario_cents` y `specs`). A diferencia de otras desviaciones de esta familia, **esta no
está anotada en ninguna sincronía**. Es la única pantalla que le queda al cliente del pedido: ante un
reclamo por precio no hay qué mostrarle.

**Deuda de segundo orden** (implementado y correcto, pero sin test que lo proteja): el payload del
audit `order.created` solo verifica 2 de 4 claves y no el `actor null`; `OrderStatus::values()` no se
usa en ningún lado; de los tres `CHECK >= 0` de `orders` solo se testea uno; nada verifica que
`orders.shipping_cp` conserve el `0123`; el `trim` de `prepareForValidation` no se ejercita por HTTP;
y el `find` + redirect que HIG-09 eligió (regla 117) no tiene test, así que un "arreglo" a
`findOrFail` pasaría sin ruido.

#### Verificación pendiente: specs 01, 02, 04 y calidad-onboarding

**El agente que las estaba verificando se cortó por accidente antes de producir resultados.** Hay
que volver a correr `verificador-spec-codigo` sobre esas cuatro. Es la otra mitad de la auditoría
documental y el origen de las ~93 casillas sin marcar (`01`: 15, `04`: 20, `calidad-onboarding`: 9;
la `02` no tiene casillas pendientes pero nunca se verificó).

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
