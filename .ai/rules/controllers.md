---
paths:
  - 'app/Http/Controllers/**'
---

# Controllers

## Filtros por specs JSONB en PostgreSQL
`where('specs->clave', valor)` funciona en Postgres (el grammar traduce `->` a `->>`), pero `pluck('specs->clave')` NO (devuelve stdClass y rompe con "Undefined property"). Para listar valores distintos de una clave de specs usar: `->whereNotNull('specs->clave')->distinct()->selectRaw('"specs"->>? as valor', [$clave])->pluck('valor')`.
