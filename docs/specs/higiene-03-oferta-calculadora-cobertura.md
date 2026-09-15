# Spec Higiene 03 — La oferta que no se cobra, la calculadora duplicada y la cobertura que falta

- **Estado**: **borrador — pendiente de aprobación del dueño** (2026-09-15). No se implementa nada
  hasta que se apruebe.
- **Origen**: verificación con `verificador-spec-codigo` de las specs **01**, **02**, **04** y
  `calidad-onboarding` (2026-09-15), más los cuatro hallazgos de la familia Spec 07 que la
  verificación del 2026-09-12 dejó anotados en el roadmap. Ninguna de las specs verificadas tenía
  reglas fantasma: todo lo que dicen está escrito. Lo que aparece es otra cosa.
- **Fuentes**: Spec 01 reglas 33–42, Spec 02 reglas 43–54, Spec 04 reglas 69–80, Spec 05 regla 87,
  Spec 07.2 reglas 108–111, Spec 07.3 reglas 116–119, Spec 07.4 reglas 123–126, Spec 08 reglas 146 y
  153, `calidad-onboarding` reglas 2 y 6, ADR-003 (centavos + bcmath), ADR-004 (auditoría),
  `PROJECT_PRINCIPLES.md`, `AGENTS.md`, `.ai/rules/*`.
- **Prefijo de reglas**: continúa **`HIG-10`** desde la Spec Higiene 02 (`HIG-04`–`HIG-09`), que a su
  vez continuó de la Higiene 01 (`HIG-01`–`HIG-03`). Estas reglas **no** entran en la numeración
  global 1–166: son correcciones para que el código cumpla reglas que ya existen, más **una única
  enmienda de regla de negocio** (HIG-10), que el dueño ya resolvió.

## Objetivo

Cerrar la distancia entre lo que cuatro specs cerradas afirman y lo que el código hace. Hay cinco
divergencias que afectan plata o cantidades cobradas, siete que contradicen su spec o devuelven 500
en producción, y diez reglas que están bien implementadas pero que **se pueden borrar enteras con los
436 tests en verde**.

Se entrega en **dos fases**: `03.a` lo que afecta plata y lo que rompe; `03.b` la cobertura. La
justificación del corte está en la nota de handoff.

## Por qué existe esta spec

Higiene 02 dejó escrito que *"los gates de calidad validan que el código esté sano, no que implemente
la regla"*. Esta verificación lo confirma por quinta vez, y suma un patrón nuevo que conviene
nombrar, porque explica tres de los hallazgos más caros de este documento:

**Lo que se muestra y lo que se cobra son dos caminos distintos, y nadie los compara.** El precio de
oferta se pinta en la ficha y no entra en ningún cálculo. La calculadora de m²→cajas de la ficha es
una segunda implementación que da un número distinto del que arma el carrito. El costo de envío
—regla 123, seis días cobrando de menos— fue exactamente lo mismo. En los tres casos el número
correcto está en la pantalla y nunca llega al total.

El otro patrón ya tiene nombre en el repo desde la 08.b: **probar el puerto no prueba el adaptador**.
HIG-11 es su tercera aparición.

## Fase 03.a — Lo que afecta plata y lo que rompe

### HIG-10. El precio de oferta es el que se cobra (enmienda a la regla 87)

**Decisión del dueño (2026-09-15): la oferta se cobra.**

**Estado actual**: `precio_oferta_cents` aparece en `app/` únicamente en el ABM de productos
(`ProductController.php:56`, `:98`) y en el modelo — `fillable`, `casts`, `estaEnOferta()`
(`Product.php:122`), el porcentaje de descuento (`:131`) y el scope de filtro (`:149`). **No aparece
ni en `app/Services/Cart.php` ni en `app/Actions/PlaceOrderAction.php`.** Las dos calculan igual:

```php
// Cart.php:70 y PlaceOrderAction.php:97-99, idénticos
$product->isM2Mode() ? ($product->precioCajaCents() ?? 0) : $product->precio_cents
```

Y `precioCajaCents()` (`Product.php:103-110`) deriva de `precio_cents`, no de la oferta.

**Por qué la regla 87 no alcanzaba para decidirlo**: se contradice a sí misma. Llama a
`precio_vigente_cents` *"el precio del catálogo al momento de la operación"* y acto seguido lo define
como `precio_cents` en modo unidad y `round(precio_cents × m2_por_caja)` en modo m², **sin mencionar
`precio_oferta_cents`**. El código implementa la letra; el glosario
(`ubiquitous-language.md:33`, *"Oferta: precio promocional temporal"*) y la regla 73 —que manda
mostrar el precio de lista tachado con el % de descuento— dicen lo otro. No era un defecto de
implementación: era una decisión de negocio que nunca se había tomado.

**Corrección**: `precio_vigente_cents` pasa a ser el **precio de oferta cuando la oferta está
activa** (`estaEnOferta()`, regla 79), y el precio de lista en caso contrario. En modo `m2` la
derivación a precio por caja se aplica sobre ese precio vigente, con la misma fórmula y el mismo
`bcmath` de siempre: esta spec **no introduce ninguna regla de redondeo nueva**.

El lugar de la corrección es `Product`, no los dos llamadores: hoy `Cart` y `PlaceOrderAction`
duplican la misma expresión, y corregir en dos lados es cómo se vuelve a divergir. `precioCajaCents()`
y el precio de unidad salen de un único punto que ya contempla la oferta.

**La regla 87 queda enmendada**, con sincronía fechada en la Spec 05 y en la Spec 04. El texto
original se conserva, según la regla del repo de que las decisiones se marcan y no se borran.

**Por qué importa**: un producto exhibido a $1.500/m² con "25 % OFF" se cobra hoy a $2.000/m². El
cliente paga **33 % más que el precio exhibido**, y la diferencia viaja al pedido, al payload de
MercadoPago y a la factura. Además de la plata, precio exhibido ≠ precio cobrado es exposición
directa por lealtad comercial.

**Tests**: ningún test de `Carrito/`, `Orders/` ni `Checkout/` crea hoy un producto con oferta —el
estado `conOferta()` de la factory solo se usa en `tests/Feature/Catalogo/`—. Hay que cubrir: línea
de carrito con oferta en modo unidad y en modo m², pedido creado con oferta (que el
`precio_unitario_cents` congelado en `OrderLine` sea el de oferta), y el payload de MercadoPago con
el total correcto.

### HIG-11. `external_reference` y el adaptador real necesitan un test que los ejercite

**Estado actual**: los cinco tests que inspeccionan el payload de la preferencia solo afirman
`auto_return`, `back_urls.success` y `shipments`. Los de `store` y `retry` bindean fakes que
**sobrescriben `paymentUrl()` entero**, así que el cuerpo de
`app/Services/MercadoPagoGateway.php:91-107` —donde vive `'external_reference' => (string) $order->id`
en la línea 137— **nunca se ejecuta en la suite**.

**Corrección**: un test que ejercite el payload real y falle si `external_reference` desaparece o
cambia de valor. La costura ya existe y está documentada: `preferencePayload()` es `protected`
justamente porque `PreferenceClient` del SDK es `final` y no se puede mockear
(`.ai/rules/tests.md`).

**Por qué importa**: si esa línea desaparece, los 436 tests siguen verdes y **cada pago aprobado cae
en `webhook.order_not_found`**: el webhook no puede encontrar el pedido, queda en `PendingPayment`
para siempre, el stock nunca baja y el cliente ya pagó. Es la tercera aparición de *probar el puerto
no prueba el adaptador* —`revisor-entrega` la bloqueó en 08.b, y en la 07.4 quedó sin corregir—, y es
exactamente el circuito que la verificación manual con túnel va a ejercitar. **Conviene que llegue a
esa prueba ya corregido.**

### HIG-12. Un producto `M2` sin `m2_por_caja` debe lanzar `DomainException`, no venderse a cero

**Estado actual**: los casos borde de la Spec 07.2 dicen, textual: *"`m2_por_caja` null en `Unidad` →
`null` snapshot; en `M2` nunca null (Spec 03:59) — **si ocurre, `DomainException`**"*. El código hace
`($product->precioCajaCents() ?? 0)` en `app/Actions/PlaceOrderAction.php:97`, es decir **precio
cero**. La columna `products.m2_por_caja` es nullable y **no tiene `CHECK`**, así que cualquier camino
que no pase por el Form Request —seeder, importador, corrección a mano en la base— lo produce.

**Corrección**: lanzar `DomainException` cuando un producto en modo `M2` no tiene `m2_por_caja`, tal
como la spec ya dice. Aplica también a `Cart.php:70`, que tiene el mismo `?? 0`.

**Por qué importa**: el pedido se crea con `subtotal_cents: 0` y total = solo envío, MercadoPago cobra
el envío, y la auditoría registra el cero sin ningún error. No hay sincronía que enmiende esto: el
código y la spec dicen cosas distintas y la diferencia es plata.

### HIG-13. Una sola calculadora m²→cajas

**Estado actual**: la regla 75 y `docs/arquitectura.md:206-210` fijan a `M2Calculator` como *"único
lugar de las reglas de redondeo"*, y la Spec 05 dice que el carrito *"no duplica la lógica"*. El
carrito cumple (`CartController.php:110-130`). La **ficha no**: tiene su propia calculadora escrita a
mano en JavaScript (`resources/views/public/producto.blade.php:172-206`), con aritmética de punto
flotante contra el `bcmath` del servicio.

Comparadas las dos rutas sobre superficies de 0,01 a 50,00 m² y seis valores de `m2_por_caja`:
**209 divergencias en 30.000 combinaciones**, todas de una caja entera. Con el `m2_por_caja = 1,15`
de la factory:

| Superficie + 10 % | Ficha (JS) | Carrito (`M2Calculator`) |
|---|---|---|
| 11,50 m² → 12,65 m² | 12 cajas | 11 cajas |
| 23,00 m² → 25,30 m² | 23 cajas | 22 cajas |
| 1,05 m² → 1,155 m² | 2 cajas | 1 caja |

Los dos primeros son error de flotante puro (`12.65 / 1.15 = 11.000000000000002` en JavaScript, y el
`ceil` lo lleva a 12) y caen justo en los números redondos que el cliente tipea.

**Corrección**: la ficha deja de calcular por su cuenta. Queda a criterio del implementador la
técnica —consultar al servidor, o exponer el resultado ya calculado—, con un requisito no negociable:
**el número que muestra la ficha y el que arma el carrito salen del mismo cálculo**, y existe un test
que falla si vuelven a divergir.

**Además**: la calculadora y el formulario de compra son hoy dos widgets independientes —la
calculadora acepta largo × ancho, el formulario solo superficie—, así que el cliente está obligado a
retipear. Unificarlos es deseable pero **no** es parte de esta regla: se anota como deuda de UX para
una revisión de la Spec 04.

**Por qué importa**: el cliente calcula 12 cajas en la ficha, tipea los mismos m² en el formulario que
está tres centímetros más arriba, y el carrito le arma 11. Es la superficie donde el negocio promete
"calculá cuántas cajas necesitás" y devuelve dos respuestas distintas en la misma página.

### HIG-14. El 10 % de desperdicio no debe perderse por redondeo

**Estado actual**: `M2Calculator::aplicarDesperdicio()` trunca a dos decimales
(`bcdiv(..., '100', 2)`), así que 1,05 m² + 10 % = 1,155 m² se convierte en 1,15 m² y el carrito
cotiza **una sola caja de 1,15 m²** para cubrir una superficie de 1,155 m².

**Corrección**: el truncamiento no puede comerse el margen que el cliente pidió explícitamente. Queda
a criterio del implementador si se sube la precisión intermedia o si el redondeo se hace hacia arriba;
el requisito es que **las cajas cotizadas cubran siempre la superficie con desperdicio incluido**.

**Por qué importa**: el desperdicio existe para que el cliente tenga margen de corte y roturas. En las
superficies chicas hoy va la caja justa, que es precisamente lo contrario de lo que la regla promete.
Apareció de rebote al comparar las dos calculadoras de HIG-13.

### HIG-15. Vaciar el campo "Orden" de una categoría no puede devolver 500

**Estado actual**: `sort_order` es `nullable` en ambos Form Requests
(`StoreCategoryRequest.php:20`, `UpdateCategoryRequest.php:20`), el controlador lo lee con
`$request->validated('sort_order', 0)` (`CategoryController.php:45` y `:70`), y las Actions lo reciben
como `int $sortOrder` **no nullable** (`CreateCategoryAction.php:12`, `UpdateCategoryAction.php:12`).

Si el admin **borra** el valor del campo, `ConvertEmptyStringsToNull` lo transforma en `null`; la
clave **existe** en `validated()` con valor `null`, así que el default nunca se aplica y el `null`
llega al `int` → `TypeError`. Reproducido: 500 tanto al crear como al editar, sin guardar nada.

**Corrección**: normalizar el valor ausente a `0` antes de llegar a la Action —`integer()`, o
`prepareForValidation()`—, con test que mande `sort_order => ''` en create y en update.

**Por qué importa**: con `APP_DEBUG=false` el admin ve un Server Error opaco, pierde lo cargado y la
categoría no se crea ni se edita. Vaciar el campo es un gesto natural ("no quiero fijar orden"), no un
caso raro. El único test que hoy omite el orden lo hace **no mandando la clave**, que es justo el
camino que sí funciona.

### HIG-16. Eliminar la ruta de borrado de usuarios que la regla 37 prohíbe

**Estado actual**: `routes/web.php:58` hace `->except(['show'])`, que **no excluye `destroy`**. Queda
registrada `DELETE /admin/usuarios/{user}` apuntando a `UserController@destroy`, método que no existe.
La regla 37 dice, textual: *"Un usuario se desactiva, **nunca se borra**"*.

**Corrección**: `->except(['show', 'destroy'])`. Una línea.

**Por qué importa**: hoy cualquier disparo de ese verbo devuelve un 500 en producción en vez de un 403
o un 404. Pero lo más caro es el contrato: la app **anuncia** una operación de borrado de usuarios que
las Specs 00 y 01 prohíben, y `UserPolicy` no tiene `delete`. Si alguien implementa el método viendo
`usuarios.destroy` en `route:list`, el borrado queda autorizado **solo por el middleware `role:admin`**
— exactamente el patrón que HIG-06 y la regla 159 tuvieron que corregir.

### HIG-17. El carrito no puede mostrarle al cliente un stock negativo

**Estado actual**: la regla 146 y la sincronía de la Spec 04 dicen que *"el cliente nunca ve un número
negativo: en catálogo, **ficha y carrito** el producto figura como sin stock, sin cantidad"*. La
grilla y la ficha cumplen y tienen test (`StockNegativoTest.php`). La línea de carrito **no**:
`resources/views/components/cart-line.blade.php:30-31` no tiene guarda por `stock <= 0` y renderiza
literalmente *"Stock insuficiente — quedan **-3** cajas"*.

**Corrección**: la línea no comprable por stock muestra el remanente **solo cuando es positivo**; con
stock en cero o negativo dice que el producto no está disponible, sin número.

**Por qué importa**: es el escenario que la regla 145 fabrica a propósito. El cliente tiene el producto
en el carrito, otro pedido pagado deja el stock en −3, el cliente refresca `/carrito` y lee el estado
interno de reposición pendiente que la decisión del dueño del 2026-09-10 mandó esconder. Para él,
además, "quedan −3 cajas" no significa nada.

### HIG-18. Un GET al webhook debe responder 200, no 405

**Estado actual**: `routes/web.php:35` registra `/webhook/mercadopago` **solo como POST**. La regla 153
exige responder **200** ante todo lo que no haya que procesar, justamente para que MercadoPago no
reintente indefinidamente. Verificado con una entrega real el 2026-09-12 (*"Falla en entrega - 405"*,
evento `topic_merchant_order_wh`).

**Corrección**: aceptar GET en la ruta y responder 200 a todo lo que no sea una notificación de
`payment` procesable. **La firma sigue siendo obligatoria** para lo que sí se procesa (regla 154): esta
corrección no abre una puerta sin autenticar, solo deja de rechazar por verbo lo que la regla ya manda
ignorar con un 200.

**Por qué importa**: cada entrega por GET —el IPN viejo lo usa— queda marcada como fallida del lado de
MercadoPago y entra en su ciclo de reintentos, que es precisamente lo que la regla 153 quiere evitar.

### HIG-19. `shipping_address` debe validar contra el tamaño real de la columna

**Estado actual**: `app/Http/Requests/Checkout/StoreCheckoutRequest.php:25` acepta `max:500`; la
migración `2026_09_03_162109_create_orders_table.php:22` declara `string('shipping_address')`, es decir
`varchar(255)`. `CheckoutController::store` solo captura `DomainException`.

**Corrección**: alinear los dos. Queda a decisión del dueño **cuál de los dos manda**: subir la columna
a 500 (o a `text`) preserva direcciones largas; bajar la validación a 255 es una migración menos. La
recomendación es subir la columna: una dirección con entrecalles y referencias pasa los 255 sin
esfuerzo, y el `max:500` original sugiere que esa era la intención.

**Por qué importa**: una dirección de entre 256 y 500 caracteres pasa la validación, entra a la Action
y revienta con `QueryException`. El cliente ve un **500 con el carrito lleno**, en el último paso antes
de pagar.

### HIG-20. La pantalla de éxito debe mostrar los siete campos de la regla 119

**Estado actual**: `resources/views/checkout/success.blade.php:20-25` muestra `product_name`,
`cantidad` y `subtotal_cents`. Faltan **`product_codigo`, `marca`, `precio_unitario_cents` y
`specs`**. A diferencia de las otras desviaciones de la familia 07, esta **no está anotada en ninguna
sincronía**.

**Corrección**: mostrar los cuatro que faltan. Los datos ya están congelados en `OrderLine` por la
regla 111: no hay que ir a buscarlos a `Product`.

**Por qué importa**: es la única pantalla que le queda al cliente del pedido. Ante un reclamo por
precio no hay qué mostrarle — el precio unitario, que es justo el dato en disputa, no figura.

### HIG-21. Un producto sin stock no puede ofrecer el botón de compra

**Estado actual**: la regla 74 dice que el producto sin stock *"se muestra igual, con el badge 'Sin
stock' … **y sin acción de compra**"*. El badge está; el formulario de
`resources/views/public/producto.blade.php:77-99` **no está condicionado por el stock** y se renderiza
siempre, con su botón "Agregar al carrito".

**Corrección**: ocultar o deshabilitar el formulario cuando `stock <= 0`, coherente con lo que el
carrito ya hace con su botón "Finalizar compra".

**Por qué importa**: el servidor rechaza igual (`Cart::add` lanza `DomainException`), así que **no se
vende lo que no hay**: es ruido, no plata. Pero es literalmente lo que la regla prohíbe, y se coló
porque el criterio de aceptación correspondiente solo menciona el badge — así que la otra mitad de la
regla nunca se iba a mirar. Es una regresión que introdujo la Spec 05 al agregar el formulario a esta
vista sin releer la regla 74.

### HIG-22. Decidir qué pasa cuando el admin escribe un slug que ya existe

**Estado actual**: los casos borde de la Spec 04 dicen que *"si el admin edita el slug a uno ya
existente → **error de validación**"*. No existe `unique:products,slug` en los Form Requests
(`StoreProductRequest.php:22`, `UpdateProductRequest.php:22`, que solo validan formato):
`ProductSlugGenerator.php:31-35` le agrega `-2`, `-3`… **también al slug que el admin escribió a
mano**. El docblock del propio servicio (`:12-16`) afirma que *"un slug provisto por el admin que
colisiona se rechaza en la validación del Form Request"* — esa validación no existe. Y el test
`ProductSlugTest.php:95-114` **codifica el comportamiento opuesto al de la spec**, con
`assertSessionHasNoErrors()`.

**Corrección — decisión del dueño**: las dos opciones son legítimas y hay que elegir una.

- **Agregar `unique`**: el admin ve el error y corrige. Es lo que la spec dice hoy.
- **Enmendar la spec**: el sufijo silencioso se ratifica como comportamiento deseado, y se anota la
  sincronía.

En cualquiera de los dos casos, **el docblock y el test se corrigen** para que digan lo mismo que el
código.

**Por qué importa**: la unicidad en base está garantizada por el índice único y el generador, así que
no hay riesgo de datos. El riesgo es de URL: el admin escribe `porcelanato-gris` para una campaña,
guarda sin ver ningún error, y la URL publicada es `porcelanato-gris-2`. El folleto, el QR o el aviso
apuntan a otro producto o a un 404.

### HIG-23. Índice único en `categories.name` y `categories.slug`

**Estado actual**: las reglas 49 y 50 exigen nombre y slug únicos. Están implementadas y testeadas,
pero **solo en la capa HTTP** (`Rule::unique` en los Form Requests). La tabla `categories` no tiene
índice único: su único índice es `categories_pkey`. Compará con `products.slug`, que sí lo tiene
(`2026_08_06_022248_add_slug_to_products_table.php:30`).

**Corrección**: migración que agregue los dos índices únicos.

**Por qué importa**: cualquier camino que no pase por el formulario —seeder, comando, un importador
futuro, dos requests concurrentes— puede insertar dos categorías con el mismo slug, y
`/categorias/{categoria:slug}` resolvería siempre a la primera, dejando los productos de la segunda
**inalcanzables en el catálogo público**, sin ningún error visible.

## Fase 03.b — Cobertura: lo que hoy se puede borrar en verde

Todo lo de esta fase **ya está bien implementado**. Lo que falta es que algo falle si alguien lo borra.
Ninguna de estas reglas cambia comportamiento observable.

### HIG-24. `UserPolicy` necesita tests propios, no del middleware

**Estado actual**: la Spec 01 ubica la autorización en `app/Policies/UserPolicy.php`, y el controlador
la invoca con `Gate::authorize` en cada método. Pero los seis tests de denegación
(`UserManagementTest.php:16, 25, 34, 151, 248`) entran por HTTP, y el 403 lo devuelve el middleware
`role:admin` de `routes/web.php:57` **antes de que el controlador corra**. Se puede vaciar `UserPolicy`
entera —que devuelva `true` a cualquiera— y los seis tests siguen verdes. Verificado: **ningún archivo
de `tests/` menciona `UserPolicy`**.

**Corrección**: tests unitarios directos sobre la Policy, para las cuatro habilidades × los tres roles,
más el caso de usuario sin rol asignado (que HIG-03 resolvió con `try/catch DomainException → false`).

**Por qué importa**: la `OrderPolicy` de la Spec 08 copia su criterio y la referencia en un comentario.
Y en la 08 **ya se sacó `role:admin`** de las rutas de pedidos y despacho para dar acceso parcial al
vendedor (ver el comentario en `routes/web.php:44-46`). El día que eso pase en usuarios, la Policy es
el único control que queda, y hoy la suite no dice nada sobre si funciona.

### HIG-25. Test del límite de intentos de login

**Estado actual**: la regla 38 exige máximo 5 intentos fallidos por minuto por email + IP. Está bien
implementado en `app/Http/Requests/Auth/LoginRequest.php` —`tooManyAttempts(..., 5)` en `:63`, `hit()`
en `:53` con el decay de 60 s por defecto, la clave `email|ip` en `:80` y el `clear()` tras el login
exitoso en `:57`—. Pero `grep -rn "RateLimiter\|throttle\|Lockout" tests/` **no devuelve nada**.

**Corrección**: 5 POST fallidos y un sexto que asserte `trans('auth.throttle', ...)`, más un test de
que el contador se limpia al loguear bien. `CACHE_STORE=array` en `phpunit.xml`, así que el limitador
funciona in-process y no se filtra entre tests.

**Por qué importa**: el panel es la única superficie autenticada del sistema, con roles que confirman
pagos y despachan stock. Si alguien cambia el 5, sube el decay o —más probable— reordena
`authenticate()` de modo que `ensureIsNotRateLimited()` quede **después** del `Auth::attempt`, el panel
queda abierto a fuerza bruta y la suite sigue en verde. Ningún gate lo detecta.

### HIG-26. Tests del reseteo de contraseña: un solo uso y sin reactivar

**Estado actual**: la regla 41 promete un link de un solo uso y que *"el reseteo no reactiva un usuario
desactivado"*. Las dos cosas se cumplen: el un-solo-uso lo da el broker nativo, y el "no reactiva" es
correcto **por ausencia** — el callback de `NewPasswordController.php:41-53` hace `forceFill` de
`password` y `remember_token` y no toca `is_active`. Ninguna de las dos tiene test.

**Corrección**: un test que reutilice el token y espere el rechazo, y otro que resetee la contraseña de
un usuario desactivado y verifique que sigue sin poder entrar.

**Por qué importa**: es el tipo de invariante que se rompe sin querer. Alguien agrega
`'is_active' => true` al `forceFill` pensando en "desbloquear al usuario que reseteó", y **un empleado
desvinculado recupera el acceso al panel** pidiendo un reset de contraseña a su email todavía activo.
Con permisos de vendedor eso alcanza para ver pedidos y datos de clientes; con permisos de admin, para
todo.

### HIG-27. Test del `DatabaseSeeder` y de la idempotencia de los seeders

**Estado actual**: la regla 35 de la Spec 01 y la regla 2 de `calidad-onboarding` prometen que
`php artisan db:seed` crea los 3 roles y el admin inicial, y que `make setup` es idempotente. La
idempotencia está en el código (`Role::findOrCreate`, `updateOrCreate` en `AdminSeeder` y
`CategoriesSeeder`), pero `grep -rln "AdminSeeder\|DatabaseSeeder\|db:seed" tests/` no devuelve nada.

**Corrección**: un test que corra `DatabaseSeeder` y asserte 3 roles, 1 admin activo y 4 categorías; y
otro que lo corra **dos veces** y verifique que los conteos no cambian.

**Por qué importa**: es el único camino por el que nace el primer admin. Si `ADMIN_EMAIL` queda sin
valor en un entorno, `updateOrCreate(['email' => null], ...)` muere con violación de NOT NULL y **el
despliegue queda sin ningún usuario que pueda entrar al panel**, con el síntoma apareciendo recién
cuando alguien intenta loguearse. Y si alguien cambia un `updateOrCreate` por un `create`, `make setup`
sobre una base ya sembrada se rompe para el próximo que clone el repo.

**Nota**: `config/admin.php` define `'initial_email' => env('ADMIN_EMAIL')` sin default. Poner un
default o fallar con un mensaje claro es deseable y queda a criterio del implementador.

### HIG-28. Tests del sidebar por rol

**Estado actual**: las reglas 43 y 44 —qué secciones ve cada rol y qué placeholders están
deshabilitados— son dos de las tres reglas centrales de la mitad "panel" de la Spec 02, tienen sus
criterios de aceptación tildados y **no tienen una sola línea de test**.

**Corrección**: tests de que vendedor y depósito no ven Usuarios ni Categorías, y de que los
placeholders siguen deshabilitados. Afirmar el `href` exacto con `route(...)` y no la palabra suelta:
buscar `'Pedidos'` en la página entera da verdadero por cualquier texto del dashboard
(`.ai/rules/tests.md`).

### HIG-29. Test real del orden y la paginación del catálogo

**Estado actual**: la regla 80 fija orden por nombre y grillas de 12. Está implementada
(`CatalogController.php:15`, `:41`, `:57`, `:80`). El test que la cubre es un **falso verde**:

```php
Product::factory()->count(13)->create();
$this->get('/catalogo')->assertOk()->assertSee('13 productos');
```

Ese "13 productos" sale de `$productos->total()` (`catalogo.blade.php:17`), que es el total del
conjunto y **no el tamaño de página**: pasa igual con `paginate(50)`, con `paginate(5)` o sin
paginación. Y **ningún test verifica el orden por nombre**: se puede borrar el `orderBy('name')` de
las tres consultas con la suite en verde.

**Corrección**: assertar cuántas tarjetas se renderizan realmente, y el orden entre dos productos cuyos
nombres lo distingan. Ojo con la trampa que `revisor-entrega` encontró en 08.c: `paginate()` devuelve
las filas ordenadas por id aunque se borre el `ORDER BY`, así que el test tiene que usar nombres cuyo
orden alfabético sea **distinto** del orden de inserción.

**Además**: la primera mitad de la regla 80 dice que *"los listados respetan el orden de las
categorías (`sort_order`)"*, y el listado global `/catalogo` ordena solo por `name`. Si la intención
era la navegación —que sí respeta `sort_order`— la regla está mal redactada y se enmienda; si era el
listado, falta implementarla. **Queda como punto abierto para el dueño.**

### HIG-30. Dos tests de la Spec 02 que la regla pide y no existen

**Estado actual**:

- **Regla 53** (solo se borra una categoría vacía, *"con un mensaje claro"*): la regla vive en la
  Action, como corresponde (`DeleteCategoryAction.php:12-14`), y el único test que la cubre la invoca
  directamente — y vive en `tests/Feature/Productos/ProductOrderGuardTest.php:130`, el directorio de
  otra spec. **No hay test HTTP** que confirme que el admin ve el mensaje. Si alguien rompe el
  `try/catch` del controlador o la clave del `withErrors`, el admin vería un 500 y la suite seguiría
  verde.
- **Regla 51** (orden manual en el panel): `CategoryController::index` ordena por `sort_order`
  (`:23-25`) y nada lo verifica. El del catálogo público sí tiene test.

**Corrección**: los dos tests, en `tests/Feature/Categorias/`.

### HIG-31. El mínimo de 8 caracteres, en los tres caminos

**Estado actual**: la regla 39 fija la contraseña mínima en 8. Los tres caminos la cumplen —los Form
Requests de usuarios con `Password::min(8)` explícito, y el cambio propio y el reset con
`Password::defaults()`, que sin callback registrado devuelve `min(8)`—. Pero solo
`UserManagementTest.php:95` prueba el rechazo, y solo en el alta.

**Corrección**: cubrir el rechazo también en el cambio de contraseña propia y en el reset.

**Por qué importa**: `Password::defaults()` es configuración global. El día que alguien la personalice
—para exigir símbolos, o por accidente para relajarla— los dos caminos que dependen de ella cambian en
silencio y nada asserta el piso de 8.

### HIG-32. Deuda de segundo orden ya inventariada

El roadmap venía arrastrando una lista de cosas implementadas y correctas, pero sin test que las
proteja. Se incorporan acá para que dejen de estar sueltas: el payload del audit `order.created`
verifica 2 de 4 claves y no el actor `null`; de los tres `CHECK >= 0` de `orders` solo se testea uno;
nada verifica que `orders.shipping_cp` conserve el `0123`; el `trim` de `prepareForValidation` no se
ejercita por HTTP; y el `find` + redirect que HIG-09 eligió para la regla 117 no tiene test, así que un
"arreglo" a `findOrFail` pasaría sin ruido.

`OrderStatus::values()` sin llamadores **no** entra: es código muerto, no cobertura faltante. Borrarlo
o cablearlo es decisión de la Spec 08.

## Matriz de permisos

Esta spec **no altera la matriz de permisos de ninguna spec**, con una excepción y una aclaración:

| Acción | Público anónimo | admin | vendedor | depósito |
|---|---|---|---|---|
| `DELETE /admin/usuarios/{user}` | — | **se elimina la ruta** (HIG-16) | — | — |
| `GET /webhook/mercadopago` | ✓ responde 200 e ignora (HIG-18) | — | — | — |

El resto de las correcciones opera dentro de permisos ya establecidos. HIG-24 **no** cambia quién puede
hacer qué: agrega tests sobre el control que ya existe.

## Casos borde

- **HIG-10** — producto con `precio_oferta_cents` **mayor o igual** al de lista: no es oferta activa
  (regla 79), se cobra el precio de lista. Producto con oferta en modo `m2`: la derivación a precio por
  caja se aplica sobre el precio de oferta. Pedido ya creado antes de esta corrección: **no se toca**,
  su `precio_unitario_cents` está congelado por la regla 111 y `audit_logs` es inmutable (ADR-004).
- **HIG-10** — oferta que cambia mientras el producto está en el carrito: el carrito deriva en lectura
  (regla 92), así que el precio mostrado sigue al catálogo hasta que se crea el pedido. Es el
  comportamiento actual y no cambia.
- **HIG-12** — producto en modo `Unidad` sin `m2_por_caja`: correcto, sigue siendo `null`. La excepción
  es **solo** para modo `M2`.
- **HIG-13** — producto `M2` sin `m2_por_caja` en la ficha: la calculadora no se muestra (hoy tampoco),
  coherente con HIG-12.
- **HIG-17** — línea no comprable por producto inactivo **y** con stock negativo: manda el mensaje de
  inactivo, sin número.
- **HIG-18** — GET al webhook con firma válida y `type=payment`: **se ignora con 200**. MercadoPago
  entrega los pagos por POST (verificado el 2026-09-12, con y sin el tilde de "Pagos legacy"); aceptar
  un pago por GET sería inventar un formato que el proveedor no usa.
- **HIG-19** — dirección de exactamente 255 o 500 caracteres: el límite elegido se asserta en el borde.
- **HIG-21** — producto con stock negativo: mismo tratamiento que stock cero, sin número visible
  (regla 146).
- **HIG-27** — `ADMIN_EMAIL` ausente: el seeder debe fallar con un mensaje que lo diga, no con una
  violación de NOT NULL.

## Fuera de alcance

**Higiene documental pura, que no necesita spec aprobada.** Va en un PR `docs:` aparte, en paralelo:

- `AGENTS.md` abre con el bloque de Laravel Boost —en inglés, contra la regla 4— que manda correr
  `php artisan` y `npm` directamente en el host, donde `which php` devuelve *not found*. La corrección
  aparece 250 líneas más abajo. Hay que reordenarlo o anotarlo al pie.
- `docs/specs/02-panel-categorias.md:18` dice *"CRUD de categorías **jerárquicas**"*, contra su propia
  regla 45. En el código no quedó nada de la versión jerárquica.
- `docs/arquitectura.md:206-214` afirma que `M2Calculator` es el único lugar del redondeo y que no hay
  acción de compra sin stock. Se corrige **después** de HIG-13 y HIG-21, cuando pase a ser cierto.
- El placeholder deshabilitado "Pedidos" quedó duplicado con el link real que agregó la Spec 08
  (`navigation.blade.php:27` y `:83`). Es una línea de Blade, pero es UI: **puede entrar en 03.a** si
  el dueño prefiere, junto con la nota de enmienda en la Spec 02.
- El runbook del README: credenciales concretas del admin de desarrollo, `assets` en la lista de
  contenedores del paso 2, y mención de la base `ceramica_test`.
- `npm install` → `npm ci` en `docker-compose.yml:81` y `Makefile:58`, para alinear con el CI.

**Detectado y deliberadamente excluido, con su razón**:

- **El caso borde del admin que se saca el rol a sí mismo**: `UserPolicy::update` no excluye al propio
  usuario, así que un admin puede cambiarse a vendedor y dejar el sistema sin administradores — el
  mismo resultado que el guard de autodesactivación previene. **Decisión del dueño (2026-09-15): no
  debería poder, pero no es urgente.** Queda anotado para más adelante.
- **Unificar la calculadora con el formulario de compra** de la ficha: es mejora de UX, no divergencia.
  Corresponde a una revisión de la Spec 04.
- **La galería de imágenes** (la regla 73 dice "imagen(es)" y la ficha muestra solo la primera): es
  funcionalidad ausente, no una divergencia. Spec 04.
- **`/ofertas` sin filtros de specs ni de categoría, y el formulario lateral que no reenvía `q`**: la
  combinación funciona a nivel controlador y falla en la UI. Es una mejora de la Spec 04, no higiene.
- **La frescura del `ts` en la firma del webhook**: ya anotada como limitación conocida en
  `desarrollo-local.md`; la spec no la exige y la idempotencia de la regla 152 la vuelve inofensiva.
- **El criterio 1 de `calidad-onboarding`** (clon nuevo + `make setup`): no es verificable sin un
  entorno aparte con su propio volumen. Queda como tarea manual, no como regla.
- **`email_verified_at`**, vivo en la tabla y en `ProfileController` aunque `User` no implemente
  `MustVerifyEmail`: residuo inerte de Breeze. Borrarlo toca migración por una ganancia cosmética.

## Criterios de aceptación

**Fase 03.a**

- [ ] HIG-10: un producto con oferta activa se cobra al precio de oferta en el carrito, en el pedido y
      en el payload de MercadoPago, en modo `unidad` y en modo `m2`. La regla 87 queda enmendada con
      sincronía fechada en las Specs 05 y 04.
- [ ] HIG-11: existe un test que ejercita el payload real de la preferencia y **falla si se borra
      `external_reference`**.
- [ ] HIG-12: un producto `M2` sin `m2_por_caja` lanza `DomainException` en `PlaceOrderAction` y en
      `Cart`, invocados directamente.
- [ ] HIG-13: la ficha y el carrito devuelven el mismo número de cajas para los tres casos de la tabla,
      con test que falla si vuelven a divergir.
- [ ] HIG-14: las cajas cotizadas cubren la superficie con el desperdicio incluido; test en el borde
      (1,05 m² + 10 %).
- [ ] HIG-15: `sort_order` vacío crea y edita la categoría sin error; test con `sort_order => ''` en
      create y en update.
- [ ] HIG-16: `DELETE /admin/usuarios/{user}` no existe en `route:list`.
- [ ] HIG-17: la línea de carrito con stock ≤ 0 no muestra ningún número; test que falla si se quita la
      guarda.
- [ ] HIG-18: `GET /webhook/mercadopago` responde 200; la firma sigue siendo obligatoria para procesar
      un pago.
- [ ] HIG-19: validación y columna coinciden; test en el borde del límite elegido.
- [ ] HIG-20: la pantalla de éxito muestra los siete campos de la regla 119.
- [ ] HIG-21: con stock ≤ 0 no se renderiza el formulario de compra.
- [ ] HIG-22: la decisión del dueño queda implementada, y el docblock de `ProductSlugGenerator` y el
      test dicen lo mismo que el código.
- [ ] HIG-23: migración con índices únicos en `categories.name` y `categories.slug`.

**Fase 03.b**

- [ ] HIG-24: tests unitarios de `UserPolicy` que **fallan si la Policy devuelve `true` a cualquiera**,
      sin depender del middleware.
- [ ] HIG-25: test del bloqueo al sexto intento y de la limpieza del contador tras un login válido.
- [ ] HIG-26: test del token reutilizado y del usuario desactivado que resetea y sigue sin poder entrar.
- [ ] HIG-27: test del `DatabaseSeeder` y de su idempotencia corriéndolo dos veces.
- [ ] HIG-28: tests del sidebar por rol, afirmando el `href` exacto.
- [ ] HIG-29: test que asserta las tarjetas realmente renderizadas y el orden por nombre, con nombres
      cuyo orden alfabético difiera del de inserción. El punto abierto del `sort_order` en `/catalogo`
      queda resuelto por el dueño.
- [ ] HIG-30: test HTTP del borrado de categoría con productos y test del orden del listado del panel,
      en `tests/Feature/Categorias/`.
- [ ] HIG-31: el mínimo de 8 se asserta en el cambio propio y en el reset.
- [ ] HIG-32: la deuda de segundo orden queda cubierta.

**Ambas fases**

- [ ] Cada regla verificada **mutando la implementación**: borrarla o invertirla tiene que poner algún
      test en rojo. Es el procedimiento con el que este repo se defiende y no es opcional.
- [ ] `revisor-entrega` antes de cada push, pidiéndole explícitamente que mute la implementación.
- [ ] Pint, PHPStan nivel 8, Pest verde, CI verde, Pull Request a `main`.
- [ ] Las casillas de las Specs 01, 02, 04 y `calidad-onboarding` que quedaron verificadas se marcan, y
      las que siguen sin evidencia **no**.

## Tareas técnicas

- [ ] Este documento → revisión de `revisor-spec` → aprobación del dueño.
- [ ] Decidir los dos puntos abiertos: **HIG-19** (subir la columna o bajar la validación) y **HIG-22**
      (agregar `unique` o ratificar el sufijo). También el `sort_order` de `/catalogo` en HIG-29.
- [ ] Rama `fix/higiene-03a` **desde `main`** — la Higiene 02 se cortó de otra rama y arrastró 10
      commits ajenos; no repetir.
- [ ] TDD en el orden de las reglas: HIG-10 a HIG-14 primero (es lo único que afecta plata), después el
      resto de 03.a.
- [ ] Rama `fix/higiene-03b` desde `main` ya con 03.a mergeada.
- [ ] PR `docs:` aparte con la higiene documental de §Fuera de alcance, en paralelo.
- [ ] Anotar las sincronías: Spec 05 y Spec 04 (HIG-10), Spec 04 (HIG-13, HIG-21, y HIG-22 si se
      ratifica el sufijo), Spec 07.3 (HIG-20), Spec 02 (el placeholder duplicado, si entra).
- [ ] Actualizar `docs/arquitectura.md` **después** de HIG-13 y HIG-21.
- [ ] Actualizar `docs/roadmap.md` al cerrar cada fase.

## Nota de handoff

**Por qué dos fases.** La 03.a cambia comportamiento observable y toca plata: necesita revisión atenta,
regla por regla. La 03.b no cambia nada que un usuario pueda ver — son tests sobre código que ya
funciona — y su valor es que deja de ser posible borrar esas reglas en verde. Mezclarlas obliga a
revisar las dos cosas con el mismo cuidado, y la experiencia del repo es que lo que se revisa junto se
revisa peor.

**Por qué 03.a va antes que la verificación del webhook contra MercadoPago real.** HIG-11 protege
exactamente el circuito que esa prueba va a ejercitar: si `external_reference` se rompe, el pago
aprobado no encuentra su pedido. Conviene llegar a esa prueba con el adaptador ya cubierto, sobre todo
porque el acceso al panel de MercadoPago está bloqueado por ahora y la prueba no se puede repetir a
voluntad.

**Lo que esta spec no resuelve.** El patrón de fondo —dos caminos, el que se muestra y el que se cobra,
sin nada que los compare— seguirá vivo después de esta spec. HIG-10 y HIG-13 corrigen las dos
apariciones conocidas, pero no hay ningún test estructural que impida una tercera. Vale considerar,
después de esta spec, un test de más alto nivel que recorra ficha → carrito → pedido → payload de
MercadoPago sobre el mismo producto y verifique que el número es el mismo en las cuatro superficies.
Sería el único test del repo que cubriría la clase entera de defecto en lugar de sus instancias.
