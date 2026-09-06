# ADR-011 — Importador de tarifas: fila-por-fila, temporal + token, sin tabla de historial

- **Estado**: aceptado (2026-09-06)
- **Contexto**: snapshot de ~1907 filas a `shipping_rates`, cuya unicidad es parcial (`UNIQUE(cp) WHERE activo = true`). Se necesita aplicación atómica e idempotente, con preview previo y sin sobrediseño.

## Decisión

1. **No se utiliza `upsert()`; se procesa fila-por-fila (búsqueda + `create`/`update`/no-op).** La semántica requerida distingue explícitamente tarifa activa, histórica inactiva, no-op y creación (reglas 131–135 de la spec fase 2); la estrategia fila-por-fila expresa esa semántica de forma clara y es compatible con ella, además de respetar `UpdateShippingRateAction`.
2. **Fila-por-fila en una `DB::transaction()`.** Para ~1900 registros el costo es despreciable (una transacción, N queries simples); se prioriza claridad y atomicidad (Principios 5/8) sobre optimización sin medición (Principio 7).
3. **Temporal server-side + token en vez de sesión.** ~1907 filas no pertenecen a la sesión (tamaño, serialización, riesgo de manipular el preview en el cliente). Se guarda el crudo en `storage/app/private/tmp` y un manifiesto mínimo en caché con token no predecible, `user_id`, `hash` y expiración; al confirmar se re-parsea y revalida.
4. **Sin tabla de historial de importaciones.** El requerimiento se cumple con hash + temporal efímero; una entidad nueva violaría YAGNI (Principio 5/8). Si a futuro se exige auditoría de importaciones, se creará su spec.
5. **La limpieza del temporal se resuelve sin scheduler: descarte explícito al cancelar + barrido oportunista al abrir el importador.** La consecuencia "confirmar/cancelar/expirar" de esta ADR necesita un disparador. No hay ningún proceso ejecutando el scheduler de Laravel en este proyecto (ni en el `docker-compose.yml` de desarrollo ni en el servicio de Render de staging), de modo que un comando agendado quedaría escrito pero nunca correría. Se opta por: `POST .../importar/cancelar`, que descarta token y archivo, y un barrido de los temporales más viejos que el TTL cada vez que se abre `GET .../importar`. Sin infraestructura nueva (Principio 5/8) y sin optimizar sin medir (Principio 7).

## Consecuencias

- Importación atómica, idempotente y auditable en lo esencial (resultado visible en `shipping_rates`).
- Temporales y tokens requieren expiración + limpieza (confirmar/cancelar/expirar), resuelta según el punto 5.
- Un temporal abandonado sobrevive hasta que alguien vuelva a abrir el importador; la ventana es acotada y no afecta a `shipping_rates`.
- Límite de tamaño de archivo a fijar en la implementación aprobada.

## Alternativas descartadas

- `upsert()` sobre `cp`: descartado porque la semántica activa/histórica/no-op/creación queda más clara con procesamiento explícito.
- Filas en sesión o confirmación ciega del preview: descartado (tamaño, manipulación cliente, desincronización).
- Tabla `shipping_imports`: descartado por ahora (YAGNI; sin requerimiento de auditoría).
