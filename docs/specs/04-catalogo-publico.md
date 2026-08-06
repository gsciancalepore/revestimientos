# Spec 04 — Catálogo público

- **Estado**: aprobada (2026-08-05)
- **Fuentes**: Spec 00 (reglas 1–12, 27), Spec 02 (categorías planas, reglas
  43–54), Spec 03 (productos, reglas 55–68), ADR-003 (m², cajas, dinero),
  visión (objetivo "mostrar stock real" y calculadora), decisiones del dueño
  (2026-08-05): slug de producto, filtros completos, sin stock visible con
  badge, home con destacados con oferta

## Objetivo

El **catálogo público** (dominio Products): home con navegación por categorías y
destacados con oferta, listados con filtros y búsqueda, y ficha de producto con
calculadora m²→cajas (solo modo m²), stock visible y ofertas. Es la cara de venta
web del negocio; el carrito y el checkout se implementan en las Specs 05–07.

## Contexto

- El cliente web es **anónimo** (Spec 00, regla 27): el catálogo se navega sin
  login, fuera del layout del panel. La web hoy muestra la vista `welcome` del
  skeleton, que esta spec reemplaza.
- Las categorías son **planas** (Spec 02): Porcelanatos, Cerámicas, Pastinas,
  Adhesivos y las que cree el admin, con `slug` (regla 50) pensado para las URLs
  del catálogo público.
- Los productos tienen **dos modos de venta** (Spec 03, reglas 57–60): por m²
  (precio por m², stock en cajas, calculadora m²→cajas) y por unidad (precio por
  bolsa/pieza, stock en unidades, sin calculadora ni desperdicio).
- El rubro vende cerámicas, porcelanatos, pastinas, adhesivos y accesorios; la
  **oferta** es un precio promocional con precio de lista tachado (Spec 00,
  regla 6; Spec 03, regla 64).
- Decisión del dueño (2026-08-05): los productos **sin stock se mantienen
  visibles** marcados como "Sin stock" (regla 63: "puede seguir visible a
  criterio del negocio"); en este rubro es la práctica común.

## Reglas de negocio (continúa la numeración de las Specs 00–03)

69. El **catálogo público** es abierto y anónimo: se navega sin autenticación y
    fuera del panel (no usa el layout `layouts/app` del admin).
70. Solo se publican productos **activos** (`activo = true`, regla 63/67). Los
    inactivos no aparecen en home ni en listados, y su ficha responde 404.
71. El producto tiene un **slug** público (nueva columna `slug`, única en todo
    el catálogo): se auto-genera del nombre (`Str::slug`), con sufijo `-2`,
    `-3`… si colisiona, y es **editable por el admin** (mismo patrón que las
    categorías, regla 50). Es la URL de la ficha: `/productos/{slug}`.
72. La **home** muestra la navegación por categorías (en orden `sort_order`,
    regla 51) y una sección de **destacados con oferta activa** (productos
    activos con oferta).
73. La **ficha de producto** muestra: nombre, marca, categoría, descripción,
    imagen(es), specs de la familia, precio según `unidad_venta`, oferta si
    existe (precio de lista tachado + % de descuento, regla 64) y el **stock**
    en la unidad de venta ("Quedan N cajas" en modo m²; "Quedan N unidades" en
    modo unidad).
74. Un producto **sin stock** (0 cajas o 0 unidades) se muestra igual, con el
    badge **"Sin stock"** (decisión del dueño 2026-08-05; regla 63) y sin acción
    de compra.
75. La **calculadora m²→cajas** se muestra solo en productos modo **m²** (reglas
    8–12, ADR-003): el cliente ingresa **dimensiones (largo × ancho en cm)** o
    los **m² directamente**, y opcionalmente activa el **10 % de desperdicio**;
    muestra los m², las cajas necesarias (`ceil(m² / m²_por_caja)`, regla 9) y,
    con desperdicio, los m² a cubrir y las cajas resultantes (regla 12). La
    calculadora **no agrega al carrito** (el carrito es Spec 05/06).
76. Los **listados** del catálogo se filtran por: **categoría** (navegación),
    **ofertas** (solo productos con oferta activa), **marca**, **atributos de
    specs por familia** (claves según la categoría, con los valores presentes) y
    **búsqueda por texto**. Los filtros son **combinables** entre sí.
77. Los **filtros por specs** se ofrecen según la familia de la categoría
    (`ProductSpecs`): solo las claves de la categoría (medida, color, acabado,
    espesor, rectificado, rendimiento, peso…) y solo las que tengan valores en
    los productos publicados.
78. La **búsqueda por texto** hace coincidencia parcial (ILIKE) sobre **nombre,
    código y marca**.
79. **Oferta activa** = `precio_oferta_cents` no nulo **y menor que**
    `precio_cents` (regla 64: es un precio promocional). Un precio de oferta
    mayor o igual al de lista no es oferta: se muestra el precio normal.
80. Los listados respetan el orden de las categorías (`sort_order`) y, dentro de
    una categoría, los productos se ordenan **por nombre** (ascendente), con
    paginación en grilla (12 por página).

## Matriz de permisos

El catálogo es **público**: no hay roles. Todos los accesos están abiertos.

| Acción | Público |
|---|---|
| Ver home (`/`) | ✓ |
| Ver listado y filtros (`/catalogo`, `/categorias/{slug}`, `/ofertas`) | ✓ |
| Buscar productos | ✓ |
| Ver ficha de producto (`/productos/{slug}`) | ✓ (404 si inactivo) |
| Ver calculadora m²→cajas | ✓ (solo modo m²) |
| Agregar al carrito | — (Spec 05/06) |

## Casos borde

- Slug duplicado al crear/editar → colisión con otro producto: al auto-generar
  se agrega sufijo `-2`, `-3`…; si el admin edita el slug a uno ya existente →
  error de validación.
- Categoría sin productos activos → listado vacío con mensaje; la categoría
  sigue visible en la navegación (no 404).
- Familia sin valores para un filtro de specs → el filtro no se ofrece.
- Búsqueda o filtros sin resultados → mensaje "No se encontraron productos".
- Oferta con `precio_oferta_cents` ≥ `precio_cents` → no es oferta activa (se
  muestra el precio de lista).
- Calculadora con largo/ancho o m² en 0 o vacíos → estado vacío, sin resultado.
- Producto modo unidad → sin calculadora, sin desperdicio; la cantidad se
  expresa en unidades.
- Producto inactivo por URL directa → 404 (regla 70).
- Productos con `imagenes` vacías → placeholder de imagen en cards y ficha.
- Migración `slug`: backfill de los productos existentes (generar slug del
  nombre con unicidad) para no dejar productos sin URL.

## Criterios de aceptación

- [ ] La home muestra las categorías (orden `sort_order`) y destacados con
      oferta activa.
- [ ] Solo productos activos aparecen en home, listados y ficha; un inactivo por
      URL directa responde 404.
- [ ] La ficha se accede por `/productos/{slug}`; el slug es único, se
      auto-genera del nombre (colisión → sufijo) y es editable por el admin.
- [ ] La ficha muestra specs de la familia, precio según `unidad_venta`, oferta
      con precio tachado + % OFF y stock ("Quedan N cajas"/"unidades").
- [ ] Un producto sin stock se muestra con el badge "Sin stock".
- [ ] La calculadora aparece solo en modo m²: largo × ancho o m², desperdicio
      opcional del 10 %, resultado en cajas (ceil) y m² a cubrir con desperdicio.
- [ ] Los listados filtran por categoría, ofertas, marca, specs por familia y
      búsqueda por texto; los filtros son combinables.
- [ ] La búsqueda hace coincidencia parcial en nombre, código y marca.
- [ ] Los listados se ordenan por categoría/nombre y pagan en grillas de 12.
- [ ] El catálogo se navega sin login (no usa el layout del panel).
- [ ] Pint, PHPStan nivel 8 y Pest en verde; CI alineado.

## Decisiones arquitectónicas

- **Slug de producto**: migración `add_slug_to_products` (índice único) con
  backfill; generación por `ProductSlugGenerator` (mismo patrón que
  `CategorySlugGenerator`, sufijos `-2`, `-3`…); el slug entra en las Actions de
  la Spec 03 (`CreateProductAction`/`UpdateProductAction`) y en los Form
  Requests (vacío = auto-generar; editable por el admin). `Product` expone
  `routeKeyName()` → `slug` para el route-model binding.
- **Consultas del catálogo**: scopes en `Product` (`activo()`, `conOferta()`,
  `deCategoria()`, `buscar()`, `porMarca()`, `specsValor()`) + `with('category')`
  para evitar N+1. Los filtros por `specs` usan operadores JSONB de PostgreSQL
  (`specs->>'clave' = valor`); el índice GIN sobre `specs` se evalúa en la
  implementación según el volumen (no se asume).
- **`M2Calculator`**: servicio puro (bcmath, ADR-003) con
  `m2DesdeDimensiones(largo, ancho)`, `aplicarDesperdicio(m2, pct)` y
  `cajasNecesarias(m2, m2_por_caja)`. Único lugar de las reglas de redondeo
  (reglas 9–12); lo reutiliza el carrito (Spec 05). *Nota*: ADR-003 ubica el
  redondeo en el "caso de uso del carrito (Spec 05)"; se propone revisar esa
  frase para señalar que la calculadora vive en `M2Calculator`. **Ese cambio de
  ADR NO lo implementa el agente**: queda como tarea del dueño aparte.
- **Controlador público**: `CatalogController` delgado (home, catálogo,
  categoría, ofertas, ficha) que delega las consultas en los scopes; sin reglas
  de negocio. Reemplaza la ruta `GET /` de `welcome`.
- **Rutas públicas** (sin middleware de auth) en `routes/web.php`:
  `/`, `/catalogo`, `/categorias/{categoria:slug}`, `/ofertas`,
  `/productos/{producto:slug}` (nombres `catalogo.*`).
- **Layout público** nuevo `layouts/site` (Blade + Tailwind 4 + Alpine): header
  con navegación de categorías, buscador y enlace a ofertas; footer. Vistas:
  `public/home`, `public/catalogo` (grilla con filtros y paginación) y
  `public/producto` (ficha con calculadora en Alpine). Componente reusable de
  **card de producto** (imagen, nombre, marca, precio, oferta, badge "Sin
  stock", unidad de venta).
- **Sin carrito** en esta spec: la ficha solo calcula y muestra; el botón de
  compra llega con las Specs 05/06.

## Tareas técnicas

- [ ] Migración `add_slug_to_products` (índice único + backfill de productos
      existentes).
- [ ] `ProductSlugGenerator` + integración del slug en
      `StoreProductRequest`/`UpdateProductRequest` y en
      `CreateProductAction`/`UpdateProductAction`.
- [ ] `Product`: `routeKeyName()` y scopes del catálogo (`activo`,
      `conOferta`, `deCategoria`, `buscar`, `porMarca`, `specsValor`).
- [ ] Servicio `M2Calculator` (bcmath).
- [ ] `CatalogController` + rutas públicas (`/`, `/catalogo`,
      `/categorias/{slug}`, `/ofertas`, `/productos/{slug}`); reemplazo de la
      vista `welcome`.
- [ ] Layout público `layouts/site` + vistas `public/home`, `public/catalogo`
      (filtros combinables, paginación 12) y `public/producto` (calculadora
      Alpine) + componente card de producto.
- [ ] Tests Pest: `tests/Feature/Catalogo/` (home, solo activos, ficha por slug,
      filtros combinables, búsqueda, sin stock, 404 inactivo, slug único) y
      `tests/Unit/M2CalculatorTest` (ceil, desperdicio 10 %, dimensiones, casos
      borde). Los tests de vistas requieren assets de Vite.
- [ ] Verificación de calidad: pint, PHPStan nivel 8, Pest, CI.
- [ ] Actualizar `arquitectura.md`, `ubiquitous-language.md` (catálogo público,
      ficha, destacados, slug de producto) y `roadmap.md` (cierre de Spec 04).
      La revisión de ADR-003 (ubicación del redondeo en `M2Calculator`) queda
      **fuera del alcance del agente**: es una tarea del dueño aparte.

## Nota de handoff para el agente implementador

La spec está **aprobada** (2026-08-05). Implementar con TDD en este orden:

1. Migración `add_slug_to_products` (índice único + backfill).
2. `ProductSlugGenerator` e integración del slug en Requests y Actions de la
   Spec 03.
3. `Product`: `routeKeyName()` + scopes del catálogo.
4. `M2Calculator` (servicio puro con bcmath).
5. `CatalogController` + rutas públicas (reemplazar la vista `welcome`).
6. Layout público `layouts/site` + vistas + componente card.
7. Tests Pest (`tests/Feature/Catalogo/`, `tests/Unit/M2CalculatorTest`).

Reglas para esta tarea:

- **No editar** `docs/specs/`, `docs/adr/` ni `docs/roadmap.md` (incluida la
  nota de ADR-003: no se toca).
- Seguir el orden de lectura y las reglas de `AGENTS.md` y `.ai/rules` (hacer
  `grep` en `.ai/rules` por keywords: producto, m2, specs, rutas, tests).
- Tras editar PHP: `make format`; validar con `make lint` → `make stan` →
  `make test`.
- Los tests que renderizan vistas requieren assets de Vite (`make npm-dev` o
  `make npm-build`); correr una sola suite de Pest a la vez.
- Registrar con `record-rule` cualquier regla durable nueva descubierta durante
  la implementación (p. ej. filtros JSONB, backfill de slugs).
