# ADR-003 — Unidades de negocio: m², cajas, unidades y dinero

- **Estado**: aceptado (2026-08-05); **revisado (2026-08-05)** para cubrir los
  **dos modos de venta** (m² y unidad) definidos por el dueño (Spec 03)
- **Contexto**: en el rubro, la cerámica/porcelanato se vende **por m²** y se
  despacha en **cajas**; pastinas, adhesivos y perfiles se venden **por unidad**
  (bolsa o pieza). El dueño definió: `unidad_venta` por producto (`m2` | `unidad`)
  que determina la semántica de precio, stock y cálculo.

## Decisión

1. **Precios y montos en centavos** (`BIGINT` en base de datos, `int` en PHP):
   `precio_cents`, subtotales y totales. El significado de `precio_cents` lo da
   `unidad_venta`: precio **por m²** (modo `m2`) o **por bolsa/pieza** (modo
   `unidad`).
2. **Modo `m2`**: m² por caja como decimal de precisión controlada
   (`NUMERIC(8,2)` / string en DTOs), atributo del producto (`m2_por_caja`).
   El stock se expresa en **cajas** (entero). Aplica el cálculo m²→cajas.
3. **Modo `unidad`**: no existe `m2_por_caja` (NULL), no hay precio por caja, ni
   calculadora m²→cajas, ni desperdicio. El stock se expresa en **unidades**
   (entero).
4. **Cálculo de cajas (solo modo `m2`)**: `cajas = ceil(m² / m2_por_caja)` —
   nunca se vende media caja. El desperdicio opcional (10 %) se aplica antes del
   ceil.
5. **Derivación del precio por caja (solo modo `m2`)**:
   `precio_caja_cents = round(precio_cents × m2_por_caja)` — con `bcmath`
   (extensión instalada), nunca con floats.
6. El stock siempre se almacena en la unidad que define `unidad_venta` (cajas o
   unidades). Los m² son siempre de entrada (input del cliente) o de referencia
   (pantalla), nunca almacén de stock.

## Consecuencias

- Cero errores de redondeo de punto flotante en dinero.
- Las reglas de redondeo viven en un único lugar (caso de uso del carrito, Spec 05)
  y son testeables por unidad.
- `precio_caja` es un dato derivado: se recalcula si cambian precio por m² o m²/caja
  (no se persiste como valor independiente). Solo existe en modo `m2`.
- Un cambio de `unidad_venta` en un producto con historial de pedidos se bloquea
  (Spec 03, regla 67).

## Alternativas

- **Floats para dinero**: descartado — acumula errores en montos repetidos.
- **Decimal como string de moneda**: descartado — centavos enteros es el estándar
  más simple y seguro para ARS.
- **Persistir precio_caja calculado**: descartado — duplicación con riesgo de desvío;
  se calcula siempre.
- **Dos columnas de precio (m² y unidad)**: descartado — una sola `precio_cents`
  cuyo significado lo da `unidad_venta` evita columnas huérfanas (decisión del
  dueño 2026-08-05).
- **Un solo modo de venta (todo por m²)**: descartado — pastinas/adhesivos/perfiles
  no se venden por superficie.
