# Spec Higiene 03 — La oferta que no se cobra, la calculadora duplicada y la cobertura que falta

- **Estado**: **aprobada por el dueño** (2026-09-16); **fase 03.a implementada** (2026-09-17,
  rama `fix/higiene-03a`, 462 tests — 436 base + 26 nuevos — cada regla verificada mutando la
  implementación); fase 03.b pendiente.
- **Origen**: verificación con `verificador-spec-codigo` de las specs **01**, **02**, **04** y
  `calidad-onboarding` (2026-09-15), más los cuatro hallazgos de la familia Spec 07 y el del
  webhook (`GET` → 405) que las verificaciones del 2026-09-12 dejaron anotados en el roadmap y en la
  §Sincronía de la Spec 08. Ninguna de las specs verificadas tenía
  reglas fantasma: todo lo que dicen está escrito. Lo que aparece es otra cosa.
- **Fuentes**: Spec 01 reglas 33–42, Spec 02 reglas 43–54, Spec 04 reglas 69–80, Spec 05 regla 87,
  Spec 07.2 reglas 108–111, Spec 07.3 reglas 116–119, Spec 07.4 reglas 123–126, Spec 08 reglas 146 y
  153, `calidad-onboarding` reglas 2 y 6, ADR-003 (centavos + bcmath), ADR-004 (auditoría),
  `PROJECT_PRINCIPLES.md`, `AGENTS.md`, `.ai/rules/*`.
- **Prefijo de reglas**: continúa **`HIG-10`** desde la Spec Higiene 02 (`HIG-04`–`HIG-09`), que a su
  vez continuó de la Higiene 01 (`HIG-01`–`HIG-03`). Estas reglas **no** entran en la numeración
  global 1–166: son correcciones para que el código cumpla reglas que ya existen.
- **Qué contrato toca esta spec**, declarado por adelantado: **cuatro reglas de negocio enmendadas**
  con su sincronía fechada — la **87** (HIG-10, decisión del dueño del 2026-09-15), la **92**
  (HIG-12, tercera condición no-comprable), la **68** (HIG-33/PA-2, la auditoría cubre la oferta,
  decisión del dueño del 2026-09-16) y la **153** más su fila de matriz (HIG-18, que suma el verbo
  `GET` a la ruta del webhook); más la **sincronía de la regla 44** (HIG-28, sin enmendar su
  contenido más allá de dejarla al día) y la **eventual sincronía de la regla 75** (HIG-13, solo si
  el fallback sin estimación en vivo resulta necesario — ver HIG-13). Ninguna otra regla, ADR ni
  matriz cambia. En particular,
  `precioCajaCents()` **no se toca**, así que la regla 59 de la Spec 03, la regla 3 de la Spec 00
  y el punto 5 de **ADR-003 quedan intactos** — ver HIG-10. El punto 4 de ADR-003 tampoco se toca:
  no fija precisión intermedia, así que HIG-14 no lo enmienda — ver HIG-14.

## Objetivo

Cerrar la distancia entre lo que cuatro specs cerradas afirman y lo que el código hace. Son **25
reglas**: seis afectan plata o cantidades cobradas, nueve contradicen su spec o devuelven 500 en
producción (incluido el placeholder duplicado HIG-28a), y diez están bien implementadas pero **se
pueden borrar enteras con los 436 tests en verde**.

Se entrega en **dos fases**: `03.a` lo que afecta plata y lo que rompe (más HIG-28a, que va en
03.a solo para no colisionar con HIG-28); `03.b` la cobertura. La
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
(`ProductController.php:56`, `:98`) y en el modelo — `fillable`, `casts`, `tieneOfertaActiva()`
(`Product.php:120`), el porcentaje de descuento (`:125`) y el scope de filtro (`:147`). **No aparece
ni en `app/Services/Cart.php` ni en `app/Actions/PlaceOrderAction.php`.** Las dos calculan igual:

```php
// Cart.php:70 y PlaceOrderAction.php:97-99, idénticos
$product->isM2Mode() ? ($product->precioCajaCents() ?? 0) : $product->precio_cents
```

Y `precioCajaCents()` (`Product.php:103-111`) deriva de `precio_cents`, no de la oferta.

**Por qué la regla 87 no alcanzaba para decidirlo**: se contradice a sí misma. Llama a
`precio_vigente_cents` *"el precio del catálogo al momento de la operación"* y acto seguido lo define
como `precio_cents` en modo unidad y `round(precio_cents × m2_por_caja)` en modo m², **sin mencionar
`precio_oferta_cents`**. El código implementa la letra; el glosario
(`ubiquitous-language.md:33`, *"Oferta: precio promocional temporal"*) y la regla 73 —que manda
mostrar el precio de lista tachado con el % de descuento— dicen lo otro. No era un defecto de
implementación: era una decisión de negocio que nunca se había tomado.

**Corrección**: `precio_vigente_cents` pasa a ser el **precio de oferta cuando la oferta está
activa** (`tieneOfertaActiva()`, regla 79), y el precio de lista en caso contrario. En modo `m2` la
derivación a precio por caja se aplica sobre ese precio vigente, con la misma fórmula y el mismo
`bcmath` de siempre: esta spec **no introduce ninguna regla de redondeo nueva**.

El lugar de la corrección es `Product`, no los dos llamadores: hoy `Cart` y `PlaceOrderAction`
duplican la misma expresión, y corregir en dos lados es cómo se vuelve a divergir.

**`precioCajaCents()` NO se toca.** Ese método es la implementación literal de la regla 59 de la
Spec 03, de la regla 3 de la Spec 00 y del **punto 5 de ADR-003**, que definen
`precio_caja_cents = round(precio_cents × m2_por_caja)` sobre el **precio de lista**. Cambiarlo
enmendaría una ADR aceptada y dos reglas más, para nada: "precio por caja" y "precio vigente" son
conceptos distintos y conviene que sigan siéndolo.

Se agrega en su lugar un **punto único nuevo** —`precioVigenteCents()`, y su derivación por caja—
que resuelve la oferta y que es lo que consumen `Cart` y `PlaceOrderAction`. Con esto la **única**
regla de negocio enmendada es la 87, que es exactamente lo que esta spec declara.

**La regla 87 queda enmendada**, con sincronía fechada en la Spec 05 y en la Spec 04. El texto
original se conserva, según la regla del repo de que las decisiones se marcan y no se borran. El
texto de reemplazo vive **en esta spec** para que la sincronía sea transcripción y no
interpretación, y es el siguiente:

> **Regla 87 (texto de reemplazo, 2026-09-16).** `precio_vigente_cents` es el precio del catálogo
> al momento de la operación que efectivamente se cobra: el **precio de oferta
> (`precio_oferta_cents`) cuando la oferta está activa** (`tieneOfertaActiva()`, regla 79), y el
> precio de lista (`precio_cents`) en caso contrario. En modo `unidad` el vigente es ese valor. En
> modo `m2` el vigente por caja es `round(precio_vigente × m2_por_caja)` con la misma fórmula y el
> mismo `bcmath` de siempre; esta regla **no introduce ninguna regla de redondeo nueva**. El
> `subtotal` es la suma de `precio_vigente × cantidad` sobre las líneas comprables (regla 92).

**Guard PA-1 — Decisión del dueño (2026-09-16): la oferta en `0` se rechaza.** `precio_oferta_cents`
pasa a validarse con `['nullable','integer','min:1']` en `StoreProductRequest.php:27` y
`UpdateProductRequest.php:27`: un tipeo de `0` devuelve 422 y nunca publica mercadería gratis. No
se inventa ningún umbral de descuento máximo: un 99 % OFF explícito sigue siendo legítimo.

**Documentos que quedan desactualizados y hay que sincronizar**: `ubiquitous-language.md:20`
("Precio por caja"), `:64` ("Subtotal") y el bullet "Líneas" de `arquitectura.md` §Carrito, que
transcriben la fórmula de la regla 87. Además, **`precio vigente` no existe hoy como término del
glosario** —solo aparece dentro de la definición de "Subtotal"— y con esta regla pasa a ser el
concepto central de qué se cobra: le corresponde su propia fila.

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

**Estado actual — son dos huecos distintos, y conviene no confundirlos** (el borrador original los
mezclaba, y la corrección es mucho más barata de lo que parecía):

1. **`external_reference` no está afirmado, pero sí se ejecuta.** `preferencePayload()` —donde vive
   `'external_reference' => (string) $order->id`, en `app/Services/MercadoPagoGateway.php:137`— lo
   ejercitan cinco tests a través de `PayloadInspectorGateway`
   (`tests/Feature/Checkout/MercadoPagoTest.php:46-53`, usado en `:231`, `:244`, `:257`, `:277` y
   `:298`). Esos tests solo afirman `auto_return`, `back_urls.success` y `shipments`: **ninguno
   asserta `external_reference`**. La costura existe y funciona; falta el assert.
2. **El cuerpo de `paymentUrl()` (`:91-107`) sí es inalcanzable**: el guard de `init_point` vacío y
   el `Order::update(['mp_preference_id', 'mp_init_point'])` no los ejecuta ningún test, porque los
   fakes de `store` y `retry` sobrescriben el método entero. Y acá la costura **no existe**:
   `PreferenceClient` es `final` y el constructor lo tipa (`MercadoPagoGateway.php:17-20`), así que
   no se puede inyectar un doble.

**Corrección**:

- Para (1): agregar el assert de `external_reference` a los tests que ya inspeccionan el payload.
  Es una línea, y es lo que protege el circuito del webhook.
- Para (2): **se declara deliberadamente sin cubrir**, con el mismo criterio que la 08.b usó para
  `PaymentStatusQuery` y que `.ai/rules/tests.md` ya documenta: cuando el SDK vuelve la costura
  imposible, se afirma lo que sí se puede afirmar en vez de fingir cobertura. Si el implementador
  encuentra una costura razonable —sin reescribir el gateway para hacerlo testeable— puede cubrirlo;
  no es requisito de esta regla. El hueco (guard de `init_point` vacío + `update mp_*`) queda además
  registrado como deuda conocida en §Nota de handoff, para que la aprobación no se lea como
  "circuito cubierto".

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

**Corrección — distinta según sea camino de escritura o de lectura**:

- **`PlaceOrderAction` (escritura)**: lanza `DomainException`, tal como la spec ya dice. Es el hueco
  real. El camino de escritura del carrito ya está cubierto: `CartController.php:113-115` tiene su
  guard.
- **`Cart::lines()` (lectura)**: **no** lanza. La línea se marca **no comprable**, que es el
  mecanismo que la regla 92 de la Spec 05 ya tiene para "esto no se puede comprar y hay que decirlo
  sin romper la página". `lines()` se llama desde `CartController::show()` sin `try/catch`
  (`CartController.php:19-20`), igual que `subtotal()` y que la vista de checkout: lanzar ahí le
  daría al cliente un 500 al abrir el carrito — exactamente el modo de falla que HIG-15 y HIG-19
  vienen a eliminar en este mismo documento.

  **Esto agrega una tercera condición a la regla 92** (comprable = `activo && cantidad ≤ stock` **&&
  en modo `M2`, `m2_por_caja` presente**): es una **enmienda a la regla 92**, con sincronía fechada
  en la Spec 05. El fondo no cambia — la alternativa (lanzar en lectura) rompería la página —, pero
  no puede entrar en silencio. La línea no comprable por esta causa **no exhibe precio ni subtotal**
  (el `?? 0` de `Cart.php:70` no llega a la vista), coherente con HIG-17: informar sin números
  inventados.

**Por qué importa**: el pedido se crea con `subtotal_cents: 0` y total = solo envío, MercadoPago cobra
el envío, y la auditoría registra el cero sin ningún error. No hay sincronía que enmiende esto: el
código y la spec dicen cosas distintas y la diferencia es plata.

### HIG-13. Una sola calculadora m²→cajas

**Estado actual**: la regla 75 y `docs/arquitectura.md` §Catálogo y carrito fijan a `M2Calculator` como *"único
lugar de las reglas de redondeo"*, y la Spec 05 dice que el carrito *"no duplica la lógica"*. El
carrito cumple (`CartController.php:110-130`). La **ficha no**: tiene su propia calculadora escrita a
mano en JavaScript (`resources/views/public/producto.blade.php:172-206`), con aritmética de punto
flotante contra el `bcmath` del servicio.

Comparadas las dos rutas sobre superficies de 0,01 a 50,00 m² y seis valores de `m2_por_caja`:
**209 divergencias en 30.000 combinaciones**, todas de una caja entera. Con el `m2_por_caja = 1,15`
de la factory:

| Superficie + 10 % | Ficha (JS) | Carrito (`M2Calculator`) | **Esperado** |
|---|---|---|---|
| 11,50 m² → 12,65 m² | 12 cajas | 11 cajas | **11** |
| 23,00 m² → 25,30 m² | 23 cajas | 22 cajas | **22** |
| 1,05 m² → 1,155 m² | 2 cajas | 1 caja | **2** |

**La columna "esperado" no es siempre la del carrito.** En las dos primeras filas gana el carrito:
la ficha se equivoca por punto flotante. En la tercera gana la **ficha**, porque el `1` del carrito
es justamente el defecto que **HIG-14** corrige. Por eso **HIG-14 va antes que HIG-13**: si se
unifica primero, se corre el riesgo de hacer converger la fila 3 en 1 y romper HIG-14 sin que nada
avise.

Los dos primeros son error de flotante puro (`12.65 / 1.15 = 11.000000000000002` en JavaScript, y el
`ceil` lo lleva a 12) y caen justo en los números redondos que el cliente tipea.

**Corrección**: la ficha deja de calcular por su cuenta y consume el resultado **ya calculado en el
servidor**, sin ruta HTTP nueva. En concreto: el render inicial pinta el número que calcula
`M2Calculator` en el servidor para los valores precargados, el bloque Alpine
(`resources/views/public/producto.blade.php:172-206`, `parseFloat`/`Math.ceil`) **se elimina** y se
reemplaza por la presentación de ese resultado, y al enviar manda lo que calcula el servidor al
agregar al carrito (igual que hoy: la fuente de verdad al comprar ya es el servidor). Descartada explícitamente la alternativa de consultar al servidor al
tipear: exigiría una ruta pública que ninguna spec autoriza y que no figura en ninguna matriz de
permisos, y `AGENTS.md` §Arquitectura y dominio prohíbe introducirla sin respaldo de spec. El
requisito no negociable es que **el número que muestra la ficha y el que arma el carrito salgan del
mismo cálculo**, con un test que falle si vuelven a divergir. Si al tipear valores arbitrarios la
ficha no puede estimar en vivo sin calcular por su cuenta, entonces **no estima en vivo**: muestra el
cálculo inicial y el número definitivo lo pone el carrito al agregar. Ese cambio de UX —dejar de
prometer estimación interactiva— es parte declarada de esta regla, no un efecto colateral. Si ese
fallback resulta necesario, la regla 75 de la Spec 04 (el cliente ingresa dimensiones o m² y ve m² +
cajas) deja de cumplirse en la ficha y pasa a ser una quinta regla enmendada, con su sincronía
fechada en la Spec 04; si la ficha conserva estimación en vivo con el cálculo unificado, la 75 no se
toca. La tarea de sincronía ya lista Spec 04 para HIG-13, así que ningún caso entra en silencio.

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

**Corrección**: el truncamiento no puede comerse el margen que el cliente pidió explícitamente. El
mecanismo fijado es **subir la precisión intermedia** (no redondear hacia arriba el intermedio):
la superficie con desperdicio se calcula con precisión suficiente para no perder el margen y el
`ceil` final se aplica sobre esa superficie completa; el requisito es que **las cajas cotizadas
cubran siempre la superficie con desperdicio incluido**. Se descarta el redondeo hacia arriba del
valor intermedio porque cambia otros bordes de forma distinta con el mismo test en verde. Esto
**no enmienda ADR-003 punto 4**: ese punto no fija precisión intermedia, solo centavos + `bcmath`,
y así se deja escrito.

**Por qué importa**: el desperdicio existe para que el cliente tenga margen de corte y roturas. En las
superficies chicas hoy va la caja justa, que es precisamente lo contrario de lo que la regla promete.
Apareció de rebote al comparar las dos calculadoras de HIG-13.

### HIG-15. Vaciar el campo "Orden" de una categoría no puede devolver 500

**Estado actual**: `sort_order` es `nullable` en ambos Form Requests
(`StoreCategoryRequest.php:20`, `UpdateCategoryRequest.php:20`), el controlador lo lee con `validated()` y un default —`0` al crear
(`CategoryController.php:45`), `$category->sort_order` al editar (`:70`)—, y las Actions lo reciben
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

**Estado actual**: `routes/web.php:59` hace `->except(['show'])`, que **no excluye `destroy`**. Queda
registrada `DELETE /admin/usuarios/{user}` apuntando a `UserController@destroy`, método que no existe.
La regla 37 dice, textual: *"Un usuario se desactiva, **nunca se borra**"*.

**Corrección**: `->except(['show', 'destroy'])`. Una línea.

**Por qué importa**: hoy cualquier disparo de ese verbo devuelve un 500 en producción en vez de un 403
o un 404. Pero lo más caro es el contrato: la app **anuncia** una operación de borrado de usuarios que
la Spec 01 (regla 37) prohíbe, y `UserPolicy` no tiene `delete`. Si alguien implementa el método viendo
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
ignorar con un 200. Todo GET ignorado que traiga parámetros de notificación de MercadoPago deja
rastro en la auditoría como `webhook.ignored` — un ignorado silencioso sin audit sería una pérdida
invisible. Un GET pelado (bots, health checks, sin parámetros MP) responde 200 sin auditar: no se
ensucia `audit_logs`, inmutable por ADR-004, con tráfico que nunca fue una notificación.

**Decisión del dueño (2026-09-16, convalidada)**: un pago genuino entregado por GET se ignora con
200 y no se procesa — aceptar un pago por GET sería inventar un formato que el proveedor no usa
(entregas por POST verificadas el 2026-09-12). Si MercadoPago alguna vez entrega pagos por GET, se
reabre esta regla.

**Por qué importa**: cada entrega por GET —el IPN viejo lo usa— queda marcada como fallida del lado de
MercadoPago y entra en su ciclo de reintentos, que es precisamente lo que la regla 153 quiere evitar.

### HIG-19. `shipping_address` debe validar contra el tamaño real de la columna

**Estado actual**: `app/Http/Requests/Checkout/StoreCheckoutRequest.php:25` acepta `max:500`; la
migración `2026_09_03_162109_create_orders_table.php:22` declara `string('shipping_address')`, es decir
`varchar(255)`. `CheckoutController::store` solo captura `DomainException`.

**Decisión del dueño (2026-09-15): se sube la columna.** Migración que lleva `shipping_address` a 500.

Es además la opción que **no enmienda nada**: la regla 116 de la Spec 07.3 fija textualmente
`shipping_address nullable string max:500`, así que bajar la validación a 255 habría requerido
enmendar esa regla con su sincronía. Subir la columna deja las dos puntas coherentes con lo que la
spec ya decía.

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

**Corrección**: **no renderizar** el formulario cuando `stock <= 0`. En el carrito el avance se
deshabilita sin esconderse (`cart/show.blade.php:55-59`: `span` deshabilitado si `hasUnpurchasable`);
en la ficha no hay avance parcial posible, así que lo coherente acá es no renderizar el form.

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
`ProductSlugTest.php:94-114` **codifica el comportamiento opuesto al de la spec**, con
`assertSessionHasNoErrors()`.

**Decisión del dueño (2026-09-15): se agrega el `unique`.** La spec no se enmienda; se termina de
implementar lo que ya dice.

La distinción que manda es **quién escribió el slug**, y el diseño ya existe: `uniqueFor()` distingue
los dos casos en `ProductSlugGenerator.php:20`.

- **Slug vacío** (auto-generado desde el nombre): el sufijo `-2`, `-3`… **se queda**. Nadie pidió un
  slug específico, así que resolver la colisión en silencio es lo correcto.
- **Slug escrito por el admin**: `unique` en el Form Request → error de validación, como fija el caso
  borde de la Spec 04.

En edición, el `unique` debe **ignorar al propio producto** (`Rule::unique(...)->ignore($product)`),
o el admin no podría guardar sin cambiarle el slug.

**El docblock de `ProductSlugGenerator.php:14-16` y el test `ProductSlugTest.php:94-114` se
corrigen**: hoy el docblock describe el comportamiento correcto (que no existe) y el test codifica el
opuesto al de la spec.

**Por qué importa**: la unicidad en base está garantizada por el índice único y el generador, así que
no hay riesgo de datos. El riesgo es de URL: el admin escribe `porcelanato-gris` para una campaña,
guarda sin ver ningún error, y la URL publicada es `porcelanato-gris-2`. El folleto, el QR o el aviso
apuntan a otro producto o a un 404.

### HIG-28a. Eliminar el placeholder "Pedidos" duplicado (03.a)

**Estado actual**: `navigation.blade.php:79` muestra un placeholder deshabilitado "Pedidos" mientras
`:22` ya es el link real (Spec 08.c). El único placeholder legítimo es "Ventas WhatsApp" (`:86`),
coherente con que esa venta quedó fuera del MVP.

**Corrección (Decisión del dueño 2026-09-16)**: eliminar el placeholder duplicado en 03.a, junto al
resto de cambios visibles. La sincronía de la regla 44 y los tests del sidebar quedan en HIG-28
(03.b): separar así evita que las dos ramas toquen `navigation.blade.php` y la regla 44 en
paralelo.

### HIG-33. La auditoría de precios cubre `precio_oferta_cents` (PA-2 resuelto)

**Decisión del dueño (2026-09-16): `product.price_changed` pasa a cubrir `precio_oferta_cents`.**

**Estado actual**: la regla 68 audita los cambios de precio para poder responder *"¿a qué precio
estaba antes y desde cuándo se vendió mal?"* (Higiene 02 la arregló con HIG-04).
`UpdateProductAction.php:63-77` captura anteriores y registra únicamente `precio_cents`. Después de
HIG-10, **cambiar `precio_oferta_cents` cambia lo que se cobra y no deja rastro**.

**Corrección**: `UpdateProductAction` captura el anterior de `precio_oferta_cents` (igual que
HIG-04 hizo con precio y stock) y registra `product.price_changed` cuando cambia la lista, la
oferta, o ambas — con claves fijas `previous_precio_cents`, `new_precio_cents`,
`previous_oferta_cents` y `new_oferta_cents` (las de oferta van en `null` cuando no hay oferta de
ese lado). Es **enmienda a la regla 68**, con sincronía
fechada en la Spec 03.

**Por qué importa**: sin esto, una oferta mal cargada que se cobró durante días no se puede
reconstruir: la pregunta que la regla 68 promete contestar queda sin respuesta justo para el campo
que ahora cobra.

## Fase 03.b — Cobertura: lo que hoy se puede borrar en verde

Todo lo de esta fase **ya está bien implementado**. Lo que falta es que algo falle si alguien lo borra.
Ninguna de estas reglas cambia comportamiento observable.

### HIG-23. Índice único en `categories.name` y `categories.slug`

**Estado actual**: las reglas 49 y 50 exigen nombre y slug únicos. Están implementadas y testeadas,
pero **solo en la capa HTTP** (`Rule::unique` en los Form Requests). La tabla `categories` no tiene
índice único: su único índice es `categories_pkey`. Compará con `products.slug`, que sí lo tiene
(`2026_08_06_022248_add_slug_to_products_table.php:30`).

**Corrección**: migración que agregue los dos índices únicos, con **pre-chequeo en PHP que liste
los duplicados antes del `ADD CONSTRAINT`**: si el entorno ya tiene duplicados, la migración falla
de forma legible nombrando los slugs en conflicto, no con el error crudo de Postgres. Test que
seedea duplicados y corre la migración esperando ese fallo legible.

**Por qué está en 03.b y no en 03.a**: no hay ningún defecto observado. `CategoriesSeeder` usa
`updateOrCreate(['slug' => ...])`, no existe importador de categorías, y la unicidad HTTP está
implementada y testeada. Es endurecimiento preventivo, y los principios 5 y 8 piden no tratarlo como
urgente. Es la única regla de esta fase que toca el esquema en vez de agregar un test.

**Por qué importa**: cualquier camino que no pase por el formulario —seeder, comando, un importador
futuro, dos requests concurrentes— puede insertar dos categorías con el mismo slug, y
`/categorias/{categoria:slug}` resolvería siempre a la primera, dejando los productos de la segunda
**inalcanzables en el catálogo público**, sin ningún error visible.

### HIG-24. `UserPolicy` necesita tests propios, no del middleware

**Estado actual**: la Spec 01 ubica la autorización en `app/Policies/UserPolicy.php`, y el controlador
la invoca con `Gate::authorize` en cada método. Pero los seis tests de denegación
(`UserManagementTest.php:16, 25, 34, 151, 248`) entran por HTTP, y el 403 lo devuelve el middleware
`role:admin` de `routes/web.php:57-59` **antes de que el controlador corra**. Se puede vaciar `UserPolicy`
entera —que devuelva `true` a cualquiera— y los seis tests siguen verdes. Verificado: **ningún archivo
de `tests/` menciona `UserPolicy`**.

**Corrección**: tests unitarios directos sobre la Policy, para las cuatro habilidades × los tres roles,
más el caso de usuario sin rol asignado (que HIG-03 resolvió con `try/catch DomainException → false`).

**Por qué importa**: la `OrderPolicy` de la Spec 08 copia su criterio y la referencia en un comentario.
Y en la 08 **ya se sacó `role:admin`** de las rutas de pedidos y despacho para dar acceso parcial al
vendedor (ver el comentario en `routes/web.php:45-47`). El día que eso pase en usuarios, la Policy es
el único control que queda, y hoy la suite no dice nada sobre si funciona.

### HIG-25. Test del límite de intentos de login

**Estado actual**: la regla 38 exige máximo 5 intentos fallidos por minuto por email + IP. Está bien
implementado en `app/Http/Requests/Auth/LoginRequest.php` —`tooManyAttempts(..., 5)` en `:66`, `hit()`
en `:49` con el decay de 60 s por defecto, la clave `email|ip` en `:87` y el `clear()` tras el login
exitoso en `:56`—. Pero `grep -rn "RateLimiter\|throttle\|Lockout" tests/` **no devuelve nada**.

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
correcto **por ausencia** — el callback de `NewPasswordController.php:45-52` hace `forceFill` de
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

**El seeder debe fallar con un mensaje que nombre la variable ausente**, no con una violación de NOT
NULL: `config/admin.php` define `'initial_email' => env('ADMIN_EMAIL')` sin default. Esto no queda a
criterio del implementador — tiene su criterio de aceptación.

**Infraestructura que hay que agregar**: `ADMIN_EMAIL`, `ADMIN_NAME` y `ADMIN_PASSWORD` **no están en
`phpunit.xml`** (las de MercadoPago sí, `phpunit.xml:34-36`) ni en `.github/workflows/ci.yml`. Un
test que corra `DatabaseSeeder` dependería hoy del `.env` del desarrollador y fallaría en CI. Es el
mismo razonamiento que `.ai/rules/tests.md` ya deja escrito para las credenciales externas: fijarlas
en `phpunit.xml` es parte de esta regla, no un detalle.

### HIG-28. Tests del sidebar por rol

**Estado actual**: las reglas 43 y 44 —qué secciones ve cada rol y qué placeholders están
deshabilitados— son dos de las tres reglas centrales de la mitad "panel" de la Spec 02, tienen sus
criterios de aceptación tildados y **no tienen una sola línea de test**.

**Pero la regla 44 quedó desactualizada por tres specs posteriores, y hay que sincronizarla antes de
escribir el test** — si no, se codifica una regla que ya no es cierta:

- La regla lista **Productos, Pedidos y Ventas WhatsApp** como placeholders deshabilitados. Productos
  es link real desde la Spec 03 (`navigation.blade.php:58`) y **Pedidos también**, desde la 08.c
  (`:22`).
- La regla **no menciona Despacho** (`:32`, Spec 08) ni **Tarifas de envío** (`:66`, Spec 06), que
  están en el sidebar.
- El único placeholder legítimo que queda es **Ventas WhatsApp** (`:86`) — coherente con que la venta
  por WhatsApp haya quedado fuera del MVP. El placeholder de Pedidos (`:79`) es el duplicado que
  HIG-28a borra en 03.a.

**Corrección**: sincronía fechada en la Spec 02 que deje la regla 44 al día —placeholders reales y
secciones admin-only completas, incluidas Productos y Tarifas de envío—, y recién después los tests:
que vendedor y depósito no vean Usuarios, Categorías, Productos ni Tarifas de envío, y que el
placeholder que quede siga deshabilitado.

Afirmar el `href` exacto con `route(...)` y no la palabra suelta: buscar `'Pedidos'` en la página
entera da verdadero por cualquier texto del dashboard (`.ai/rules/tests.md`).

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

**Punto cerrado (2026-09-15), sin enmienda**: se había planteado que la primera mitad de la regla 80
—*"los listados respetan el orden de las categorías (`sort_order`)"*— no se cumplía en `/catalogo`,
que ordena solo por nombre. Leída completa, la regla dice *"respetan el orden de las categorías
(`sort_order`) **y, dentro de una categoría**, los productos se ordenan por nombre"*: habla de la
**lista de categorías**, no de ordenar productos por el `sort_order` de la suya. Y eso ya se cumple —
`CatalogController` ordena las categorías por `sort_order` en `:20`, `:40`, `:56` y `:79`, y los
productos por nombre. **Decisión del dueño: queda como está.** No hay enmienda ni trabajo pendiente
acá.

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
proteja. Se incorporan acá para que dejen de estar sueltas. **Son cinco items independientes, cada uno
con su propio criterio**, porque "la deuda queda cubierta" no se puede verificar mutando nada:

1. El payload del audit `order.created` verifica 2 de 4 claves y no el actor `null`.
2. De los tres `CHECK >= 0` de `orders` (migración `:33-35`) solo se testea uno.
3. Nada verifica que `orders.shipping_cp` conserve el cero inicial del `0123`.
4. El `trim` de `prepareForValidation` no se ejercita por HTTP.
5. El `find` + redirect que HIG-09 eligió para la regla 117 no tiene test, así que un "arreglo" a
   `findOrFail` pasaría sin ruido.

`OrderStatus::values()` sin llamadores **no** entra: es código muerto, no cobertura faltante. Borrarlo
o cablearlo es decisión de la Spec 08.

## Matriz de permisos

Esta spec altera **una** matriz de permisos, la de la Spec 08, y elimina una ruta que la Spec 01
prohibía. Las dos van declaradas acá y con sincronía fechada en su spec:

| Acción | Público anónimo | admin | vendedor | depósito |
|---|---|---|---|---|
| `DELETE /admin/usuarios/{user}` | — | **se elimina la ruta** (HIG-16) | — | — |
| `GET /webhook/mercadopago` | ✓ responde 200 e ignora (HIG-18) | — | — | — |

**HIG-18 es un cambio de contrato de una spec cerrada**: la regla 153 declara textualmente
`POST /webhook/mercadopago` y la matriz de la Spec 08 tiene una única fila, `POST`. Aceptar `GET`
requiere enmendar las dos, con sincronía fechada. La corrección técnica está bien fundada —el 200 lo
pide la propia regla 153, y el 405 está verificado contra una entrega real del 2026-09-12— pero no
puede entrar en silencio.

**HIG-16 no amplía nada**: elimina una ruta que nunca debió existir, porque la regla 37 prohíbe
borrar usuarios. No hace falta enmendar la Spec 01; el código pasa a cumplirla.

El resto de las correcciones opera dentro de permisos ya establecidos. HIG-24 **no** cambia quién puede
hacer qué: agrega tests sobre el control que ya existe.

## Puntos abiertos — decisiones del dueño (resueltas 2026-09-16)

Los tres originales (la columna de HIG-19, el `unique` de HIG-22 y el orden de HIG-29) quedaron
resueltos el 2026-09-15 y están anotados en sus reglas. Los dos que destapó `revisor-spec`
quedaron resueltos el 2026-09-16 y están anotados en sus reglas: **PA-1** (oferta en `0` →
`min:1`, ver HIG-10) y **PA-2** (`product.price_changed` cubre `precio_oferta_cents`, ver HIG-33).
No quedan puntos abiertos: el alcance de 03.a está cerrado en 25 reglas.

### PA-1. Una oferta en `0` publica mercadería gratis — resuelto: `min:1`

`precio_oferta_cents` se validaba con `['nullable','integer','min:0']`
(`StoreProductRequest.php:27`, `UpdateProductRequest.php:27`) porque era un dato que solo se
mostraba. Con HIG-10 pasa a ser el precio que se cobra, y `tieneOfertaActiva()` devuelve `true`
con `0 < precio_cents`.

**Decisión del dueño (2026-09-16): mínimo de 1 centavo.** Es el guard más barato, no inventa una
regla de negocio sobre cuánto se puede descontar, y deja la puerta abierta a un umbral si alguna
vez hace falta. Ver HIG-10.

### PA-2. El precio que pasa a cobrarse queda fuera de la auditoría — resuelto: se audita

La regla 68 audita los cambios de precio; `UpdateProductAction.php:63-77` registraba únicamente
`precio_cents`.

**Decisión del dueño (2026-09-16): `product.price_changed` pasa a cubrir `precio_oferta_cents`.**
Es la regla HIG-33 de 03.a, con sincronía en la Spec 03.

## Casos borde

- **HIG-10** — producto con `precio_oferta_cents` **mayor o igual** al de lista: no es oferta activa
  (regla 79), se cobra el precio de lista. Producto con oferta en modo `m2`: la derivación a precio por
  caja se aplica sobre el precio de oferta. Pedido ya creado antes de esta corrección: **no se toca**,
  su `precio_unitario_cents` está congelado por la regla 111 y `audit_logs` es inmutable (ADR-004).
- **HIG-10** — oferta que cambia mientras el producto está en el carrito: el carrito deriva en lectura
  (regla 92), así que el precio mostrado sigue al catálogo hasta que se crea el pedido. Es el
  comportamiento actual y no cambia.
- **HIG-10 / PA-1** — oferta en `0`: se rechaza con 422 en create y en update. Oferta `null`
  (sin oferta): se cobra lista, sin audit de oferta si nada más cambió.
- **HIG-33** — oferta que se quita (pasa a `null`) o que se crea desde `null`: también deja
  `product.price_changed` con su anterior/nuevo. Cambio solo de `precio_cents` con oferta activa:
  audita ambos valores igual, porque lo cobrado pudo cambiar por las dos vías.
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
- **HIG-10** — pedido en `PendingPayment` con preferencia de MercadoPago ya creada **al precio de
  lista**, que el cliente paga después del deploy: el congelamiento de la regla 111 y la verificación
  de monto de la regla 157 lo dejan consistente y el pago se confirma, pero el cliente termina
  pagando lista por algo exhibido en oferta. No se corrige retroactivamente; se acepta y se anota.
- **HIG-12** — producto `M2` sin `m2_por_caja` que ya está en el carrito del cliente: la línea figura
  **no comprable** (regla 92) y bloquea el avance a checkout, sin romper la página.
- **HIG-13/HIG-14** — `m2_por_caja` o el precio cambian entre que la ficha calcula y el formulario se
  envía: manda lo que calcula el servidor al agregar al carrito. Es la misma clase de dato que cambia
  entre dos momentos del flujo que la regla 92 ya resuelve en lectura.
- **HIG-23** — la migración de índices únicos corre sobre un entorno con duplicados preexistentes
  (en staging las migraciones son un step manual, `docs/deployment/staging.md`): el pre-chequeo en
  PHP falla de forma legible, nombrando los slugs en conflicto, no con el error crudo de Postgres.

## Fuera de alcance

**Higiene documental pura, que no necesita spec aprobada.** Va en un PR `docs:` aparte, en paralelo:

- `AGENTS.md` abre con el bloque de Laravel Boost —en inglés, contra la regla de idioma
  ("toda la documentación en español")— que manda correr
  `php artisan` y `npm` directamente en el host, donde `which php` devuelve *not found*. La corrección
  aparece 250 líneas más abajo. Hay que reordenarlo o anotarlo al pie.
- `docs/specs/02-panel-categorias.md:18` dice *"CRUD de categorías **jerárquicas**"*, contra su propia
  regla 45. En el código no quedó nada de la versión jerárquica.
- `docs/arquitectura.md` §Catálogo y carrito afirma que `M2Calculator` es el único lugar del redondeo
  y que no hay acción de compra sin stock. Se corrige **después** de HIG-13 y HIG-21, cuando pase a
  ser cierto.
- El runbook del README: credenciales concretas del admin de desarrollo, `assets` en la lista de
  contenedores del paso 2, y mención de la base `ceramica_test`.

**Reclasificados — no son `docs:` y no van en ese PR**:

- **El placeholder "Pedidos" duplicado** (`navigation.blade.php:79` contra el link real de `:22`)
  **va en 03.a como HIG-28a**: es UI visible, y además colisiona con la sincronía de la regla 44 que
  HIG-28 necesita en 03.b. Hacerlo en dos PRs en paralelo garantizaría conflicto, así que el borrado
  va con los cambios visibles y la sincronía+tests después.
- **`npm install` → `npm ci`** en `docker-compose.yml:81` y `Makefile:58` cambia el comportamiento de
  `make setup` y `make npm-build`: por la convención de commits del repo es **`chore:`**, no `docs:`.
  Va en su propio PR.

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

**Fase 03.a** (HIG-10 a HIG-22 más HIG-28a y HIG-33)

- [x] HIG-10: un producto con oferta activa se cobra al precio de oferta en el carrito, en el pedido y
      en el payload de MercadoPago, en modo `unidad` y en modo `m2`. `precioCajaCents()` **sigue
      derivando del precio de lista** (regla 59 y ADR-003 intactas). La regla 87 queda enmendada con
      el texto de reemplazo de esta spec, sincronía fechada en las Specs 05 y 04, y el glosario gana
      la fila de `precio vigente`. Una oferta en `0` se rechaza con 422 (`min:1`, PA-1).
- [x] HIG-11: los tests que ya inspeccionan el payload **assertan `external_reference`** y fallan si
      se borra. El cuerpo de `paymentUrl()` queda declarado sin cubrir, con su motivo escrito y
      registrado como deuda en §Nota de handoff.
- [x] HIG-12: `PlaceOrderAction` lanza `DomainException` con un producto `M2` sin `m2_por_caja`,
      invocada directamente; y `GET /carrito` con ese mismo producto **responde 200** con la línea
      marcada no comprable y sin precio ni subtotal exhibidos. **Enmienda a la regla 92** con
      sincronía en la Spec 05.
- [x] HIG-13: la ficha y el carrito devuelven **11, 22 y 2** cajas en los tres casos de la tabla, con
      test que falla si vuelven a divergir. El bloque Alpine de la ficha se elimina; sin ruta nueva;
      si la ficha no puede estimar en vivo, no estima en vivo.
- [x] HIG-14: las cajas cotizadas cubren la superficie con el desperdicio incluido por **precisión
      intermedia suficiente** (no redondeo hacia arriba del intermedio); test en el borde
      (1,05 m² + 10 % → 2 cajas). ADR-003 punto 4 intacto.
- [x] HIG-15: `sort_order` vacío crea y edita la categoría sin error; test con `sort_order => ''` en
      create y en update.
- [x] HIG-16: `DELETE /admin/usuarios/{user}` no existe en `route:list`.
- [x] HIG-17: la línea de carrito con stock ≤ 0 no muestra ningún número; test que falla si se quita la
      guarda.
- [x] HIG-18: `GET /webhook/mercadopago` responde 200; el GET con parámetros MP ignorado deja
      `webhook.ignored` en la auditoría y el GET pelado no audita; la firma sigue siendo obligatoria
      para procesar
      un pago. **Sincronía en la Spec 08**: regla 153 y su fila de matriz de permisos. Un pago genuino
      por GET se ignora por diseño (decisión del dueño 2026-09-16).
- [x] HIG-19: `shipping_address` acepta 500 en columna y validación; test en el borde de 500.
- [x] HIG-20: la pantalla de éxito muestra los siete campos de la regla 119.
- [x] HIG-21: con stock ≤ 0 **no se renderiza** el formulario de compra.
- [x] HIG-22: un slug escrito por el admin que colisiona da error de validación; un slug vacío sigue
      recibiendo el sufijo; en edición el producto no colisiona consigo mismo. El docblock de
      `ProductSlugGenerator` y `ProductSlugTest` dicen lo mismo que el código.
- [x] HIG-28a: el placeholder "Pedidos" duplicado (`navigation.blade.php:79`) no se renderiza; el link
      real (`:22`) y "Ventas WhatsApp" (`:86`) quedan como están.
- [x] HIG-33: cambiar solo `precio_oferta_cents` (incluido quitarla a `null`) deja `product.price_changed`
      con las cuatro claves fijas (`previous/new_precio_cents`, `previous/new_oferta_cents`); mutar
      la auditoría de oferta pone un test en rojo.
      **Enmienda a la regla 68** con sincronía en la Spec 03.

**Fase 03.b** (HIG-23 a HIG-32)

- [ ] HIG-23: migración con índices únicos en `categories.name` y `categories.slug`, con pre-chequeo
      en PHP que lista los duplicados; test que seedea duplicados y espera el fallo legible.
- [ ] HIG-24: tests unitarios de `UserPolicy` que **fallan si la Policy devuelve `true` a cualquiera**,
      sin depender del middleware.
- [ ] HIG-25: test del bloqueo al sexto intento y de la limpieza del contador tras un login válido.
- [ ] HIG-26: test del token reutilizado y del usuario desactivado que resetea y sigue sin poder entrar.
- [ ] HIG-27: `ADMIN_*` fijadas en `phpunit.xml`; test del `DatabaseSeeder` (3 roles, 1 admin activo,
      4 categorías), test de idempotencia corriéndolo dos veces, y el seeder falla con un mensaje que
      nombra la variable ausente si falta `ADMIN_EMAIL`.
- [ ] HIG-28: sincronía de la regla 44 en la Spec 02 **antes** de los tests; tests del sidebar por
      rol afirmando el `href` exacto. El borrado del placeholder duplicado ya lo hizo HIG-28a en 03.a.
- [ ] HIG-29: test que asserta las tarjetas realmente renderizadas y el orden por nombre, con nombres
      cuyo orden alfabético difiera del de inserción.
- [ ] HIG-30: test HTTP del borrado de categoría con productos y test del orden del listado del panel,
      en `tests/Feature/Categorias/`.
- [ ] HIG-31: el mínimo de 8 se asserta en el cambio propio y en el reset.
- [ ] HIG-32, uno por item: (1) el audit `order.created` assertea sus 4 claves y el actor `null`;
      (2) los tres `CHECK >= 0` de `orders` tienen test; (3) un test verifica que `shipping_cp`
      conserva el `0123`; (4) el `trim` de `prepareForValidation` se ejercita por HTTP; (5) la regla
      117 tiene test del `find` + redirect, que falla si se cambia a `findOrFail`.

**Ambas fases**

- [ ] Cada regla verificada **mutando la implementación**: borrarla o invertirla tiene que poner algún
      test en rojo. Es el procedimiento con el que este repo se defiende y no es opcional.
- [ ] `revisor-entrega` antes de cada push, pidiéndole explícitamente que mute la implementación.
- [ ] Pint, PHPStan nivel 8, Pest verde, CI verde, Pull Request a `main`.
- [ ] Las casillas de las Specs 01, 02, 04 y `calidad-onboarding` que quedaron verificadas se marcan, y
      las que siguen sin evidencia **no**.

## Tareas técnicas

- [x] Este documento → revisión de `revisor-spec` (hecha el 2026-09-15, correcciones aplicadas;
      segunda vuelta 2026-09-16 con PA-1/PA-2/HIG-28 resueltos, texto de reemplazo de la regla 87 y
      ajustes de segunda revisión, veredicto: aprobable) →
      **aprobación del dueño (2026-09-16)**.
- [x] Puntos abiertos PA-1/PA-2 cerrados el 2026-09-16 y anotados en HIG-10/HIG-33. No quedan puntos
      abiertos.
- [x] Texto de reemplazo de la regla 87 escrito en HIG-10: la sincronía es transcripción.
- [x] Rama `fix/higiene-03a` **desde `main`** — la Higiene 02 se cortó de otra rama y arrastró 10
      commits ajenos; no repetir.
- [x] TDD en este orden: **HIG-14 antes que HIG-13** (si no, la fila 3 de la tabla se unifica en el
      valor equivocado); HIG-10 a HIG-14 primero por ser lo único que afecta plata; después el resto
      de 03.a.
- [ ] Rama `fix/higiene-03b` desde `main` ya con 03.a mergeada.
- [ ] PR `docs:` aparte con la higiene documental de §Fuera de alcance, y un PR `chore:` propio para
      `npm ci`.
- [x] Anotar las sincronías: Specs 05 y 04 más el glosario y `arquitectura.md` (HIG-10, HIG-13 y
      eventual regla 75), Spec 05
      regla 92 (HIG-12), Spec 03 regla 68 (HIG-33), Spec 08
      regla 153 y su matriz (HIG-18), Spec 04 (HIG-13, HIG-21), Spec 07.3 (HIG-20), Spec 02 regla 44
      (HIG-28) y `arquitectura.md` §Panel y categorías (HIG-28a: ya no hay placeholder de Pedidos).
- [x] Actualizar `docs/arquitectura.md` **después** de HIG-13 y HIG-21.
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

**Deuda conocida que 03.a deja abierta.** El cuerpo de `paymentUrl()` (`MercadoPagoGateway.php:91-107`,
guard de `init_point` vacío + `update mp_*`) queda declarado sin cubrir (HIG-11): el SDK (`PreferenceClient`
`final`) vuelve la costura imposible sin reescribir el gateway. La aprobación de esta spec **no** se lee
como "circuito cubierto": ese tramo lo ejercita la verificación manual con túnel.
