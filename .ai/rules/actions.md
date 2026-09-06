---
paths:
  - 'app/Actions/*Category*.php'
  - 'app/Actions/*Product*.php'
---

# Actions

## Slug auto-generado único con sufijo
CategorySlugGenerator.uniqueFor(name, slug) genera Str::slug y si colisiona agrega sufijo -2, -3... El slug puede editarse por el admin en el formulario (vacío = regenerar). Las categorías son **planas** (revisión Spec 02, 2026-08-05): la unicidad es global, no entre hermanos.

## Borrado protegido de categorías y productos
DeleteCategoryAction lanza DomainException si la categoría tiene productos (regla 53). El check "producto con pedidos" (regla 67) está ACTIVO desde 2026-09-06: DeleteProductAction lanza DomainException si `Product::tienePedidos()` (no se borra, se desactiva) y UpdateProductAction la lanza si cambia `unidad_venta` con pedidos históricos (la `cantidad` congelada en order_lines se leería en otra unidad). ProductController captura ambas y devuelve `withErrors`, nunca 500 — mismo patrón que CategoryController. La baja por desactivación (`activo = false`) se audita.

## Cambios de precio, stock y baja de productos: auditar
UpdateProductAction registra en `audit_logs` (AuditRecorder) `product.price_changed` (anterior→nuevo), `product.stock_changed` (anterior→nuevo) y `product.deactivated` (Spec 03 regla 68, ADR-004). NO crear ChangeProductPriceAction/UpdateProductStockAction separadas: el update de producto los cubre.
