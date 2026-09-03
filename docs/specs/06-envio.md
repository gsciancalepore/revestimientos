# Spec 06 — Envío por código postal

- **Estado**: cerrada (2026-09-03) — 158 tests en verde, Pint/PHPStan alineados
- **Fuentes**: Spec 00 (reglas 15–16, envío por CP + adaptador), Spec 05 (reglas 81–92, carrito en sesión, `subtotal` sí / `total` no), ADR-006 (puerto `ShippingCalculator` + implementación inicial interna), ADR-005 (sin reserva), decisiones del dueño (2026-09-03): CP argentino 4 dígitos, una tarifa activa por CP exacto, CP única variable, sin tarifa → no disponible sin excepción, tarifas manuales en DB solo admin, `ShippingCalculator` + `ManualShippingCalculator` sin API, `total = subtotal + shipping` cuando hay cotización disponible

## Objetivo

El **cálculo de envío por código postal** (dominio Orders, dentro del flujo de compra): dado un CP ingresado por el cliente, devolver el costo de envío si existe tarifa activa para ese CP exacto, o indicar que el envío no está disponible. Es la base de `total = subtotal + shipping` y del checkout (Spec 07).

## Contexto

- El cliente web es **anónimo** (Spec 00, regla 27) y el carrito es **anónimo en sesión** con `subtotal` (Spec 05, reglas 81/88): el envío se cotiza sin cuenta ni reserva de stock.
- Spec 05 dejó `subtotal = Σ subtotal_línea` y explícitamente no calcula `total` ni envío; Spec 06 incorpora el envío y establece `total = subtotal + shipping` **solo cuando existe cotización disponible** (regla 100).
- El **CP es la única variable** para determinar la tarifa (regla 93). No existen zonas, rangos, precedencias, peso, distancia ni otras variables.
- El proyecto usa `M2Calculator` (Spec 04/ADR-003) y `Cart` en sesión (Spec 05); el envío no duplica esa lógica.
- La **reserva de stock con vencimiento** sigue explícitamente diferida y vinculada a ADR-005 (Spec 05, evolución).

## Reglas de negocio (continúa la numeración de las Specs 00–05)

93. Cada **código postal** es una cadena de **4 dígitos** (`^[0-9]{4}$`) que **conserva los ceros iniciales** (ej. `1000`, `0123`). Es la **única variable** para determinar la tarifa de envío. No existen zonas, rangos de CP, precedencias, peso, distancia ni otras variables.
94. Cada **CP puede tener como máximo una tarifa activa** simultáneamente. La unicidad es por **CP exacto + `activo = true`** (índice único parcial). Un mismo CP puede tener historial con tarifas inactivas, pero nunca dos activas a la vez.
95. La **tarifa es manual, persistida en DB y administrable** (CRUD). Atributos: `cp` (string 4 dígitos), `costo_cents` (int ≥ 0, en centavos), `activo` (bool). `costo_cents = 0` representa envío gratis y es válido.
96. **Cotización por CP**: dado un `cp` normalizado (`trim`, exact match), si existe tarifa con ese `cp` y `activo = true` → la cotización está **disponible** y el costo es `costo_cents`; si no existe tarifa activa para ese CP → la cotización está **no disponible**. La no disponibilidad **no lanza excepción de dominio** (comportamiento esperado, no error).
97. La **tarifa aplicada queda determinada únicamente por el CP ingresado** por el cliente (exact match). No hay fallback a zona ni a CP cercano.
98. **Sin API externa** en esta spec. No se integra ni se diseña el contrato de una API de operador logístico.
99. **Arquitectura**: se mantiene `ShippingCalculator` como **abstracción** y `ManualShippingCalculator` como **implementación inicial** que consulta las tarifas manuales en DB. La existencia de la abstracción permite, a futuro y fuera del alcance de esta spec, incorporar otra implementación sin modificar el flujo que consume el puerto. La sección Evolución puede mencionar esa posibilidad **sin especificar** cómo funcionaría una API futura.
100. **Total del flujo** (evolución desde Spec 05): `subtotal` sigue definido por el carrito (Spec 05, regla 88); Spec 06 establece `total = subtotal + shipping` **cuando existe cotización disponible**; si el envío no está disponible, no hay `total` con envío (el flujo informa la no disponibilidad). Cuando la cotización no está disponible, el flujo de compra no puede confirmar el checkout ni crear el pedido hasta obtener una cotización disponible.

## Matriz de permisos

| Acción | Público (sesión) | admin | vendedor | depósito |
|---|---|---|---|---|
| Cotizar envío por CP (consultar tarifa) | ✓ | ✓ | ✓ | ✓ |
| Ver listado de tarifas | — | ✓ | — | — |
| Crear / editar / desactivar tarifa | — | ✓ | — | — |
| Acceder por URL a `/admin/tarifas-envio` | — | ✓ | 403 | 403 |

La cotización es **pública y anónima** (como el carrito, Spec 05:36). La administración de tarifas es solo admin.

## Casos borde

- CP vacío, nulo o no enviado → 422 por validación (requerido).
- CP con formato inválido (no `^[0-9]{4}$`, ej. `ABC`, `123`, `12345`, `0123A`) → 422 por validación.
- CP válido pero sin tarifa activa (no existe o `activo = false`) → cotización **no disponible** con mensaje "Envío no disponible para este CP" (sin excepción).
- Intento de crear segunda tarifa activa para el mismo CP → error de validación por unicidad (422).
- Crear tarifa con `costo_cents` negativo o no entero → error de validación.
- Desactivar una tarifa activa (`activo = false`) → el CP pasa a no disponible en la siguiente cotización.
- `costo_cents = 0` con `activo = true` → cotización disponible con envío gratis.
- CP con ceros iniciales (`0123`) → se persiste y compara como string, sin perder el cero.
- Cotización sin afectar el carrito: el `subtotal` del carrito no cambia por cotizar.

## Criterios de aceptación

- [x] Una tarifa activa por CP exacto retorna cotización disponible con `costo_cents` correcto.
- [x] CP válido pero sin tarifa activa retorna cotización no disponible sin lanzar excepción (separado de input inválido 422).
- [x] Se valida CP requerido `^[0-9]{4}$` como string con ceros iniciales; CP vacío/null/no enviado o con formato inválido → 422.
- [x] No se puede crear una segunda tarifa activa para el mismo CP → 422 por unicidad.
- [x] `costo_cents` negativo o no entero → 422.
- [x] Desactivar una tarifa vuelve el CP a no disponible en la siguiente cotización.
- [x] `costo_cents = 0` con tarifa activa se cotiza como disponible (envío gratis).
- [x] Solo admin accede a `/admin/tarifas-envio` (crear/editar/desactivar); vendedor y depósito reciben 403.
- [x] La cotización es pública y anónima: un cliente sin login puede cotizar por CP.
- [x] Cuando hay cotización disponible, `total = subtotal + shipping` es calculable; sin cotización, no hay total con envío.
- [x] Pint, PHPStan nivel 8 y Pest en verde; CI alineado.

## Decisiones arquitectónicas

- **Tabla `shipping_rates`**: `id`, `cp` (varchar 4, string), `costo_cents` (bigint con `CHECK (costo_cents >= 0)`), `activo` (bool, default true), `timestamps`, índice único parcial `UNIQUE(cp) WHERE activo = true` para garantizar una única tarifa activa y favorecer consultas de tarifas activas; índice adicional sobre `cp` para consultas administrativas que incluyen tarifas inactivas (si el agente determina que no aporta, puede eliminarlo sin cambiar la regla de negocio). `cp` se persiste normalizado (`trim`) como string para conservar ceros iniciales. YAGNI: sin columnas de zona/rango/peso.
- **Puerto `ShippingCalculator`**: puerto `ShippingCalculator` e implementación inicial `ManualShippingCalculator`, sin fijar namespace ni estructura interna adicional, que consulta `shipping_rates` por `cp` exacto y `activo`. Binding en el contenedor. No se diseña ni implementa contrato de API externa en esta spec. Los nombres `ShippingCalculator` y `ManualShippingCalculator` sí se mantienen por ser decisión arquitectónica explícita.
- **Sin anticipación**: no se crean estructuras para zonas, rangos, precedencias, múltiples tarifas activas por CP, APIs externas, cálculo por peso/distancia ni otros mecanismos no definidos. La evolución queda solo como consecuencia de la abstracción.
- **Administración**: recurso `admin/tarifas-envio` (solo admin, `role:admin` + Policy) mediante Form Requests o mecanismo equivalente, con validación de CP 4 dígitos, unicidad parcial de tarifa activa y `costo_cents`.
- **Total del flujo**: `subtotal` lo expone `Cart` (Spec 05); el `total` con envío se deriva fuera del carrito cuando `ShippingQuote::disponible === true` (no se persiste `total` en esta spec; lo crea el checkout en Spec 07).

## Evolución documentada (no arquitectura anticipada)

Esta spec **documenta** evoluciones sin crear código para ellas:

- **Spec 07 (Checkout)**: creará el pedido con `subtotal + shipping` congelados cuando la cotización esté disponible.
- **ShippingCalculator**: la existencia del puerto permite, a futuro y fuera del alcance de Spec 06, incorporar otra implementación (ej. consultar un operador logístico) sin modificar el flujo que consume el puerto, **sin especificar** en esta spec cómo funcionaría esa integración.

No se diseña en esta spec el contrato, los DTOs ni el flujo de una API futura.

## Tareas técnicas

- [x] Migración `create_shipping_rates` (cp, costo_cents, activo, índices) + modelo `ShippingRate` (scope `activo()`).
- [x] Puerto `ShippingCalculator` + implementación `ManualShippingCalculator` (cotización por CP exacto, `disponible` sin excepción) + binding.
- [x] Recurso admin `admin/tarifas-envio` (Policy solo admin, Form Requests con validación CP `^[0-9]{4}$` y unicidad parcial, vistas).
- [x] Integración de cotización por CP en el flujo (campo CP + consulta al puerto, `total = subtotal + shipping` cuando `disponible`, mensaje no disponible si no hay tarifa).
- [x] Tests Pest: `tests/Feature/Envio/` (unicidad CP activo, validación formato, cotización disponible/no disponible sin excepción, permisos admin 403, `costo 0`, `total` con/sin envío) + unit del calculador si aplica.
- [x] Verificación de calidad: pint, PHPStan nivel 8 (`app/`), Pest (una suite a la vez, `ceramica_test`), CI.
- [x] Actualizar `docs/arquitectura.md` (envío por CP exacto, `ShippingCalculator` + `ManualShippingCalculator`) y `docs/roadmap.md` al cerrar la spec (no en este borrador).

## Nota de handoff para el agente implementador

La spec está **cerrada** (2026-09-03). Implementada con TDD en este orden:

1. Migración `create_shipping_rates` + modelo `ShippingRate`.
2. Puerto `ShippingCalculator` + `ManualShippingCalculator` + binding.
3. Recurso admin `admin/tarifas-envio` (Policy + Form Requests + vistas).
4. Integración de cotización por CP (campo + `total = subtotal + shipping` cuando `disponible`).
5. Tests Pest (`tests/Feature/Envio/` + unit del calculador).

Reglas para esta tarea:

- **No editar** `docs/specs/` (salvo esta spec al aprobarla), `docs/adr/` ni `docs/roadmap.md`.
- **No anticipar** zonas, rangos, múltiples tarifas activas por CP, APIs externas, cálculo por peso/distancia ni otras lógicas no definidas. `ShippingCalculator` sí, API futura no.
- Seguir `AGENTS.md` y `.ai/rules` (`grep -rin 'envio\|shipping\|cp' .ai/rules`).
- Tras editar PHP: `make format`; validar con `make lint` → `make stan` → `make test` (una suite).
- Tests que renderizan vistas requieren assets de Vite (`make npm-dev` o `make npm-build`).
- Registrar con `record-rule` cualquier regla durable nueva descubierta (p. ej. patrón de tarifa única por CP, `ShippingCalculator` en sesión).

