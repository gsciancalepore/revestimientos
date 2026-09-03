# Spec 05 — Carrito

- **Estado**: cerrada (2026-09-03) — 135 tests en verde, Pint/PHPStan alineados
- **Fuentes**: Spec 00 (reglas 1–12, 13–14, 27), Spec 03 (reglas 55–60, unidad_venta, stock), Spec 04 (calculadora m²→cajas, `M2Calculator`, regla 75), ADR-003 (m², cajas, dinero, bcmath), ADR-005 (gestión de stock, sin reserva), ADR-006 (envíos diferidos a Spec 06), visión (compra anónima), decisiones del dueño (2026-09-03): sin reserva, sin anticipaciones, subtotal sí / total no

## Objetivo

El **carrito anónimo en sesión** (dominio Orders): el cliente agrega productos desde el catálogo público, define cantidades y obtiene el **subtotal**. No incluye costo de envío ni total final. Es la base del checkout (Spec 07) y del cálculo de envío por CP (Spec 06).

## Contexto

- El cliente web es **anónimo** (Spec 00, regla 27): el carrito vive en sesión, sin cuenta ni persistencia en base de datos (YAGNI).
- El carrito **no reserva stock** (ADR-005: el stock desciende solo al confirmar el pago). Un pedido `pendiente_de_pago` no garantiza disponibilidad.
- Los productos tienen **dos modos de venta** (Spec 03, reglas 57–60): por m² (precio por m², stock en cajas, `m2_por_caja`, calculadora con `ceil` y desperdicio 10 %) y por unidad (precio por bolsa/pieza, stock en unidades, sin calculadora ni desperdicio).
- La calculadora m²→cajas de la ficha (Spec 04, regla 75) es informativa; el carrito **reutiliza** `M2Calculator` (bcmath, ADR-003) y no duplica la lógica de redondeo.
- El **precio vigente** es el del catálogo al momento de agregar/actualizar; el congelado al generar el pedido pertenece a Spec 07, no a esta spec.
- Envío y descuentos no existen en esta spec: se documentan solo como evolución.

## Reglas de negocio (continúa la numeración de las Specs 00–04)

81. El **carrito es anónimo y vive en sesión** (regla 00:13). No persiste en base de datos, no reserva stock y no requiere autenticación. Cada sesión tiene un único carrito.
82. El carrito conoce sus **líneas**. Cada línea referencia un **producto** (`product_id`) y una **cantidad comercial** entera: **cajas** si `unidad_venta=m2`, **unidades** (bolsas/piezas) si `unidad_venta=unidad` (regla 03:57).
83. **Derivación de cantidad en modo `m2`**: el cliente expresa la necesidad en **m²** (ingreso directo o largo×ancho vía `M2Calculator::m2DesdeDimensiones`). La cantidad de la línea se deriva como `cajas = ceil(m² / m2_por_caja)` (regla 00:9, ADR-003:22). En modo `unidad` la cantidad es directa, sin conversión.
84. **Desperdicio 10 % (solo modo `m2`)**: si el cliente activa el 10 %, se aplica **antes** de calcular cajas: `m²_a_cubrir = m² × 1,10` (`M2Calculator::aplicarDesperdicio`) → `cajas = ceil(m²_a_cubrir / m²_por_caja)` (regla 00:11). La **cantidad almacenada en la línea es siempre un entero de cajas** (nunca m² ni fracción de caja).
85. **Validación de stock contra cantidad solicitada**: el `stock` se expresa en la unidad de `unidad_venta` (03:59–60): en `m2` en **cajas** (int), en `unidad` en **unidades** (int). Al agregar o actualizar, se valida `cantidad_solicitada ≤ stock`. Un producto con `stock == 0` no es comprable (regla 00:5/03:63). Ejemplo: `stock=3` cajas y `cantidad=4` → rechazo. La validación se reejecuta en cada mutación y al consultar/recalcular el carrito.
86. **Validación de producto activo**: solo productos con `activo = true` pueden agregarse o mantenerse comprables (regla 04:70). Un producto inactivo no se agrega.
87. **Precio de la línea**: `subtotal_línea = precio_vigente_cents × cantidad`, donde `precio_vigente_cents` es el precio del catálogo al momento de la operación y **reutiliza la semántica de Spec 03/ADR-003 sin redefinirla**: en modo `unidad` es `precio_cents` directo (03:58); en modo `m2` es el precio por caja ya definido `precio_caja_cents = round(precio_cents × m2_por_caja)` con bcmath (Spec 03:59, ADR-003:26). Esta spec no introduce nueva regla de redondeo. No existe precio congelado en esta spec (pertenece a Spec 07).
88. **Subtotal del carrito**: `subtotal = Σ subtotal_línea`. El carrito **no calcula total final ni costo de envío** en esta spec.
89. **Agregar al carrito**: si el producto ya existe en el carrito, se **acumula** la cantidad (`cantidad_nueva = cantidad_existente + cantidad_solicitada`) y se revalida contra stock (regla 85). Si no existe, se crea la línea.
90. **Actualizar cantidad**: la cantidad se reemplaza por el valor solicitado (entero ≥ 1) y se revalida contra stock y estado activo. Cantidad 0 o vacía equivale a eliminar la línea (no persiste línea en 0).
91. **Eliminar y vaciar**: el cliente puede eliminar una línea o vaciar el carrito completo.
92. **Producto que deja de estar comprable — condición derivada al leer el carrito** (sin estado persistente ni operación especial de recalcular): al leer el carrito, cada línea es **comprable** si `producto.activo == true` **y** `cantidad ≤ stock` (reglas 85–86); en caso contrario es **no comprable**. Un producto que pasa a inactivo o sin stock suficiente no introduce un estado `no_disponible` en sesión: la condición se deriva en lectura, se informa en la vista y **bloquea el avance a checkout** hasta corregir o eliminar la línea.

## Matriz de permisos

El carrito es **público y anónimo** (como el catálogo, Spec 04:80). No hay roles.

| Acción | Público (sesión) |
|---|---|
| Ver carrito (`/carrito`) | ✓ |
| Agregar producto al carrito | ✓ (si activo y con stock suficiente) |
| Actualizar cantidad de una línea | ✓ (revalida stock/activo) |
| Eliminar línea / vaciar carrito | ✓ |
| Avanzar a checkout | — (Spec 07; bloqueado si hay línea no comprable) |
| Ver/definir costo de envío | — (Spec 06) |
| Ver total final | — (Spec 06/07) |

## Casos borde

- Producto inactivo al agregar → rechazo; producto en carrito que se desactiva → línea no comprable al recalcular (regla 92).
- Stock 0 al agregar → rechazo; stock insuficiente para cantidad solicitada (ej. 3→4) → rechazo con mensaje claro; stock que baja entre agregar y recalcular → línea no comprable.
- Cantidad no entera, 0, negativa o vacía → error de validación (0 equivale a eliminar).
- Modo `m2` con `m²` 0 o vacío → sin línea; `m2_por_caja` es del producto, no del input.
- Modo `unidad` con desperdicio activado → se ignora (no aplica).
- Carrito vacío → vista vacía con mensaje y enlace al catálogo.
- Sesión sin carrito → se inicializa vacío al primer agregado.
- Producto modo `m2` sin `m2_por_caja` → no debería existir (validado en Spec 03); si ocurre, rechazo.
- Concurrencia: dos agregados simultáneos en la misma sesión acumulan y revalidan; no hay lock en esta spec (el stock real se descuenta recién al pagar, ADR-005).

## Criterios de aceptación

- [x] Cliente anónimo agrega un producto modo `m2` indicando m² → línea con `cajas = ceil(m² / m2_por_caja)`, subtotal por cajas (precio por caja derivado con bcmath).
- [x] Cliente agrega modo `m2` con 10 % desperdicio → `m²_a_cubrir = m² × 1,10` antes de `ceil`; la línea guarda entero de cajas; subtotal sobre cajas resultantes.
- [x] Cliente agrega producto modo `unidad` por unidades → línea con unidades, subtotal directo, sin desperdicio ni conversión.
- [x] Agregar el mismo producto dos veces acumula cantidad y revalida contra stock; exceder stock → rechazo.
- [x] Actualizar cantidad a un valor ≤ stock → OK; a un valor > stock (ej. 3→4) → rechazo; a 0 → elimina la línea.
- [x] Producto con `stock==0` o `activo==false` no se puede agregar.
- [x] Producto en carrito que pasa a inactivo o sin stock suficiente → al ver/recalcular el carrito la línea figura como no comprable y bloquea checkout.
- [x] Eliminar una línea y vaciar el carrito funcionan.
- [x] `subtotal = Σ subtotal_línea` correcto en ambas unidades; el carrito no expone `total` ni `envío`.
- [x] Pint, PHPStan nivel 8 y Pest en verde; CI alineado.

## Decisiones arquitectónicas

- **Sesión, no tabla `carts`**: el carrito vive en `session('cart')` (array de líneas por `product_id`). YAGNI: no se crea tabla ni modelo `Cart` persistido en esta spec (`docs/arquitectura.md:58-65` fronteras futuras, principio 5/8).
- **Reuso de `M2Calculator`** (`app/Services/M2Calculator.php`, Spec 04, ADR-003): `m2DesdeDimensiones`, `aplicarDesperdicio`, `cajasNecesarias` (bcmath, `ceil`). Único lugar de reglas 00:9–11; no se duplica lógica.
- **Sin `ShippingCalculator` en esta spec**: el puerto `ShippingCalculator` (ADR-006) nace en Spec 06. El carrito no conoce envíos.
- **Sin `DiscountCalculator` ni `precio_congelado_cents` en esta spec**: pertenecen a Spec 09 y Spec 07 respectivamente; no se anticipan campos ni abstracciones.
- **Controlador delgado**: `CartController` (`show`, `add`, `update`, `destroy`, `clear`) delega en un servicio de dominio `Cart`/`CartService` puro (sin HTTP), que aplica reglas 81–92 y revalida contra `Product` (`activo`, `stock`, `unidad_venta`).
- **Rutas públicas** (sin auth) en `routes/web.php`: `GET /carrito` (`carrito.show`), `POST /carrito/agregar` (`carrito.add`), `PATCH /carrito/{producto}` (`carrito.update`), `DELETE /carrito/{producto}` (`carrito.remove`), `DELETE /carrito` (`carrito.clear`). No se usa `Route::resource` para mantener verbos explícitos del carrito.
- **Vistas**: `resources/views/cart/show.blade.php` con layout `layouts/site` (como catálogo, Spec 04), componente `cart-line`; Alpine solo para cantidad/desperdicio; mensajes de validación en español.
- **Sin reserva de stock**: coherente con ADR-005; el descuento de stock ocurre en `ConfirmPaymentAction` (Spec 07), no aquí.

## Evolución documentada (no arquitectura anticipada)

Esta spec **documenta** dependencias futuras sin crear código para ellas:

- **Spec 06 (Carrito + Envío)**: podrá agregar un costo de envío al flujo sin modificar las reglas 81–92 del carrito. Entonces `total = subtotal + shipping`. El carrito expone `subtotal`; el envío se suma fuera del carrito.
- **Spec 07 (Checkout)**: congelará precios y creará el pedido; validará stock/activo finales dentro de la transacción (ADR-005).
- **Spec 09 (Descuentos, opcional)**: entonces `total = subtotal + shipping - discount`. Sin `DiscountCalculator` anticipado.
- **Reserva con vencimiento**: decisión **explícitamente diferida** vinculada a ADR-005. No es un TODO en código. Trigger de reevaluación: tasa alta de pedidos `pendiente_de_pago` abandonados o colisiones de stock al confirmar pago. Requiere ADR propio si se activa.

## Tareas técnicas

- [x] Servicio de dominio `Cart`/`CartService` (sesión, reglas 81–92, `M2Calculator`).
- [x] `CartController` delgado + Form Requests (`AddToCartRequest`, `UpdateCartRequest`) con validación de cantidad entera ≥1.
- [x] Rutas públicas del carrito (`/carrito`, `/carrito/agregar`, etc.) en `routes/web.php`.
- [x] Vista `cart/show` (layout `layouts/site`, líneas, subtotal, estados no comprable) + componente `cart-line`.
- [x] Tests Pest: `tests/Feature/Carrito/` (agregar m2/unidad, desperdicio 10 % antes de ceil, acumulación y validación stock 3→4, inactivo, no comprable al recalcular, eliminar/vaciar, subtotal) y unit del servicio de carrito si aplica.
- [x] Verificación de calidad: pint, PHPStan nivel 8 (`app/`), Pest (una suite a la vez, `ceramica_test`), CI.
- [x] Actualizar `docs/arquitectura.md` (carrito en sesión, `M2Calculator` reuso) y `docs/roadmap.md` al cerrar la spec (no en este borrador).

## Nota de handoff para el agente implementador

La spec está **cerrada** (2026-09-03). Implementada con TDD en este orden:

1. Servicio `Cart`/`CartService` (sesión, reglas 81–92, `M2Calculator`).
2. Form Requests (`AddToCartRequest`, `UpdateCartRequest`).
3. `CartController` + rutas públicas.
4. Vista `cart/show` + componente.
5. Tests Pest (`tests/Feature/Carrito/`).

Reglas para esta tarea:

- **No editar** `docs/specs/` (salvo esta spec al aprobarla), `docs/adr/` ni `docs/roadmap.md`.
- **No crear** `ShippingCalculator`, `DiscountCalculator`, `precio_congelado_cents`, tabla `carts`, ni reserva de stock. `subtotal` sí, `total` no.
- Seguir `AGENTS.md` y `.ai/rules` (`grep -rin 'carrito\|m2\|stock' .ai/rules`).
- Tras editar PHP: `make format`; validar con `make lint` → `make stan` → `make test` (una suite).
- Tests que renderizan vistas requieren assets de Vite (`make npm-dev` o `make npm-build`).
- Registrar con `record-rule` cualquier regla durable nueva descubierta (p. ej. patrón de carrito en sesión).
