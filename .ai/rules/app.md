---
paths:
  - 'app/**'
---

# App

## Dos modos de venta: m² y unidad (unidad_venta)
Decisión del dueño (2026-08-05): hay DOS modos de venta. unidad_venta enum (M2 | Unidad) define la semántica de precio/stock/cálculo. Modo M2: precio por m², stock en cajas, m2_por_caja requerido, precio_caja = round(precio_m2 × m2_por_caja) con bcmath, calculadora m²→cajas con ceil y desperdicio 10%. Modo Unidad (pastinas, adhesivos, perfiles): precio por bolsa/pieza, stock en unidades, sin caja/desperdicio. Cambio de unidad_venta en producto con pedidos se bloquea (Spec 03). ADR-003 revisado en consecuencia.
