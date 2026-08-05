---
paths:
  - 'app/Http/Requests/Categorias/**'
---

# Categorias

## Categorías planas: unicidad global por query (revisión Spec 02, 2026-08-05)
Las categorías son **planas** (sin `parent_id`, sin jerarquía) por decisión del dueño del 2026-08-05. El `name` y el `slug` son **únicos en todo el catálogo**: se validan con `Rule::unique('categories', 'name'|'slug')` en `StoreCategoryRequest`/`UpdateCategoryRequest` (update con `->ignore($this->route('category'))`). NO reintroducir `parent_id`, validación de profundidad ni unicidad "entre hermanos". `CategorySlugGenerator.uniqueFor(name, slug, exceptId)` genera slug único global con sufijo `-2`, `-3` si colisiona; `exceptId` excluye la categoría misma en updates. La columna `parent_id` ya se eliminó (migración 2026_08_05_110000). El borrado con productos lo protege `DeleteCategoryAction`.
