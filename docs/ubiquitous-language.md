# Lenguaje Ubicuo

Vocabulario único del proyecto. Todos los términos de negocio se usan con este
significado en specs, código, UI y documentación. Si un término se usa con otro
sentido, es un bug de lenguaje.

## Glosario

| Término | Definición | Ejemplo / notas |
|---|---|---|
| **Producto** | Unidad del catálogo: un modelo concreto de cerámica, porcelanato, pastina, adhesivo o accesorio, con un modo de venta (m² o unidad) y atributos por familia. | "Pegamento Weber Estándar Cerámicas Bolsa 25kg" |
| **Categoría** | Agrupación de navegación del catálogo. **Plana** (sin subcategorías): una lista de raíces. Cada producto pertenece a una categoría. | Porcelanatos, Cerámicas, Pastinas, Adhesivos |
| **Unidad de venta** | Modo en que se vende un producto (`unidad_venta`): por **m²** o por **unidad** (bolsa/pieza). Determina la semántica de precio, stock y cálculo. | Cerámica → por m²; pastina → por unidad |
| **Specs** | Atributos de producto guardados como JSON validado por familia de categoría (medida, color, acabado, rendimiento, peso…). Complementan las columnas tipadas. | Porcelanato: medida, acabado, rectificado |
| **Caja** | Unidad física de stock y despacho de los productos que se venden por m². Se despacha solo en cajas enteras. | 1 caja ≈ 1,15 m² de cerámica 46×46 |
| **Bolsa / Pieza** | Unidad física de stock y despacho de los productos que se venden por unidad (pastinas, adhesivos, perfiles). | Pastina en bolsa de 5 kg; perfil por pieza |
| **m²** | Metro cuadrado: unidad de superficie en la que se expresa la venta y el precio (solo modo m²). | Precio mostrado: $ 20.098,20 / m² |
| **m²/caja** | Superficie que cubre una caja del producto (solo modo m²). Atributo del producto. | 1 caja cubre 1,15 m² |
| **Precio** | Precio de venta unitario mostrado en el catálogo; su significado lo da la unidad de venta (por m² o por bolsa/pieza). | Es la base del cálculo del total |
| **Precio por caja** | Precio efectivamente cobrado por caja completa (solo modo m²). Se deriva del precio y del m²/caja. | El total se calcula sobre cajas enteras |
| **Calculadora** | Herramienta de la ficha de producto (solo modo m²): el cliente ingresa dimensiones (largo × ancho) y obtiene m² → cajas necesarias, con o sin desperdicio. | Se muestra también el m² resultante |
| **Formato** | Tamaño de la pieza en centímetros. | 46×46, 60×60, 80×80, 61×122 |
| **Calidad** | Calidad de fábrica del producto. | 1.ª, 2.ª |
| **Terminación** | Acabado superficial de la pieza. | Satinado, brillante, mate |
| **Línea / Colección** | Familia comercial a la que pertenece el producto. | Línea "Mármol", colección "Milan" |
| **Desperdicio** | Porcentaje adicional opcional sobre los m² para cubrir cortes y roturas en la colocación (solo modo m²). | Opción del carrito: "Incluir 10 % adicional para cubrir desperdicios de la colocación" |
| **Stock** | Cantidad disponible de un producto, en la unidad de su unidad de venta (cajas o unidades). | "Quedan 3 cajas" / "Quedan 5 bolsas" |
| **Sin stock** | Producto con 0 unidades disponibles (cajas o bolsas/piezas). No se puede comprar. | Puede seguir visible en el catálogo |
| **Oferta** | Precio promocional temporal con % de descuento respecto del precio de lista. | "7 % OFF" con precio tachado |
| **Descuento** | Reducción del total por condición de pago o monto de compra. | "10 % de descuento pagando en efectivo" |
| **Pedido** | Compra registrada por un cliente con uno o más productos, su total y su estado. | Nace en el checkout web o en el registro manual de venta WhatsApp |
| **Estado del pedido** | Etapa del ciclo de vida del pedido. | Pendiente de pago → Pagado → Despachado → Entregado; o Cancelado |
| **Envío** | Costo de entrega calculado según el código postal del cliente. | Se calcula al ingresar el CP |
| **Reparto propio** | Entrega con vehículos del negocio dentro de una zona. | Método de entrega posible según el CP |
| **Transportista / Correo** | Entrega a todo el país por un tercero. | Método de entrega posible según el CP |
| **Entrega** | Acción de entregar la mercadería (reparto propio o transportista). | No incluye retiro en local (fuera de alcance) |
| **Venta WhatsApp** | Venta realizada fuera de la plataforma y registrada manualmente por un vendedor, para control de stock. | Se carga a mano; el pago se registra por fuera |
| **Seña** | Anticipo de una venta WhatsApp. Se registra a mano, fuera del sistema. | Solo se deja constancia en el pedido |
| **Cuotas** | Pago en cuotas con tarjeta. En la web lo gestiona MercadoPago; en ventas WhatsApp, por fuera del sistema. | — |
| **Cliente anónimo** | Comprador de la web sin cuenta: solo se conocen email y código postal. | No hay historial ni cuenta en el MVP |
| **Confirmación de pago** | Acción manual del admin que marca un pedido por transferencia como pagado. | La tarjeta se confirma automáticamente vía MercadoPago |
| **Usuario interno** | Persona con acceso al panel admin: admin, vendedor o depósito. | No existen cuentas de clientes |
| **Rol** | Función de un usuario interno que determina qué acciones puede realizar. | admin, vendedor, depósito (uno por usuario) |
| **Admin** | Rol del dueño: acceso total; gestiona usuarios y roles. | El primer admin nace del seeder con credenciales de entorno |
| **Vendedor** | Rol que atiende ventas: clientes, pedidos, ventas WhatsApp, catálogo. | No gestiona usuarios |
| **Depósito** | Rol que despacha y entrega pedidos. | — |
| **Desactivar usuario** | Baja de un usuario interno: no puede iniciar sesión, conserva historial. | Nunca se borra |
| **Auditoría** | Registro permanente de quién hizo cada acción crítica y cuándo. | Cambios de rol, precios, stock, pagos |

## Sinónimos prohibidos

| No usar | Motivo | Usar |
|---|---|---|
| Artículo, ítem, producto suelto | Ambiguos; "producto" es la unidad de catálogo | **Producto** |
| Pieza (como sinónimo de producto) | "Pieza" es la unidad física individual dentro de una caja | **Producto** / **Caja** |
| Unidad (como sinónimo de producto) | "Unidad" es un modo de venta (`unidad_venta`); puede ser bolsa o pieza | **Producto** |
| Cerámica (como sinónimo de producto) | "Cerámicas" es una categoría del catálogo | **Producto** |
| Ofertas (como categoría) | "Ofertas" no es una categoría: es un filtro por productos con oferta activa | **Oferta** |
| Stock en m² | El stock siempre se expresa en la unidad de venta (cajas o unidades) | **Stock (cajas / unidades)** |
| Subcategoría, categoría raíz, árbol | Las categorías son planas (revisión Spec 02, 2026-08-05) | **Categoría** |
| Pegamento (como categoría) | La categoría es **Adhesivos** | **Adhesivos** |
| Venta (como sinónimo de pedido) | "Venta WhatsApp" y "pedido web" conviven; se reserva "venta" para el registro manual | **Pedido** / **Venta WhatsApp** |
| Borrar usuario | El usuario nunca se borra (conserva historial); se da de baja | **Desactivar usuario** |
