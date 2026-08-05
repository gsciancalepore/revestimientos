# Visión — Casa de Cerámicas

> Borrador v0.1 — pendiente de revisión y aprobación del dueño.
> Fecha: 2026-08-05

## Objetivos

- **Digitalizar la venta**: crear un canal web donde los clientes compren de forma
  autoservicio con tarjeta (MercadoPago) o transferencia bancaria.
- **Mantener WhatsApp como canal**: sigue siendo el canal principal de asesoramiento
  y venta. El sistema registra esas ventas manualmente para que el stock sea consistente.
- **Comunicar el producto como se piensa en el rubro**: dos modos de venta —
  cerámicas/porcelanatos por m² (precio por m², calculadora de dimensiones
  largo × ancho → m² → cajas necesarias, despacho en cajas enteras) y
  pastinas/adhesivos/perfiles por unidad (bolsa o pieza).
- **Mostrar stock real**: el cliente web ve cuántas cajas quedan antes de comprar.
- **Operar desde un panel administrativo simple**: productos, categorías, pedidos,
  stock y usuarios, sin conocimientos técnicos.

## Problema

Hoy la venta es 100 % por WhatsApp:

- El catálogo no es autoservicio: cada consulta de precio/stock la responde un vendedor.
- Los precios se comunican uno a uno; no hay una forma de que el cliente calcule por sí
  mismo cuánto necesita (m², cajas, desperdicio).
- No hay historial de clientes ni de ventas.
- El stock se conoce "de memoria" o contando cajas; las ventas web y de WhatsApp pueden
  pisarse entre sí.
- La disponibilidad de una venta depende de que un vendedor esté conectado.

## Qué NO resuelve

Fuera de alcance (al menos en la primera versión):

- **No es un ERP completo**: no emite facturación AFIP, no gestiona compras a
  proveedores, no tiene cuentas corrientes ni fidelización.
- **No reemplaza WhatsApp** como canal de atención y asesoramiento.
- **No gestiona logística**: no planifica rutas de reparto ni integra con correo
  Argentino u otros transportistas de forma automática (el costo de envío se calcula
  por código postal; el *cómo* se resuelve más adelante mediante un adaptador).
- **No hay retiro en local**: la entrega es por reparto propio o transportista.
- **No hay cuentas de clientes**: la compra es anónima (email + código postal).
- **No hay monto mínimo** de compra.

## MVP

1. **Admin** (Users): login con roles — dueño (admin), vendedor, depósito.
2. **Categorías y productos** (Products): categorías planas (Porcelanatos,
   Cerámicas, Pastinas, Adhesivos); producto con dos modos de venta (m² o
   unidad), atributos del producto (marca, medida, color, acabado…) y atributos
   comerciales (precio, ofertas, stock, activo, imágenes).
3. **Catálogo público** (Products): home, categorías, listados con filtros, ficha de
   producto con calculadora de dimensiones, stock visible y ofertas.
4. **Carrito** (Orders): cantidades en m² → cajas enteras (redondeo hacia arriba,
   modo m²) o en unidades (bolsas/piezas, modo unidad), opción de 10 % adicional
   por desperdicio de colocación (modo m²), precio del envío según código postal.
5. **Checkout** (Orders + Payments): compra anónima, pago con tarjeta vía
   MercadoPago o transferencia bancaria con confirmación manual desde el admin.
6. **Gestión de pedidos** (Orders): estados (pendiente de pago → pagado → despachado
   → entregado / cancelado), vista para depósito, registro manual de ventas de
   WhatsApp para control de stock.

## Éxito del proyecto

Métricas propuestas (metas numéricas a definir con el dueño):

- Porcentaje de ventas que entran por la web sobre el total.
- Reducción de consultas repetitivas de precio/stock por WhatsApp.
- Cero ventas de productos sin stock real (consistencia entre canales).
- Tiempo para publicar un producto nuevo desde el panel.
- Tiempo para procesar un pedido web (pago → despacho → entrega).

## Stakeholders

| Rol | Interés principal |
|---|---|
| **Dueño (admin)** | Decisión final, precios, catálogo, reportes básicos |
| **Vendedores** | Atención web, registro de ventas de WhatsApp, clientes |
| **Depósito** | Despacho y entrega de pedidos |
| **Clientes finales** | Comprar cerámicas sin depender de un vendedor |

## Riesgos

| Riesgo | Mitigación |
|---|---|
| Volatilidad de precios (inflación) | Mecanismo de actualización de precios a definir; precios verificables antes de confirmar la compra |
| Desvío de stock entre WhatsApp y web | Registro manual obligatorio de ventas de WhatsApp; stock único y visible |
| Dependencia de un único procesador de pagos | Transferencia bancaria con confirmación manual como alternativa a MercadoPago |
| Costo de envío mal calculado | Adaptador de envío: comienza con tarifas internas; se puede reemplazar por API sin tocar el dominio |
| Falta de adopción interna | Panel simple, roles claros, ventas WhatsApp cargadas "a mano" sin fricción |
