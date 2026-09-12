---
paths:
  - 'app/Models/Product.php'
  - 'app/Http/Requests/Productos/**'
  - 'app/Actions/*Product*.php'
  - 'app/Services/ProductSpecs.php'
  - 'app/Enums/ProductSaleUnit.php'
---

# Productos

## Modelo híbrido: columnas tipadas + specs JSONB (Spec 03)
`products` usa columnas tipadas solo para lo que se calcula o filtra: `category_id`, `name`, `marca`, `codigo` (único), `descripcion`, `precio_cents`, `precio_oferta_cents`, `unidad_venta` (enum `ProductSaleUnit`: `M2`|`Unidad`), `m2_por_caja` (nullable), `stock`, `activo`, `imagenes` (jsonb), `specs` (jsonb). El resto (medida, color, acabado, rectificado, rendimiento, peso, tiempo de fraguado…) vive en `specs` JSONB con **claves validadas por familia** según la categoría, mapeadas en `App\Services\ProductSpecs::ALLOWED` (por slug de categoría: porcelanatos, ceramicas, pastinas, adhesivos). NO crear columnas por atributo (color, medida…) ni caer en EAV completo.

## unidad_venta define la semántica de precio/stock/cálculo
Modo `M2`: precio por m², stock en cajas, `m2_por_caja` requerido, `precio_caja = round(precio_cents × m2_por_caja)` con bcmath (ADR-003), calculadora m²→cajas con ceil y desperdicio. Modo `Unidad`: precio por bolsa/pieza, stock en unidades, sin `m2_por_caja`, sin caja ni desperdicio. Un cambio de `unidad_venta` en un producto con pedidos se bloquea (check preparado; se activa con la tabla `orders`, Spec 05). Los precios SIEMPRE en centavos (int), nunca floats.

## El binding de Product en el panel es por SLUG, no por id
`Product::getRouteKeyName()` devuelve `slug`, así que **todas** las rutas —incluidas las del panel— resuelven por slug. Una URL de test como `/admin/productos/{$producto->id}` da 404 con `ModelNotFoundException`, no un error de validación, y desde afuera parece que la request falló por otra cosa. Usar `route('productos.edit', $producto)` o el slug explícito.

## Para el análisis estático el stock NO podía ser negativo
La columna se declaró `unsignedInteger` y de ahí PHPStan infiere `int<0, max>`: cualquier `stock < 0` figuraba como comparación **siempre falsa**, es decir, la regla 145 de la Spec 08 era código muerto a ojos del tipo. PostgreSQL no tiene ese `CHECK` —la propia regla lo verificó—, así que el tipo mentía. El modelo lo corrige con `@property int $stock`. Si alguna vez se agrega el `CHECK`, hay que revisar la regla 145 antes, no la anotación.
