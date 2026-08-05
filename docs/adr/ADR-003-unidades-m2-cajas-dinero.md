# ADR-003 — Unidades de negocio: m², cajas y dinero

- **Estado**: aceptado (2026-08-05)
- **Contexto**: en el rubro, el precio se comunica por **m²** pero la mercadería se
  despacha en **cajas**. El dueño definió: venta por m², cobro y despacho solo en
  cajas enteras (ceil), precio mostrado por m².

## Decisión

1. **Precios y montos en centavos** (`BIGINT` en base de datos, `int` en PHP):
   `precio_m2_cents`, `precio_caja_cents`, subtotales y totales.
2. **m² por caja** como decimal de precisión controlada (`NUMERIC(8,2)` / string
   en DTOs): atributo del producto.
3. **Cálculo de cajas**: `cajas = ceil(m² / m²_por_caja)` — nunca se vende media
   caja. El desperdicio opcional (10 %) se aplica antes del ceil.
4. **Derivación del precio por caja**: `precio_caja_cents =
   round(precio_m2_cents × m²_por_caja)` — con `bcmath` (extensión instalada),
   nunca con floats.
5. El **stock siempre en cajas** (entero). Los m² son siempre de entrada (input del
   cliente) o de referencia (pantalla), nunca almacén de stock.

## Consecuencias

- Cero errores de redondeo de punto flotante en dinero.
- Las reglas de redondeo viven en un único lugar (caso de uso del carrito, Spec 05)
  y son testeables por unidad.
- `precio_caja` es un dato derivado: se recalcula si cambian precio por m² o m²/caja
  (no se persiste como valor independiente).

## Alternativas

- **Floats para dinero**: descartado — acumula errores en montos repetidos.
- **Decimal como string de moneda**: descartado — centavos enteros es el estándar
  más simple y seguro para ARS.
- **Persistir precio_caja calculado**: descartado — duplicación con riesgo de desvío;
  se calcula siempre.
