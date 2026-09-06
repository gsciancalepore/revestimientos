# Spec 06 fase 2 — Importador administrativo de tarifas por CP

- **Estado**: cerrada (2026-09-06) — aprobada por el dueño, implementada y verificada (253 tests en verde, Pint/PHPStan alineados; reglas renumeradas 129–142 en la sincronía SDD del 2026-09-06)
- **Base**: Spec 06 cerrada (2026-09-03, reglas 93–100), `shipping_rates` (`cp` string 4, `costo_cents` bigint `CHECK >= 0`, `activo`, único parcial `cp WHERE activo = true`), `UpdateShippingRateAction` (UPDATE directo), `ShippingRatePolicy` (solo admin).
- **Fuentes**: plan aprobado del importador (decisiones cerradas listadas abajo).

## Objetivo

Permitir al admin importar un CSV externo con el **snapshot completo** de tarifas `codigo_postal → precio_envio`, con validación total previa, preview, confirmación y aplicación transaccional e idempotente, sin que Laravel calcule, genere ni modifique tarifas.

## No-objetivos explícitos

Laravel NO calcula tarifas: sin GeoRef, sin distancias, sin Haversine, sin factores geográficos, sin generación de CP, sin modificación del CSV, sin recuperación de CP excluidos. No se toca `CP-script/`, `ShippingCalculator` ni `ManualShippingCalculator`. El importer es responsabilidad administrativa independiente.

## Contrato CSV

- Cabecera exacta, en orden: `codigo_postal,precio_envio`.
- `codigo_postal`: string de exactamente 4 dígitos (`^[0-9]{4}$`), con `trim`; nunca como entero (conserva ceros, ej. `0123`).
- `precio_envio`: pesos argentinos, entero sin separadores ni decimales, `>= 0`. `0` = $0,00 permitido. Ej.: `10000` = $10.000,00; `18000` = $18.000,00.
- Una fila por CP; el archivo es snapshot completo, no delta.

## Reglas de negocio (continúan la numeración global desde 128, último número usado por la Spec 07.4)

129. Conversión `precio_envio × 100 = costo_cents` con aritmética entera nativa de PHP (64 bits), sin `float` y sin BCMath. Ej.: `10000 → 1000000`, `0 → 0`.
130. Límite anti-overflow explícito: `precio_envio` debe cumplir `<= 92233720368547758` (de modo que `× 100` nunca supere el máximo de `bigint`). Se valida como `max:92233720368547758`.
131. CP nuevo (sin tarifa activa) → `create` con `activo=true`.
132. CP con tarifa activa + mismo `costo_cents` → no-op (sin `UPDATE`, sin tocar `updated_at`).
133. CP con tarifa activa + distinto costo → UPDATE directo de la misma fila (misma semántica que `UpdateShippingRateAction`; no versionado).
134. CP con únicamente historial inactivo + presente en CSV → `create` de nueva fila activa; nunca reactivar una histórica.
135. CP con tarifa activa ausente del snapshot → `activo=false`. Históricas inactivas ausentes → no tocar.
136. Nunca eliminar físicamente (`delete` prohibido en esta funcionalidad; `DeleteShippingRateAction` no se usa aquí).
137. Procesamiento explícito fila-por-fila; no se utiliza `upsert()` (ver ADR-011).
138. Toda mutación en una única `DB::transaction()`; cualquier fallo → rollback total.
139. Validación total del CSV antes de persistir nada; un solo error de contenido invalida la importación.
140. Preview con archivo temporal server-side + token no predecible asociado a usuario/sesión y con expiración; no guardar filas en sesión. Al confirmar, re-parsear y revalidar desde el temporal.
141. Reimportar el mismo CSV es idempotente (solo no-ops; desactivaciones ya aplicadas no generan cambios).
142. Sin tabla de historial de importaciones (hash + temporal alcanzan; YAGNI).

## Validaciones de contenido (todas previas a persistir)

- Archivo: `required|file|mimes:csv,txt` como filtro inicial; la validación real es por contenido; tamaño máximo a fijar en la implementación aprobada.
- Cabecera exacta `codigo_postal,precio_envio`; BOM/UTF-8 y CRLF/LF manejados de forma robusta.
- Por fila (con nº de línea en el error): CP `required|string|regex:/^[0-9]{4}$/` tras `trim`; `precio_envio` `required|integer|min:0|max:92233720368547758` (entero, sin decimales ni separadores); CP duplicado dentro del archivo → error; fila vacía intermedia o archivo sin filas de datos → error.

## Flujo

1. `GET .../importar`: form `multipart` con `input[type=file][name=csv]`.
2. `POST .../importar`: valida archivo, parsea y valida **todo**; error → 422 con detalle por fila, nada persiste; éxito → guarda crudo en `storage/app/private/tmp` + manifiesto en caché (`token` no predecible, `user_id`, `path`, `hash`, conteos, expiración ~30 min) y redirige a preview.
3. `GET .../preview`: resumen (total, nuevas, a actualizar, sin cambios, a desactivar) + Confirmar/Cancelar. Token ajeno/expirado → rechazar.
4. `POST .../confirmar`: re-parsea + revalida desde el temporal, ejecuta la Action de importación en transacción, limpia temporal + token, redirige a `tarifas-envio.index` con `status`.

## Matriz de permisos (extiende Spec 06)

| Acción | admin | vendedor | depósito | invitado |
|---|---|---|---|---|
| Importar tarifas (upload/preview/confirmar) | ✓ (`import`) | 403 | 403 | login |
| CRUD existente | sin cambios Spec 06 | — | — | — |

Nuevo ability `import` en `ShippingRatePolicy` (solo admin), `Gate::authorize('import', ...)`; rutas bajo `auth` + `role:admin`, definidas antes del `Route::resource` para no colisionar con `{tarifa_envio}`.

## Casos borde

Cabecera distinta → 422; CP `123`/`12345`/`ABC` → 422; precio `-1`/`10.5`/`10,000` → 422; precio mayor al máximo → 422; duplicado en archivo → 422; `0123` se persiste como string; `0` válido; ausente con activa → desactiva; ausente solo-inactiva → intacta; reimport idéntico → 0 escrituras; token de otro usuario o expirado → rechazo sin mutación.

## Criterios de aceptación

Verificados uno a uno contra código y tests el 2026-09-06 (`tests/Feature/Envio/ShippingRateImportTest.php`, 31 tests).

- [x] CSV ejemplo `1000,10000` crea/actualiza con `costo_cents=1000000` — *precio en pesos se convierte a centavos multiplicando por 100*.
- [x] Mismo precio → no-op (sin `UPDATE`) — *confirmar crea nuevas, actualiza distintas y no toca iguales* (compara `updated_at`).
- [x] Precio distinto → UPDATE misma fila — mismo test.
- [x] Solo-inactivo + presente → nueva activa, histórica intacta — *cp solo con historial inactivo y presente crea nueva activa sin reactivar*.
- [x] Activa ausente → `activo=false`; inactiva ausente intacta; cero deletes — *activa ausente del snapshot se desactiva; histórica inactiva no se toca; sin deletes*.
- [x] Cualquier error de contenido → 0 cambios — tests de cabecera, CP, precio, duplicado y fila mixta, todos `422` con `count() === 0`.
- [x] Fallo en confirmación → rollback total — *un fallo durante la confirmación revierte toda la importación*.
- [x] Reimport idéntico → idempotente — *reimportar el mismo csv es idempotente* (compara fingerprint completo).
- [x] Preview con conteos correctos; confirm re-parsea — *preview muestra los conteos del snapshot pendiente* + *archivo manipulado entre preview y confirm no muta nada*.
- [x] Solo admin (`import`); resto 403/login — *invitado es redirigido al login* + *vendedor recibe 403 en importar, preview, confirmar y cancelar*.
- [x] Pint, PHPStan nivel 8 (`app/`), Pest en verde; CI alineado — 253 tests, Pint PASS, PHPStan sin errores; `ci.yml` ejecuta la misma secuencia.

## Tareas técnicas (solo tras aprobar esta spec)

- [x] `ImportShippingRatesRequest` + parser nativo (sin dependencias nuevas) + Action de importación (fila-por-fila en transacción).
- [x] Controlador delgado + rutas + Policy `import` + vistas `import`/`preview` (convención `admin/tarifas-envio/*`).
- [x] Tests Pest `tests/Feature/Envio/ShippingRateImportTest.php`: validación, preview, creación, actualización, no-op, desactivación por snapshot, rollback, idempotencia, autorización, cero inicial, conversión pesos→cents.
- [x] Verificación `make format` + `make lint → make stan → make test` (una suite; assets Vite para vistas).
