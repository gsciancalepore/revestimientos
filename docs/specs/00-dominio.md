# Spec 00 — Definición de Dominio

- **Estado**: aprobada (revisión 2026-08-05)
- **Fuentes**: relevamiento con el dueño + referencia Store 409 (store409.com.ar)

## Objetivo

Definir el dominio del sistema de ventas web + panel administrativo de la casa de
cerámicas, de modo que cada spec posterior describa un comportamiento dentro de
estas reglas y sin contradicciones.

## Contexto

El negocio vende cerámicas, porcelanatos, pegamentos y accesorios para la
construcción, hoy 100 % por WhatsApp. El sistema agrega un canal web de venta
autoservicio (tarjeta o transferencia) y un panel administrativo para operarlo.
WhatsApp se mantiene como canal de asesoramiento y venta; esas ventas se registran
manualmente en el sistema para mantener el stock consistente.

## Reglas de negocio

### Producto (dominio Products)

1. Un producto tiene **atributos de producto** (físicos/catalogables) y **atributos
   comerciales** (de venta). Conceptualmente separados; pueden persistir juntos.
   El modelo es **híbrido** (decisión del dueño 2026-08-05): las columnas tipadas
   guardan solo lo que se calcula o filtra; el resto (medida, color, acabado,
   rendimiento, peso…) vive en `specs` JSONB validado por familia (Spec 03).
   - Columnas tipadas: nombre, categoría, marca, código (SKU único), precio,
     `unidad_venta`, m² por caja (solo modo m²), stock, activo, imágenes.
   - Atributos comerciales: precio, oferta (opcional), stock, activo/inactivo,
     imágenes.
2. Hay **dos modos de venta** (`unidad_venta`, Spec 03): **por m²** (cerámicas y
   porcelanatos: el precio se expresa por m² y el stock en cajas) y **por unidad**
   (pastinas, adhesivos, zócalos/perfiles: el precio es por bolsa/pieza y el stock
   en unidades). El precio se guarda en una sola columna `precio_cents` cuyo
   significado lo da `unidad_venta`.
3. Solo en modo **m²** se deriva el **precio por caja**:
   `precio_caja = redondear(precio_cents × m²_por_caja)` (a la unidad monetaria más
   cercana). En modo **unidad** no existe el precio por caja.
4. El producto pertenece a una **categoría plana** (Spec 02 revisada): una lista de
   raíces (Porcelanatos, Cerámicas, Pastinas, Adhesivos), sin jerarquía.
5. Un producto **sin stock** (0 cajas o 0 unidades) no puede comprarse; puede seguir
   visible en el catálogo ("Sin stock") a criterio del negocio.
6. La **oferta** es un precio promocional con % de descuento sobre el precio de
   lista; se muestra con el precio de lista tachado.
7. Las ventas WhatsApp y las ventas web comparten el **mismo stock único**.

### Unidades y cálculo (regla de redondeo)

8. En modo **m²**, el cliente expresa la cantidad en **m²** (o mediante dimensiones:
   largo × ancho). En modo **unidad**, expresa la cantidad en **unidades** (bolsas o
   piezas) y no aplican las reglas 9–12.
9. **No se vende media caja**: las cajas se redondean siempre hacia arriba
   (techo). `cajas = ceil(m² / m²_por_caja)`.
10. El **total se cobra por cajas enteras**: `total = cajas × precio_caja`.
11. Si el cliente activa el **10 % de desperdicio**, los m² se incrementan un 10 %
    antes de convertir a cajas: `m²_a_cubrir = m² × 1,10`.
12. La calculadora del catálogo muestra: m² ingresados → cajas necesarias (y, con
    desperdicio, m² a cubrir y cajas resultantes).

### Carrito (dominio Orders)

13. El carrito es **anónimo** (sesión, sin cuenta de cliente). No persiste stock.
14. Reglas del carrito: ver Spec 05 (reglas del carrito). Resumen:
    - Al agregar/actualizar un producto se valida stock y estado activo.
    - El precio mostrado es el vigente del catálogo; el precio se congela al
      generar el pedido.
    - El stock **no se reserva** en el carrito (ver ADR-005).

### Envío (dominio Shipping — inicialmente dentro de Orders)

15. El costo de envío se calcula cuando el cliente ingresa su **código postal**.
16. El cálculo se realiza a través de un **adaptador** (puerto `ShippingCalculator`):
    implementación inicial interna (tarifas manuales por CP/zona), reemplazable por
    API de cotización sin cambiar el dominio (ADR-006).
17. No hay **retiro en local** por el momento (fuera de alcance).
18. No hay **monto mínimo** de compra.

### Pedido (dominio Orders)

19. Un pedido nace en el checkout web o como **venta WhatsApp** registrada
    manualmente por un vendedor (solo control; pago por fuera del sistema).
20. Estados del pedido:
    - `pendiente_de_pago` — creado, sin pago confirmado
    - `pagado` — pago confirmado (automático tarjeta / manual transferencia o venta WhatsApp)
    - `despachado` — salió del depósito
    - `entregado` — llegó al cliente
    - `cancelado` — no se concreta
21. El **stock desciende** al confirmarse el pago (ADR-005). La venta WhatsApp
    desciende al registrarse (su pago se registra al cargar).
22. Un pedido puede cancelarse solo en estados que no sean `entregado`; al cancelar
    un pedido `pagado`/`despachado`, el stock **se restituye**.
23. El pedido registra: email del cliente, CP, datos de entrega, líneas (producto,
    m², cajas, precio congelado), subtotal, envío, total, medio de pago.

### Pagos (dominio Payments)

24. Medios de pago web:
    - **Tarjeta** (crédito/débito) vía MercadoPago — confirmación automática.
    - **Transferencia bancaria** — confirmación manual por el admin.
25. La venta WhatsApp se registra con pago "por fuera" (efectivo, tarjeta física,
    seña o cuotas) sin integración: solo se deja constancia.
26. Descuentos: por forma de pago y por monto de compra (se definen en su spec);
    las ofertas son por producto y pertenecen al dominio Products.

### Clientes (dominio Customers — no se crea en el MVP)

27. El cliente web es **anónimo**: solo email y código postal. No hay cuenta,
    historial, direcciones guardadas ni fidelización.
28. Customers se extraerá como dominio cuando aparezcan historial, direcciones,
    listas de precios o fidelización (ADR-001).

### Inventario (dominio Inventory — no se crea en el MVP)

29. El stock es un atributo del producto. No hay movimientos, depósitos, reservas
    ni múltiples sucursales.
30. Inventory se extraerá cuando aparezcan movimientos, depósitos, reservas o
    múltiples sucursales (ADR-001).

### Usuarios (dominio Users)

31. Usuarios internos con roles: **admin** (dueño; todo), **vendedor** (clientes,
    pedidos, ventas WhatsApp, catálogo), **depósito** (despacho y entrega).
32. Cada acción es autorizada por Policy; los permisos por rol se definen en la
    Spec 01 (Autenticación y roles).

## Casos de uso principales (nivel dominio)

| Caso de uso | Dominio | Spec |
|---|---|---|
| Iniciar sesión / administrar usuarios y roles | Users | 01 |
| Crear/editar categorías | Products | 02 |
| Crear/editar productos, precios, ofertas, stock | Products | 03 |
| Ver catálogo, filtrar, calcular m²→cajas | Products | 04 |
| Aplicar reglas del carrito | Orders | 05 |
| Calcular envío por CP | Orders | 06 |
| Confirmar checkout y pagar (MercadoPago / transferencia) | Orders + Payments | 07 |
| Gestionar pedidos; registrar venta WhatsApp | Orders | 08 |
| Aplicar descuentos por pago/monto | Orders | 09 (opcional) |

## Casos borde (resumen; detalle por spec)

- Pedido de m² que no cierra en cajas exactas → siempre ceil a cajas enteras (solo
  modo m²).
- Cambio de stock entre el carrito y el checkout → se valida al generar el pedido
  (ver Spec 05).
- Cambio de precio entre carrito y checkout → precio congelado al generar el pedido
  (ver Spec 05).
- Producto con 0 cajas (modo m²) o 0 unidades (modo unidad) → no comprable.
- Cancelación de pedido pagado → restitución de stock.
- CP fuera de la zona de tarifas → sin envío disponible (se define en Spec 06).

## Criterios de aceptación (de esta spec)

- Toda spec posterior referencia esta spec sin contradecirla.
- Los términos usados en código y UI pertenecen al lenguaje ubicuo.

## Decisiones arquitectónicas

- Dominios iniciales: **Products, Orders, Payments, Users** (ADR-001).
- Dominios diferidos: **Inventory, Customers, Shipping, Discounts** (ADR-001).
- Gestión del stock: al confirmar el pago (ADR-005).
- Envío: puerto + adaptador (ADR-006).
- Unidades y dinero: precios en centavos; m² con precisión decimal; dos modos de
  venta (m² y unidad) definidos por `unidad_venta` (ADR-003, Spec 03).
