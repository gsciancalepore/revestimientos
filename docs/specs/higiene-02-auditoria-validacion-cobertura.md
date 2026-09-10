# Spec Higiene 02 — Auditoría de precios, validación de PlaceOrderAction y cobertura del lock

- **Estado**: **cerrada (2026-09-10)** — aprobada por el dueño e implementada en la rama `fix/higiene-02` (274 tests en verde, Pint y PHPStan nivel 8 limpios). Pendiente el merge a `main` vía Pull Request.
- **Origen**: verificación automatizada de las specs cerradas contra el código (agente
  `verificador-spec-codigo`, 2026-09-10), confirmada a mano sobre el código y la base de desarrollo.
- **Fuentes**: Spec 03 regla 68, Spec 07.2 reglas 108 y 109, Spec 07.3 regla 117, Spec 07.4 regla
  126, ADR-004 (auditoría), ADR-003 (centavos + bcmath), `AGENTS.md`, `.ai/rules/actions.md` y
  `tests.md`.
- **Prefijo de reglas**: continúa `HIG-04` desde la Spec Higiene 01 (`HIG-01`–`HIG-03`), para no
  colisionar. Estas reglas **no** entran en la numeración global 1–166: son correcciones para que
  el código cumpla reglas que ya existen, no reglas de negocio nuevas.

## Objetivo

Corregir cinco divergencias entre lo que las specs cerradas dicen y lo que el código hace. Una está
corrompiendo datos hoy; dos son bombas de tiempo que explotan cuando la Spec 08 sume un segundo
llamador; una es un test que miente; y la última se corrige en el papel, no en el código.

## Por qué existe esta spec

El proceso SDD del repo tiene un agujero estructural que este trabajo dejó a la vista: **los gates
de calidad validan que el código esté sano, no que implemente la regla**. Pint, PHPStan nivel 8 y
261 tests en verde conviven con una regla de auditoría que guarda el dato equivocado y con un test
que no llama nunca a la acción que dice probar.

Ya había pasado con la regla 123 —seis días cobrando el subtotal en vez del total— y con la regla
67, diferida y olvidada. Esta spec cierra los casos encontrados; el agente `verificador-spec-codigo`
queda en el repo para que la búsqueda sea repetible.

## Reglas

### HIG-04. La auditoría de precio y stock debe guardar el valor anterior real

**Estado actual**: `app/Actions/UpdateProductAction.php:65-72` llama a `$product->save()` **antes**
de leer `$product->getOriginal(...)`. `Model::save()` ejecuta `finishSave() → syncOriginal()`, así
que para cuando se lee el "original" ya es el valor recién guardado. El payload auditado queda
`{"previous": X, "new": X}`.

**Verificado en la base de desarrollo (2026-09-10)**: los dos únicos registros existentes, escritos
por ediciones reales del panel, están corruptos:

```
product.price_changed subj=1 payload={"previous":750000,"new":750000}
product.price_changed subj=2 payload={"previous":1200000,"new":1200000}
```

**Corrección**: capturar los valores originales **antes** del `save()`, como ya hace
`UpdateUserAction.php:24` con el rol previo. Aplica a `product.price_changed` y a
`product.stock_changed`.

**Los dos registros existentes no se pueden reparar**: el valor anterior se perdió y no es
reconstruible desde ninguna fuente. Se dejan como están; `audit_logs` es inmutable por ADR-004.

**Por qué importa**: la regla 68 existe para poder responder "¿a qué precio estaba antes y desde
cuándo se vendió mal?". Hoy la auditoría no puede responder ninguna de las dos. Y el mismo bug
afecta al stock, justo cuando la Spec 08 empieza a descontarlo.

### HIG-05. El test de auditoría debe verificar el payload, no solo que la fila exista

**Estado actual**: `tests/Feature/Productos/ProductManagementTest.php:184-210` hace
`assertDatabaseHas('audit_logs', ['action' => 'product.price_changed', 'subject_id' => ...])`.
Nunca mira el payload, así que HIG-04 pasó el gate durante meses.

**Corrección**: assertar el contenido del payload — `previous` es el valor de antes, `new` el de
después — para precio y para stock. Sin esto, HIG-04 se puede volver a romper sin que nada avise.

**Regla general que deja establecida**: un test de auditoría que solo verifica que la fila existe no
cubre la regla; lo que la regla promete es el contenido. Se anota en `.ai/rules/tests.md`.

### HIG-06. `PlaceOrderAction` debe validar lo que la regla 108 dice que valida

**Estado actual**: la regla 108 (Spec 07.2) exige validar `customer_name`/`customer_phone`
requeridos, `customer_email` con formato, `shipping_cp` contra `^[0-9]{4}$` y `payment_method` en el
enum. `app/Actions/PlaceOrderAction.php:35-48` solo valida el carrito y `payment_method`. Los tipos
de PHP garantizan que sean strings y nada más: `''` y `'abc'` pasan.

**Por qué hoy no se nota**: hay un único llamador, `CheckoutController`, y entra por
`StoreCheckoutRequest` (regla 116), que sí valida todo correctamente y está bien testeado. La regla
se cumple **por accidente**, no por diseño.

**Por qué hay que resolverlo antes de la Spec 08**: la fase 08.c suma confirmación manual desde el
panel y la Spec 08.2 sumará ventas por WhatsApp. Ese segundo llamador no va a pasar por
`StoreCheckoutRequest`. Un pedido con `shipping_cp = 'abc'` no matchea ninguna tarifa,
`ShippingCalculator` devuelve `disponible = false` y la regla 118 congela `shipping_cost_cents = 0`:
**el pedido sale con envío gratis y sin dirección utilizable, en silencio y sin error**. Es
exactamente el patrón de la regla 123: el dinero se pierde en una rama que nadie ejercita.

**Corrección**: mover las validaciones a la Action, lanzando `DomainException` como el resto de sus
validaciones. La validación del `FormRequest` **se mantiene**: es la que produce el 422 con mensajes
en español para el usuario. La de la Action es la red de seguridad del dominio, no su reemplazo.

**Tests**: email malformado, CP que no matchea el regex, CP con espacios, nombre y teléfono vacíos,
todos invocando la Action directamente.

### HIG-07. La revalidación bajo `lockForUpdate` necesita cobertura real

**Estado actual**: la revalidación de la regla 109 existe y es correcta
(`app/Actions/PlaceOrderAction.php:67-73`). Lo que no existe es su cobertura:

- `tests/Feature/Orders/PlaceOrderTest.php:52-70` — titulado *"producto activo=false dentro de lock
  lanza DomainException y rollback mantiene carrito"* — es una tira de comentarios donde el autor
  razona por qué no logra armar el escenario, y termina en
  `expect(app(Cart::class)->hasUnpurchasable())->toBeTrue()`. **Nunca llama a `execute()`.**
- *"stock insuficiente lanza DomainException"* (línea 72) sí llama a `execute()`, pero corta en la
  prevalidación `hasUnpurchasable()` de la línea 39 y nunca llega al lock.
- *"concurrencia PostgreSQL: lockForUpdate serializa stock"* (línea 211) admite en su propio
  comentario que *"por ahora solo verifica que no hay deadlock"*: ejecuta dos pedidos
  **secuenciales**.

Se pueden borrar las líneas 67-73 y los 261 tests siguen en verde.

**Corrección**: un test que llegue efectivamente al lock. El obstáculo real es que la prevalidación
`hasUnpurchasable()` intercepta antes cualquier escenario armado desde el carrito; hay que mutar el
producto **después** de esa prevalidación, lo que exige un punto de intervención dentro de la
transacción. Queda a criterio del implementador la técnica, con dos requisitos: el test tiene que
llamar a `execute()`, y tiene que fallar si se borran las líneas 67-73.

**Además**: la Spec 07.2 afirma en sus casos borde que *"existe cobertura de concurrencia sobre
PostgreSQL que verifica que dos operaciones concurrentes sobre el mismo stock no pueden confirmar
ambas la compra"*. Eso hoy es falso. Si el test real de concurrencia no resulta viable, **se enmienda
la spec** en lugar de dejar escrita una promesa que el código no respalda.

**Por qué importa ahora**: hoy el lock no protege nada, porque el stock no se descuenta. La Spec 08
regla 143 lo convierte en la única defensa contra dos clientes comprando la última caja.

### HIG-08. Test del guard de estado en el reintento de MercadoPago

**Estado actual**: `CheckoutController.php:84-86` valida correctamente `payment_method === 'mercadopago'`
**y** `status === PendingPayment` (regla 126). Pero el único test que ejercita el `abort(403)` entra
por la primera condición (`MercadoPagoTest.php:186`, pedido con `transferencia`). No hay ningún test
con un pedido `mercadopago` en estado `paid`.

**Corrección**: agregar ese test.

**Por qué importa**: hoy es inofensivo porque ningún pedido llega a `paid` (regla 128). Cuando la
fase 08.b mueva pedidos por webhook, si ese guard se rompe, un cliente que vuelva a
`/checkout/exito` con la sesión viva genera una preferencia nueva sobre un pedido ya pagado y
**paga dos veces el mismo pedido**. Es un test de una línea que hoy no cuesta nada y después vale
una devolución.

### HIG-09. Enmendar la regla 117: `success` usa `find` + redirect, no `findOrFail`

**Estado actual**: la regla 117 y los casos borde de las Specs 07.3 y 07.4 dicen que
`GET /checkout/exito` con un `order_id` inexistente responde `404` vía `findOrFail`.
`CheckoutController.php:108-112` usa `find()` y redirige a `carrito.show` si es `null`.

**Corrección: se enmienda la spec, no el código.** El redirect es mejor experiencia que un 404, y el
escenario de tamper que motivaba el `findOrFail` no existe: la sesión es server-side. Se anota la
sincronía en la Spec 07.3.

Misma regla, desviación cosmética que **no se corrige**: `show()` pasa a la vista `lines`,
`subtotal` y `categorias` en vez de los seis argumentos que la regla enumera. Es inocuo porque
`show` redirige antes en los dos casos que usarían los que faltan. Se anota, no se toca.

## Fuera de alcance

Detectadas por la misma verificación y **deliberadamente no incluidas**, con su razón:

- **Regla 62, caso borde "clave con valor vacío se elimina del JSON"**: no está implementado en
  ningún lado. Es funcionalidad ausente, no una divergencia: corresponde a una revisión de la Spec
  03, no a higiene.
- **Regla 83, `M2Calculator::m2DesdeDimensiones()` sin llamadores**: el cálculo autoritativo es
  server-side y usa bcmath, así que la regla se cumple; el método es código muerto. Borrarlo o
  cablearlo es una decisión de la Spec 04, no un defecto.
- **Regla 87 / ADR-003, `Cart::lines()` usa `*` en vez de bcmath** y `Product::precioCajaCents()`
  pasa por `float` en el borde: a los valores reales del negocio no hay pérdida de precisión. Es un
  atajo que ADR-003 quiso prohibir, pero corregirlo toca el carrito entero sin cambiar ningún
  resultado observable. Se anota como deuda.
- **Nombre engañoso del test `POST /checkout mercadopago tambien crea pedido`**
  (`CheckoutTest.php:76`): afirma el camino de error con un nombre que sugiere el feliz. Ya no
  depende del ambiente, que era el problema real. Renombrarlo es trivial y puede ir en esta spec si
  el dueño quiere, pero no justifica una regla.

## Criterios de aceptación

- [x] HIG-04: `product.price_changed` y `product.stock_changed` registran el valor anterior real.
- [x] HIG-05: los tests assertan el contenido del payload, y fallan si se revierte HIG-04.
- [x] HIG-06: `PlaceOrderAction` lanza `DomainException` ante email inválido, CP que no matchea el
      regex, y nombre o teléfono vacíos, invocada directamente sin pasar por HTTP. El checkout HTTP
      sigue devolviendo 422 con los mensajes en español (sin regresión en los tests de la 07.3).
- [x] HIG-07: existe un test que llama a `execute()`, llega a la revalidación bajo lock y **falla si
      se borran las líneas 67-73**. Si no resulta viable, la Spec 07.2 queda enmendada en su lugar.
- [x] HIG-08: test de reintento sobre un pedido `mercadopago` en estado `paid` → 403.
- [x] HIG-09: sincronía anotada en la Spec 07.3; sin cambios de código.
- [ ] Pint, PHPStan nivel 8, Pest verde, CI verde, PR a `main`.

## Tareas técnicas

- [x] Este documento → aprobación del dueño (2026-09-10).
- [x] Rama `fix/higiene-02`.
- [x] TDD en orden de gravedad: HIG-04 y HIG-05 primero (es lo único que corrompe datos hoy), luego
      HIG-06 y HIG-07 (bloquean la Spec 08), después HIG-08 y HIG-09.
- [x] Anotar en `.ai/rules/tests.md` que un test de auditoría debe verificar el payload.
- [x] Actualizar `docs/roadmap.md` al cerrar.

## Nota de handoff

**Esta spec va antes que la Spec 08**, no después. HIG-06 y HIG-07 son precondiciones: la 08 suma un
segundo llamador a `PlaceOrderAction` y convierte el `lockForUpdate` en la única defensa contra la
sobreventa. Implementar la 08 primero significa construir sobre las dos cosas que esta spec arregla.

## Resultado (2026-09-10)

- **HIG-04/HIG-05**: `UpdateProductAction` captura los originales antes del `save()`; el test
  assertea `previous`/`new` de precio y stock y falla si se revierte la corrección. Los dos
  registros corruptos en desarrollo se dejan como están (`audit_logs` es inmutable, ADR-004).
- **HIG-06**: las validaciones de la regla 108 viven en `PlaceOrderAction` y lanzan
  `DomainException`; `StoreCheckoutRequest` sigue produciendo el 422 en español, sin regresión en
  los tests de la 07.3. Sincronía anotada en la Spec 07.2.
- **HIG-07**: dos tests llegan efectivamente a la revalidación bajo lock y **fallan si se borra**
  (verificado quitándola). El test de concurrencia real no resultó viable —`RefreshDatabase` envuelve
  cada test en una transacción—, así que se enmendó la Spec 07.2 retirando esa promesa, tal como
  esta regla preveía.
- **HIG-08**: test de reintento sobre un pedido `mercadopago` en `paid` → 403, verificado que falla
  si se quita la condición de estado del guard.
- **HIG-09**: sincronía anotada en la Spec 07.3, sin cambios de código.
- **Suite**: 261 → **274 tests** en verde; Pint limpio, PHPStan nivel 8 sin errores.
