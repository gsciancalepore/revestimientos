# Spec 02 — Panel administrativo (layout) + Categorías

- **Estado**: cerrada (2026-08-05); **revisada (2026-08-05)** tras la decisión del
  dueño de volver a **categorías planas** (sin jerarquía) para el modelo de
  productos con dos modos de venta y atributos por familia (Spec 03)
- **Fuentes**: Spec 00 (regla 4), referencia del negocio store409.com.ar
  (estructura real de categorías y navegación), decisiones del dueño (2026-08-05),
  investigación del mercado (LECER, Creta Disegno, Taffel, Cerro Negro, A Todo
  Mosaico): el patrón argentino dominante agrupa bajo una raíz paraguas, pero el
  dueño eligió **categorías planas** (Porcelanatos, Cerámicas, Pastinas,
  Adhesivos); el "look" (simil madera, cemento, mármol/piedra) y formato/marca/color
  quedan como atributos o filtros (Spec 03)

## Objetivo

Dar al panel administrativo un **layout con sidebar** preparado para las specs
funcionales (usuarios hoy; categorías, productos y pedidos después), y el
**CRUD de categorías** jerárquicas del catálogo (dominio Products), que es la
base de navegación del catálogo público (Spec 04).

## Contexto

- El panel es **interno** (Spec 01): se accede con usuario interno desde
  `/admin`.
- La categoría es la agrupación de navegación del catálogo (Spec 00, regla 4).
  Tras la revisión de 2026-08-05, las categorías son **planas**: una lista de
  raíces, sin subcategorías. La referencia del negocio (store409) muestra que
  **cerámica y porcelanato son dos familias de producto distintas**: Cerámicas y
  Porcelanatos son categorías separadas (no una subcategoría de la otra).
- **Ofertas, marca, acabado y calidad NO son categorías**: son atributos/flag
  del producto y se definen en la Spec 03 (Productos). Ofertas se filtra por el
  flag de oferta del producto, no por una categoría.

## Reglas de negocio (continúa la numeración de las Spec 00 y 01)

43. El panel tiene un **layout con sidebar** lateral y una barra superior. El
    sidebar muestra las secciones según el rol del usuario; cada sección aparece
    solo si el usuario tiene permiso para esa área.
44. Secciones del sidebar: **Dashboard** (todos los roles), **Usuarios** (solo
    admin), **Categorías** (solo admin), y **placeholders deshabilitados** de
    Productos, Pedidos y Ventas WhatsApp (visibles pero no accesibles, como
    recordatorio de las specs 03/07/08). El dashboard mantiene su contenido
    placeholder en esta spec.
45. Las categorías son **planas**: una lista de categorías raíz, **sin
    subcategorías** (decisión del dueño 2026-08-05). No existe `parent_id` ni
    árbol.
46. Categorías base del negocio (creadas por el seeder): **Porcelanatos,
    Cerámicas, Pastinas, Adhesivos**. El admin puede crear otras categorías.
47. El **producto** (Spec 03) se asigna a **una** categoría.
48. **Ofertas, Marca, Acabado y Calidad no son categorías**: son atributos del
    producto (Spec 03). La sección "Ofertas" del catálogo es un filtro por
    productos con oferta activa, no una categoría.
49. El **nombre** de la categoría es **único** en todo el catálogo.
50. La categoría tiene un **slug** legible, **auto-generado del nombre** y
    **editable por el admin**, **único**. Se usa en las URLs del catálogo
    público (Spec 04).
51. Las categorías se **ordenan manualmente** (`sort_order`) en el listado del
    panel y en la navegación del catálogo.
52. Solo el **admin** crea, edita y borra categorías; vendedor y depósito no
    (403).
53. Una categoría **solo se borra si está vacía**: sin productos. No existe
    borrado en cascada; borrar una categoría con productos se rechaza con un
    mensaje claro (la validación de productos se activa cuando exista la tabla
    `products`, Spec 03).
54. Las acciones sobre categorías **no se auditan** (ADR-004 reserva la
    auditoría para precios, stock, pagos y roles).

## Matriz de permisos

| Acción | admin | vendedor | depósito |
|---|---|---|---|
| Ver Dashboard | ✓ | ✓ | ✓ |
| Ver sección Usuarios | ✓ | — | — |
| Ver listado de categorías | ✓ | — | — |
| Crear / editar / borrar categorías | ✓ | — | — |
| Acceder por URL a /admin/categorias | ✓ | 403 | 403 |
| Ver placeholders Productos/Pedidos/Ventas | (deshabilitados para todos) | (deshabilitados para todos) | (deshabilitados para todos) |

## Casos borde

- Nombre duplicado en el catálogo → error de validación con mensaje claro.
- Slug duplicado → error; el slug auto-generado puede ajustarse por el admin.
- Borrar una categoría con productos → rechazado (la validación de productos se
  activa cuando exista la tabla `products`, Spec 03).
- Un producto asignado a una categoría impide borrarla (regla 53).

## Criterios de aceptación

- [x] El panel muestra el sidebar con Dashboard, Usuarios y Categorías según el
      rol, y placeholders deshabilitados de Productos, Pedidos y Ventas WhatsApp.
- [x] vendedor/depósito ven Dashboard (y placeholders) pero no Usuarios ni
      Categorías; si acceden por URL a /admin/categorias reciben 403.
- [x] Solo admin accede a /admin/categorias (index, create, edit).
- [x] Admin crea una categoría (plana); el seeder crea las 4 categorías base
      (Porcelanatos, Cerámicas, Pastinas, Adhesivos).
- [x] No existe la noción de subcategorías (sin `parent_id`).
- [x] Nombre duplicado en el catálogo → error.
- [x] Slug auto-generado del nombre y editable; duplicado → error.
- [x] El listado respeta el orden manual (`sort_order`).
- [x] Editar permite cambiar nombre, slug y orden.
- [x] Borrar una categoría vacía → OK; con productos → error claro.
- [x] `CategoriesSeeder` crea la estructura base del negocio de forma idempotente
      (4 categorías planas).
- [x] Pint, PHPStan nivel 8 y Pest en verde; CI alineado.

## Decisiones arquitectónicas

- Modelo **`Category` simple** (sin jerarquía): `name`, `slug`, `sort_order`.
  La revisión 2026-08-05 **elimina `parent_id`** y sus relaciones
  `parent`/`children` (migración para quitarlo).
- **Acciones**: `CreateCategoryAction`, `UpdateCategoryAction`,
  `DeleteCategoryAction` (la regla de borrado con productos vive en la Action,
  lanza `DomainException`).
- **Autorización**: `CategoryPolicy` (solo admin) + middleware `role:admin` en
  las rutas, mismo patrón que `usuarios` (Spec 01).
- **Rutas**: `Route::resource('categorias', CategoryController::class)
  ->parameters(['categorias' => 'category'])` (la regla registrada de
  route-model binding exige que el parámetro singular matchee el tipo
  `Category $category`).
- **Validación**: Form Requests `StoreCategoryRequest`/`UpdateCategoryRequest`
  con unicidad global de `name` y `slug` (`Rule::unique`).
- **Sidebar**: se reescribe `layouts/navigation` y `layouts/app` (grid
  sidebar + contenido); las secciones se renderizan según
  `Auth::user()->role()`.
- **Sin auditoría** para categorías (ADR-004).

## Tareas técnicas

- [x] Migración `create_categories_table` (name, slug, sort_order, timestamps)
      y migración **`2026_08_05_110000` que elimina `parent_id`** y su FK/índice
      (categorías planas, revisión 2026-08-05).
- [x] Modelo `Category` (sin relaciones jerárquicas) + `CategoryFactory`.
- [x] Actions: `CreateCategoryAction`, `UpdateCategoryAction`,
      `DeleteCategoryAction`.
- [x] `CategoryPolicy` (solo admin).
- [x] `CategoryController` delgado + `StoreCategoryRequest` /
      `UpdateCategoryRequest`.
- [x] Rutas `/admin/categorias` (resource, solo admin, parámetro `category`).
- [x] Sidebar: reescritura de `layouts/navigation` + `layouts/app` con secciones
      por rol y placeholders deshabilitados.
- [x] Vistas `admin/categorias/index`, `create` y `edit` en español (listado
      plano con orden manual).
- [x] `CategoriesSeeder` idempotente (`updateOrCreate` por slug) con la
      estructura real del negocio (revisión 2026-08-05): **Porcelanatos,
      Cerámicas, Pastinas, Adhesivos** (planas).
- [x] Tests Pest `tests/Feature/Categorias/` (permisos, unicidad, slug, orden,
      borrado, seeder).
- [x] Verificación de calidad: pint, PHPStan, Pest, CI.
- [x] Actualizar `arquitectura.md`, `ubiquitous-language.md` (término
      "Categoría") y `roadmap.md`.
