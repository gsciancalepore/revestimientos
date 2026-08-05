---
paths:
  - 'app/Actions/*Category*.php'
  - 'app/Actions/*Product*.php'
---

# Actions

## Slug auto-generado único con sufijo
CategorySlugGenerator.uniqueFor(name, slug) genera Str::slug y si colisiona agrega sufijo -2, -3... El slug puede editarse por el admin en el formulario (vacío = regenerar). Las categorías son **planas** (revisión Spec 02, 2026-08-05): la unicidad es global, no entre hermanos.

## Borrado protegido de categorías y productos
DeleteCategoryAction lanza DomainException si la categoría tiene productos (regla 53). DeleteProductAction borra el producto; el check "producto con pedidos" (regla 67: no borrar ni cambiar unidad_venta) se activa cuando exista la tabla `orders` (Spec 05). La baja por desactivación (`activo = false`) se audita.

## Cambios de precio, stock y baja de productos: auditar
UpdateProductAction registra en `audit_logs` (AuditRecorder) `product.price_changed` (anterior→nuevo), `product.stock_changed` (anterior→nuevo) y `product.deactivated` (Spec 03 regla 68, ADR-004). NO crear ChangeProductPriceAction/UpdateProductStockAction separadas: el update de producto los cubre.
