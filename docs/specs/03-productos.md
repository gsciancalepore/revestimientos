# Spec 03 — Productos

- **Estado**: cerrada (2026-08-05)
- **Fuentes**: Spec 00 (reglas 1–12), Spec 02 (categorías planas, revisión
  2026-08-05), decisiones del dueño (2026-08-05): dos modos de venta (m² y
  unidad), atributos híbridos (columnas tipadas + `specs` JSONB), categorías
  planas, código único por producto

## Objetivo

El **CRUD de productos** en el panel administrativo (`/admin/productos`): alta,
edición, precio, stock, imágenes y atributos. Es la base del catálogo público
(Spec 04) y de los cálculos de venta (Specs 05–07).

## Contexto

- El panel es **interno** (Spec 01); solo el admin gestiona productos
  (Spec 00, regla 31).
- Las categorías son **planas** (Spec 02 revisada): una lista de raíces
  (Porcelanatos, Cerámicas, Pastinas, Adhesivos y las que el admin cree). No hay
  jerarquía.
- El rubro vende de **dos formas** (decisión del dueño 2026-08-05):
  - **Por m²**: cerámicas y porcelanatos. El precio se comunica por m², el
    despacho es en cajas enteras, el stock es en cajas.
  - **Por unidad**: pastinas, adhesivos, zócalos/perfiles y otros. El precio es
    por bolsa o pieza, el stock es en unidades; no existe el concepto de "caja"
    ni de desperdicio.
- **Ofertas, marca, acabado y calidad NO son categorías**: son atributos del
  producto (Spec 00, regla 48). "Ofertas" en el catálogo es un filtro por
  productos con oferta activa.

## Reglas de negocio (continúa la numeración de las Specs 00–02)

55. Un **producto** tiene campos base tipados y un mapa de **atributos por
    familia** (Specs). Campos base (columnas tipadas de `products`): nombre,
    categoría, marca, código, precio, `unidad_venta`, m² por caja (solo modo
    m²), stock, activo, imágenes.
56. Cada producto pertenece a **una categoría plana** (Spec 02). La categoría
    con productos **no puede borrarse** (regla 53).
57. `unidad_venta` es un enum de dos valores: **`m2`** (cerámicas,
    porcelanatos) y **`unidad`** (pastinas, adhesivos, perfiles). Determina la
    semántica de precio, stock y cálculo de la venta.
58. El **precio se guarda en una sola columna** `precio_cents` cuyo
    significado lo da `unidad_venta`: precio **por m²** si es `m2`, o **por
    bolsa/pieza** si es `unidad`.
59. Modo `m2`:
    - El producto define `m2_por_caja` (requerido).
    - El **precio por caja se deriva**: `precio_caja_cents =
      round(precio_cents × m2_por_caja)` con bcmath (ADR-003); no se persiste.
    - El **stock se expresa en cajas** (entero).
    - Aplica la calculadora m²→cajas con ceil y el desperdicio opcional del
      10 % (Spec 00, reglas 8–12).
60. Modo `unidad`:
    - El producto **no define** `m2_por_caja` (queda NULL).
    - No hay precio por caja, ni calculadora m²→cajas, ni desperdicio.
    - El **stock se expresa en unidades** (bolsas o piezas, entero).
    - La cantidad del carrito se pide en unidades (Spec 05).
61. El **código** es único en todo el catálogo (SKU, formato libre tipo
    `ILV-12345`); no se puede duplicar.
62. Los **atributos por familia** se guardan en `specs` (JSONB) con **claves
    validadas por categoría** (ver tabla de Specs). Claves no permitidas para la
    familia de la categoría → error de validación.
63. Un producto **sin stock** (0 cajas o 0 unidades) no puede comprarse; puede
    seguir visible en el catálogo ("Sin stock") a criterio del negocio
    (Spec 00, regla 5).
64. La **oferta** es un precio promocional con % de descuento sobre el precio de
    lista; se muestra con el precio de lista tachado (Spec 00, regla 6).
65. Los atributos **comerciales** (precio, oferta, stock, activo) y los
    **atributos de producto** (marca, medida, color, acabado…) persisten juntos
    en el mismo registro; el modelo híbrido separa solo qué se calcula/filtra
    (tipado) de qué se describe (Specs JSONB).
66. Solo el **admin** crea, edita y borra productos; vendedor y depósito no
    (403).
67. Un producto **solo se borra si no tiene pedidos**; si tiene historial de
    ventas, en su lugar se **desactiva** (`activo = false`) conservando el
    historial. No existe borrado en cascada.
68. Los cambios de **precio** y **stock** de productos se **auditan**
    (ADR-004): se registra actor, valor anterior → nuevo, IP y fecha. La baja
    por desactivación también se audita.

## Specs de atributos por familia

Las claves permitidas en `specs` dependen de la categoría del producto. Todas
las claves son opcionales salvo que se indique. El admin define el conjunto de
claves al crear la categoría (Spec 02 revisada); valores por defecto sugeridos
para las categorías base del seeder:

| Categoría | Claves de `specs` (ejemplo) |
|---|---|
| Porcelanatos | medida (formato), color, acabado (terminación), espesor, rectificado (bool), piezas_por_caja, uso, aplicacion |
| Cerámicas | medida (formato), color, acabado (terminación), espesor, piezas_por_caja, uso, aplicacion |
| Pastinas | color, rendimiento (m² por bolsa), peso (kg por bolsa) |
| Adhesivos | rendimiento (m² por bolsa/kg), tiempo de fraguado, peso (kg por bolsa) |
| Zócalos y Perfiles | material, largo (m), color |

> El `m2_por_caja` es columna tipada (se calcula con ella), NO una clave de
> `specs`. Lo mismo `marca`, `codigo`, `precio`, `stock` y `unidad_venta`.

## Matriz de permisos

| Acción | admin | vendedor | depósito |
|---|---|---|---|
| Ver listado de productos | ✓ | — | — |
| Crear / editar / borrar productos | ✓ | — | — |
| Editar precio / stock de productos | ✓ | — | — |
| Acceder por URL a /admin/productos | ✓ | 403 | 403 |

## Casos borde

- Cambiar `unidad_venta` de `m2` a `unidad` (o al revés) en un producto con
  historial → se rechaza o se exige confirmación explícita (el precio cambia de
  semántica). En el MVP: si el producto tiene pedidos, se bloquea el cambio.
- `codigo` duplicado → error de validación con mensaje claro.
- `specs` con clave no permitida para la familia → error; clave permitida con
  valor vacío → se elimina del JSON.
- Producto modo `m2` sin `m2_por_caja` → error al guardar.
- Producto modo `unidad` con `m2_por_caja` cargado → se ignora/limpia.
- Borrar un producto con pedidos → rechazado; se sugiere desactivar.
- Borrar una categoría con productos → rechazado (regla 53, validación activa
  ahora que existe la tabla `products`).

## Criterios de aceptación

- [x] Solo admin accede a /admin/productos (index, create, edit); vendedor y
      depósito reciben 403.
- [x] Admin crea un producto modo `m2` (con m² por caja) y uno modo `unidad`.
- [x] El precio se guarda en centavos y se muestra por m² o por unidad según
      `unidad_venta`.
- [x] `codigo` duplicado → error.
- [x] `specs` válido según la familia; claves no permitidas → error.
- [x] Producto con 0 stock no comprable (validación lista para Spec 05).
- [x] Cambio de `unidad_venta` en producto con pedidos → bloqueado (check
      preparado; se activa con la tabla `orders`, Spec 05).
- [x] Cambios de precio y stock quedan en la auditoría.
- [x] `CategoriesSeeder` crea las 4 categorías base planas y define sus claves
      de `specs`.
- [x] Pint, PHPStan nivel 8 y Pest en verde; CI alineado.

## Decisiones arquitectónicas

- Modelo **`Product`** con columnas tipadas (`category_id`, `name`, `marca`,
  `codigo`, `descripcion`, `precio_cents`, `precio_oferta_cents`,
  `unidad_venta` enum, `m2_por_caja`, `stock`, `activo`, `imagenes` jsonb,
  `specs` jsonb) y FK a `categories` con `restrictOnDelete`.
- **`unidad_venta` como enum** `ProductSaleUnit` (`M2`, `Unidad`) en
  `app/Enums`, no string suelto.
- **`specs` JSONB** con validación por familia en los Form Requests (claves
  permitidas según la categoría, mapeadas en `ProductSpecs::ALLOWED` por slug de
  categoría); el tipo del valor se valida en la misma regla.
- **Acciones**: `CreateProductAction`, `UpdateProductAction`,
  `DeleteProductAction`. Los cambios de precio y stock se hacen dentro de
  `UpdateProductAction`, que registra `product.price_changed` /
  `product.stock_changed` / `product.deactivated` en la auditoría.
- **Autorización**: `ProductPolicy` (solo admin) + middleware `role:admin` en
  las rutas, mismo patrón que `categorias` (Spec 02) y `usuarios` (Spec 01).
- **Rutas**: `Route::resource('productos', ProductController::class)
  ->parameters(['productos' => 'product'])`.
- **Auditoría**: precios, stock y baja de productos (ADR-004); reusa
  `AuditRecorder` de la Spec 01.
- **Precios en centavos** y cálculos con bcmath (ADR-003).

## Tareas técnicas

- [x] Migración `create_products_table` (columnas tipadas, FK a categories con
      `restrictOnDelete`, índice único sobre `codigo`, cast de `specs` y
      `imagenes` a jsonb) y migración que elimina `parent_id` de `categories`
      (Spec 02 revisada).
- [x] Enum `ProductSaleUnit` + `ProductFactory` con estados por modo de venta.
- [x] Actions: `CreateProductAction`, `UpdateProductAction`,
      `DeleteProductAction` (precios, stock y baja auditados; `ChangeProductPrice`
      y `UpdateProductStock` se hacen dentro de `UpdateProductAction`, que
      registra `product.price_changed` / `product.stock_changed` / `product.deactivated`).
- [x] `ProductPolicy` (solo admin).
- [x] `ProductController` delgado + `StoreProductRequest` /
      `UpdateProductRequest` (validación de `specs` por familia vía `ProductSpecs`).
- [x] Rutas `/admin/productos` (resource, solo admin, parámetro `product`).
- [x] Sidebar: habilitar la sección **Productos** (Spec 02 la tenía como
      placeholder deshabilitado).
- [x] Vistas `admin/productos/index`, `create` y `edit` en español (formulario
      adaptado al modo de venta y a los `specs` de la familia).
- [x] `CategoriesSeeder` plano (Spec 02 revisada) define las 4 categorías base
      y sus claves de `specs`.
- [x] Tests Pest `tests/Feature/Productos/` (permisos, modos de venta, código
      único, validación de `specs`, auditoría, borrado vs desactivación,
      seeder).
- [x] Verificación de calidad: pint, PHPStan, Pest, CI.
- [x] Actualizar `arquitectura.md`, `ubiquitous-language.md`, `roadmap.md`,
      `docs/specs/00-dominio.md` y `docs/adr/ADR-003` / `ADR-005`.
