# Arquitectura

Última actualización: 2026-09-11 (**Spec 08 fase 08.b**: `POST /webhook/mercadopago` sin sesión y excluido de CSRF, autenticado por firma HMAC con `hash_equals`; filtro por tipo antes de consultar; el estado real se consulta contra la API por el puerto `PaymentStatusQuery` —`PaymentClient` es `final` y no se puede mockear—; verificación de monto contra `total_cents` antes de confirmar; 503 deliberado ante fallo transitorio para que MercadoPago reintente; 373 tests, doce mutaciones verificadas; el adaptador real del puerto se cubre aparte, porque probar el puerto no prueba el adaptador. **Spec 08 fase 08.a**: máquina de estados en `OrderStatus` + `TransitionOrderStatusAction` único camino a `order.status`; `ConfirmPaymentAction` descuenta stock al confirmarse el pago (ADR-005) con lock del pedido y relectura adentro por la idempotencia que exigen los reintentos de MercadoPago; `CancelOrderAction` restituye lo congelado en `order_lines`; stock negativo permitido y auditado como reposición pendiente; 340 tests. **Spec Higiene 02 cerrada**: la auditoría de precio y stock guarda el valor anterior real —`UpdateProductAction` leía `getOriginal()` después del `save()`, cuando `syncOriginal()` ya lo había pisado—; `PlaceOrderAction` valida nombre, teléfono, email y CP como exige la regla 108, sin quitarle nada al Form Request; la revalidación bajo `lockForUpdate` tiene cobertura que falla si se borra, y la promesa de cobertura de concurrencia real quedó retirada de la Spec 07.2 por no ser reproducible con `RefreshDatabase`; guard del reintento MP sobre pedido pagado cubierto; regla 117 enmendada a `find` + redirect. 274 tests. Spec 07.4 verificada de punta a punta contra la API real de MercadoPago: `auto_return` condicionado a back_url pública y costo de envío en `shipments.cost` —dos enmiendas a la regla 123, ver `docs/specs/07-checkout-fase4-mercadopago.md` §Sincronía 2026-09-10—, credenciales externas neutralizadas en `phpunit.xml`, botón "Finalizar compra" en el carrito; 261 tests. Sincronía SDD previa a Spec 08: reglas del importador renumeradas `101–114 → 129–142` para no colisionar con Spec 07; regla 118 ratificada como vigente sobre la regla 100 —el checkout **no** se bloquea sin cotización de envío—; regla 67 activada `Product::tienePedidos()`; testsuite `Unit` incorporado a `phpunit.xml`; 253 tests. Spec 06 fase 2 cerrada: importador administrativo de tarifas por CP — `ShippingRatesCsvParser` + `ImportShippingRatesAction` fila-por-fila en transacción + flujo 422/preview/confirmar/cancelar, ADR-011 aceptada; Fase 0 + Spec 01-06 cerradas + Spec 07.1/07.2/07.3/07.4 cerradas — 07.4 `MercadoPagoGateway`/`mp_*`/retry `POST` mergeada PR #8 — + Staging docs: `ADR-008`/`ADR-009`/`ADR-010`, `docs/deployment/staging.md` `Render Oregon + RoadRunner` + `Neon Oregon PG18 (us-west-2, 18.6)` co-localizado, fixes `cb1002b`/`10b19a5`/`e56e62c`/`73d2945`/`6b477bb`/`bbfd1fd` + `ADR-010` Oregon, `Neon` 14 migraciones + seed `users=1`/`roles=3`/`categories=4`/`products=1` + `shipping_rates` + `orders`/`order_lines`, deploy `https://revestimientos.onrender.com` `~0.3-0.7s` despierto; Spec 06 Envío por CP con `ShippingCalculator` + `ManualShippingCalculator`; Spec 07.1 Fase 1 estructura `orders`/`order_lines` + `OrderStatus` + `PaymentGateway` `name()` solo; Spec 07.2 `PlaceOrderAction` con `lockForUpdate` + `bcmath` + `Cart::clear` post-commit; Spec 07.3 HTTP `CheckoutController` + `StoreCheckoutRequest` + `session order_id`). Se actualiza con cada fase aprobada según el Definition of Done del roadmap.

## Visión general

Laravel 12 (PHP 8.4) con estructura estándar del framework, organizada por
responsabilidades de dominio. La arquitectura se mantiene deliberadamente simple:
se usa lo que Laravel ya ofrece y solo se agrega estructura cuando el dominio lo
demuestra (principio 5 y 8 de PROJECT_PRINCIPLES.md).

Stack: PostgreSQL, Redis, Blade + TailwindCSS + AlpineJS (solo donde aporta),
Vite (servicio `assets` con Node 22 en el compose), Docker Compose (dev, ver ADR-002),
Render Free + RoadRunner + Octane 2 workers para Staging (ver ADR-009 y `docs/deployment/staging.md`),
Spatie Permission para roles y permisos (ADR-007).

## Organización por dominios

Dominios actuales (ADR-001): **Products, Orders, Payments, Users**.

- Los dominios no tienen carpetas propias ni paquetes; se expresan con prefijos y
  convenciones de nombres sobre la estructura Laravel estándar:

```
app/
├── Actions/          → un caso de uso por clase (CreateProductAction, ConfirmPaymentAction…)
├── Contracts/        → puertos / interfaces (PaymentGateway — Fase 1 solo name(), Fase 2 resuelve multi-gateway)
├── DTOs/             → contratos tipados que cruzan capas (ej: CheckoutData)
├── Enums/            → estados y opciones cerradas (OrderStatus, PaymentMethod, Role…)
├── Events/           → eventos de dominio (OrderPaid, ProductPriceChanged…)
├── Listeners/        → reacciones a eventos
├── Http/
│   ├── Controllers/  → extremadamente delgados: reciben request, delegan a Actions
│   ├── Requests/     → validación de entrada (Form Requests)
│   └── Middleware/   → (framework)
├── Jobs/             → trabajo asíncrono (confirmaciones, notificaciones)
├── Models/           → Eloquent
├── Policies/         → autorización por recurso
└── Services/         → servicios de infraestructura (pagos, envíos, imágenes)
```

> De las carpetas del árbol, hoy existen: `Actions/`, `Contracts/` (excepción aprobada: puertos externos `PaymentGateway` Fase 1 `name()` solo, análogo a `ShippingCalculator` en `Services/`), `Enums/`, `Http/`,
> `Models/`, `Policies/`, `Services/` (y `View/`, `Providers/` del framework).
> `DTOs/`, `Events/`, `Listeners/` y `Jobs/` son **previstas**: se crean cuando
> la primera spec las justifique (no crearlas antes).

Reglas:

- Los **controladores no contienen reglas de negocio**.
- Cada **caso de uso es una Action** (una clase, una responsabilidad).
- Los **estados son Enums**; no hay strings sueltos.
- Las **validaciones viven en Form Requests**; la autorización en Policies.
- Los **DTOs** se usan cuando los datos cruzan capas; nunca arrays asociativos
  como contrato interno cuando un DTO hace el código más claro.
- Los **servicios de infraestructura** (pasarela de pagos, calculador de envío,
  almacenamiento) se definen por **interfaz (puerto) + implementación**, para que
  el dominio no dependa de terceros (ADR-006).

### Fronteras futuras (no implementar todavía)

| Dominio | Cuándo se crea | Dónde vive hoy |
|---|---|---|
| Inventory | movimientos, depósitos, reservas, múltiples sucursales | stock como atributo del producto (Products) |
| Customers | historial, direcciones, listas de precios, fidelización | datos sueltos del pedido (Orders) |
| Shipping | tarifas complejas o integración con API de cotización | puerto `ShippingCalculator` + impl interna (Orders) |
| Discounts | reglas de descuento fuera de la línea de producto | reglas dentro del total (Orders/Payments) |

## Casos de uso (Actions)

Una Action recibe entradas explícitas (DTO o primitivos), aplica las reglas de
negocio, persiste (transaccionalmente cuando corresponde), dispara eventos y
devuelve el resultado. Las Actions no conocen HTTP.

Ejemplos implementados: `CreateUserAction`, `UpdateUserAction`,
`SetUserActiveAction` (Spec 01); `CreateCategoryAction`, `UpdateCategoryAction`,
`DeleteCategoryAction` (Spec 02); `CreateProductAction`, `UpdateProductAction`,
`DeleteProductAction` (Spec 03); `PlaceOrderAction` (Spec 07.2, `lockForUpdate` + `bcmath` + snapshot + `Cart::clear` post-commit). Previstos: `ConfirmPaymentAction`, `RegisterWhatsAppSaleAction`, `DispatchOrderAction`,
`CancelOrderAction`.

## Eventos y listeners

Se usan cuando hay una verdadera separación de responsabilidades (ej: `OrderPaid`
→ descontar stock, notificar al cliente). Si una reacción es parte del caso de uso
mismo, se ejecuta dentro de la Action, no vía evento.

## Autenticación y roles (Users)

Implementado en la Spec 01 (Breeze + Spatie, ver ADR-007):

- Flujo de auth con **Laravel Breeze (stack Blade) 2.4.2** (pineado) adaptado a
  `/admin`: rutas en `routes/auth.php` (login, logout, recuperación y reseteo de
  contraseña, confirmación de contraseña), dashboard en `/admin` y perfil propio
  en `/admin/profile`. **Sin registro público, sin verificación de email y sin
  borrado de cuenta** (panel interno; el cliente web es anónimo — Spec 00).
- Roles con **spatie/laravel-permission** (ADR-007): `admin`, `vendedor`,
  `deposito`, uno por usuario. El rol se tipa en código con el enum `UserRole`
  y se persiste como nombre de rol de Spatie; helper `User::role()`.
- Gestión de usuarios solo por admin en `/admin/usuarios` (middleware
  `role:admin` + `UserPolicy`): `UserController` delgado que delega en las
  Actions `CreateUserAction`, `UpdateUserAction` y `SetUserActiveAction`;
  validación en `StoreUserRequest` / `UpdateUserRequest` (email `unique`,
  contraseña mínima 8, rol por enum).
- Baja de usuarios por **desactivación** (`users.is_active`), nunca borrado; el
  login deniega a los desactivados con el **mismo error genérico** que
  credenciales inválidas (no revela el estado). Throttle de login: 5 intentos
  por minuto por `email|ip` (nativo de Breeze, `LoginRequest`).
- El admin inicial nace del seeder (`AdminSeeder`) con credenciales de entorno
  (`config/admin.php` → `ADMIN_NAME`, `ADMIN_EMAIL`, `ADMIN_PASSWORD`);
  `RolesSeeder` crea los 3 roles.
- Auditoría de usuarios/roles: tabla `audit_logs` (model `AuditLog`) + servicio
  `AuditRecorder` (ADR-004). Acciones registradas: `user.created`,
  `user.updated` (con los atributos cambiados), `user.role_changed` (rol
  anterior → nuevo), `user.deactivated`, `user.reactivated`. Cada registro lleva
  actor, sujeto, payload, IP, user-agent y fecha. El admin no puede
  desactivarse a sí mismo (regla de negocio en `SetUserActiveAction`).

## Panel y categorías (Products)

Implementado en la Spec 02 (revisada 2026-08-05: **categorías planas**):

- **Layout del panel**: `layouts/app` con **sidebar lateral** (`layouts/navigation`)
   + área de contenido. El sidebar muestra las secciones según el rol del usuario:
   Dashboard (todos), Usuarios, Categorías, Productos y Tarifas de envío (solo admin); **placeholders
   deshabilitados** de Pedidos y Ventas WhatsApp (recordatorio de las
   specs 07/08).
- **Categorías** en `/admin/categorias` (middleware `role:admin` +
  `CategoryPolicy`): modelo `Category` **sin jerarquía** (`name`, `slug`,
  `sort_order`). La revisión del 2026-08-05 **elimina `parent_id`** (las
  categorías son planas: Porcelanatos, Cerámicas, Pastinas, Adhesivos y las que
  el admin cree). `CategoryController` delgado que delega en las
  Actions `CreateCategoryAction`, `UpdateCategoryAction` y
  `DeleteCategoryAction` (esta última lanza `DomainException` si la categoría
  tiene productos).
- **Validación** en `StoreCategoryRequest` / `UpdateCategoryRequest`: `name` y
  `slug` **únicos en todo el catálogo** (`Rule::unique`). El slug se auto-genera
  del nombre (`Str::slug`) con un sufijo (`-2`, `-3`...) si colisiona
  (`CategorySlugGenerator`); puede editarse en el formulario.
- **Orden manual** (`categories.sort_order`): campo numérico en el formulario,
  prellenado con el máximo + 1; el listado respeta ese orden.
- **Sin auditoría** para categorías (ADR-004 la reserva para precios, stock,
  pagos y roles).
- `CategoriesSeeder` crea la estructura base del negocio de forma idempotente
  (`updateOrCreate` por slug): **Porcelanatos, Cerámicas, Pastinas, Adhesivos**
  (planas). Se ejecuta con `make seed` junto a roles y admin inicial.

## Productos (Products)

Implementado en la **Spec 03**:

- Modelo **`Product`** con columnas tipadas (`category_id`, `name`, `slug`
  único, `marca`, `codigo` único, `descripcion`, `precio_cents`,
  `precio_oferta_cents`, `unidad_venta` enum `ProductSaleUnit`, `m2_por_caja`
  nullable, `stock`, `activo`, `imagenes` jsonb, `specs` jsonb) y FK a
  `categories` con `restrictOnDelete`.
- **Dos modos de venta** (`unidad_venta`): `m2` (precio por m², stock en cajas,
  calculadora m²→cajas) y `unidad` (precio por bolsa/pieza, stock en unidades).
  El `precio_caja` se deriva solo en modo `m2` (ADR-003); `Product::precioCajaCents()`.
- **Slug de producto** (Spec 04): columna `slug` única en todo el catálogo,
  auto-generada del nombre con sufijo `-2`, `-3`… si colisiona
  (`ProductSlugGenerator`, mismo patrón que categorías) y editable por el admin
  en los Form Requests. `Product::getRouteKeyName()` devuelve `slug` para el
  route-model binding público.
- **Scopes de catálogo** en `Product` (Spec 04): `activo()`, `conOferta()`,
  `deCategoria()`, `buscar()` (ILIKE sobre nombre, código y marca), `porMarca()`
  y `specsValor()` (filtro JSONB `specs->>'clave' = valor`).
- **Atributos híbridos**: lo que se calcula o filtra vive en columnas tipadas;
  el resto (medida, color, acabado, rendimiento, peso…) vive en `specs` JSONB con
  claves validadas por familia según la categoría (`ProductSpecs::allowedKeysFor`).
- **Acciones**: `CreateProductAction`, `UpdateProductAction`,
  `DeleteProductAction`. `UpdateProductAction` registra en la auditoría los
  cambios de precio (`product.price_changed`), stock (`product.stock_changed`) y
  la baja por desactivación (`product.deactivated`) reusando `AuditRecorder`
  (ADR-004).
- El check de "producto con pedidos" (regla 67) está **activo desde 2026-09-06**
  (la tabla `orders` existe desde la Spec 07.1): `Product::tienePedidos()` sobre
  la relación `orderLines`. `DeleteProductAction` lanza `DomainException` si el
  producto tiene pedidos (se desactiva en su lugar) y `UpdateProductAction` la
  lanza si se intenta cambiar `unidad_venta` con pedidos históricos — cambiarla
  haría que la `cantidad` congelada en `order_lines` se leyera en otra unidad.
  `ProductController` las traduce a errores de formulario, nunca a un 500.

## Catálogo público (Products)

Implementado en la **Spec 04** (cliente web anónimo, Spec 00 regla 27):

- **Layout público** `layouts/site` (Blade + Tailwind 4 + Alpine) con header de
  navegación por categorías, buscador y enlace a ofertas; no usa el layout del
  panel. Vistas: `public/home` (categorías en orden `sort_order` + destacados con
  oferta), `public/catalogo` (grilla con filtros combinables y paginación de 12)
  y `public/producto` (ficha con calculadora m²→cajas). Componente reusable
  `product-card`.
- **Rutas públicas** en `routes/web.php` (sin middleware de auth): `/`,
  `/catalogo`, `/categorias/{categoria:slug}`, `/ofertas`,
  `/productos/{producto:slug}` (nombres `catalogo.*`). La ruta `GET /` reemplaza
  la vista `welcome` del skeleton.
- **`CatalogController`** delgado (home, catálogo, categoría, ofertas, ficha)
  que delega las consultas en los scopes de `Product` y `with('category')` para
  evitar N+1. Solo publica productos **activos**; la ficha de un inactivo
  responde 404.
- **Filtros combinables** (regla 76): categoría, solo ofertas, marca y specs por
  familia (`ProductSpecs::allowedKeysFor`). Los filtros de specs se ofrecen solo
  cuando hay categoría seleccionada y con los valores presentes en los productos
  publicados (regla 77); se resuelven con operadores JSONB de PostgreSQL
  (`specs->>'clave'`). Búsqueda ILIKE sobre nombre, código y marca (regla 78).
- **`M2Calculator`** (Spec 04, ADR-003): servicio puro con bcmath
  (`m2DesdeDimensiones`, `aplicarDesperdicio`, `cajasNecesarias`); único lugar de
  las reglas de redondeo (reglas 9–12), lo reutiliza el carrito (Spec 05). La
  calculadora de la ficha es un widget Alpine de estimación (no agrega al
  carrito).
- **Stock visible** (regla 73–74): "Quedan N cajas/unidades"; sin stock se
   muestra el badge "Sin stock" y no hay acción de compra (el carrito llega con
   las Specs 05/06).

## Carrito (Orders — Spec 05)

Implementado en la **Spec 05** (cliente anónimo, sin reserva de stock, `subtotal` sí / `total` no):

- **Carrito en sesión** (regla 81): `session('cart')` como `array<product_id, cantidad>` (YAGNI: sin tabla `carts`, `docs/arquitectura.md:58-65`). No reserva stock; no persiste en DB.
- **Líneas**: cada línea referencia `Product` + `cantidad` entera (cajas si `M2`, unidades si `Unidad`, regla 82). Derivación m²→cajas con `M2Calculator::cajasNecesarias` y `aplicarDesperdicio` (10 % antes de `ceil`, regla 84); la cantidad almacenada es siempre entero de cajas. Subtotal línea reutiliza semántica `Spec 03/ADR-003`: `precio_cents` directo en `Unidad`, `precio_caja_cents = round(precio_cents × m2_por_caja)` en `M2` (regla 87).
- **Validaciones** (reglas 85–86): `cantidad ≤ stock` en la unidad de `unidad_venta` y `activo==true`; agregar acumula (regla 89) y actualizar reemplaza (regla 90); cantidad 0 elimina. Exceder stock (ej. 3→4) se rechaza con error.
- **Condición derivada al leer** (regla 92): línea comprable si `activo && cantidad ≤ stock`; si no, figura como no comprable (sin estado persistente `no_disponible`) y bloquea avance a checkout. `Cart::lines()` enriquece con `precioUnitario`, `subtotal`, `comprable`; `subtotal()` suma solo líneas comprables; `hasUnpurchasable()` indica bloqueo.
- **`Cart` + `CartController` delgado** (`show`, `add`, `update`, `remove`, `clear`) + `AddToCartRequest`/`UpdateCartRequest`. Rutas públicas `GET /carrito`, `POST /carrito/agregar`, `PATCH /carrito/{producto:slug}`, `DELETE /carrito/{producto:slug}`, `DELETE /carrito`.
- **Vistas**: `cart/show` (layout `layouts/site` con `categorias` prop) + componente `cart-line` (precio, cantidad, subtotal, badge no comprable). Form de agregar en `public/producto` (superficie + desperdicio para `M2`, cantidad para `Unidad`). Sin `ShippingCalculator`/`DiscountCalculator`/`precio_congelado_cents` en esta spec; evolución `06: total=subtotal+shipping`, `09: total=subtotal+shipping-discount` solo documentada; reserva diferida vinculada a `ADR-005`.

## Pedidos (Orders — Spec 07.1 Fase 1 Estructura)

Estructura cerrada en `docs/specs/07-checkout.md:1`. Fase 1 **solo persistencia y contratos**, sin lógica.

- **Tablas `orders`/`order_lines`** (`2026_09_03_162109`/`162110`): `orders` (`status` string enum `OrderStatus`, `customer_name/email/phone`, `shipping_cp` varchar 4, `shipping_address` nullable, `shipping_cost_cents`/`subtotal_cents`/`total_cents` bigint `CHECK >=0`, `payment_method` nullable string, índices `status`/`customer_email`); `order_lines` (`order_id` FK `cascadeOnDelete`, `product_id` FK `restrictOnDelete` — trazabilidad + snapshot histórico, `product_name/codigo/marca/unidad_venta/m2_por_caja` decimal(8,2) nullable, `cantidad` unsignedInteger `CHECK >0` entero positivo `M2→cajas`/`Unidad→unidades`, `precio_unitario_cents`/`subtotal_cents` bigint `CHECK >=0`, `specs` jsonb).
- **Congelado**: `order_lines` snapshot desnormalizado (`product_name/codigo/marca/specs/m2_por_caja`) independiente de `products`; `shipping_cost_cents` snapshot de `ShippingQuote` al crear pedido, no se recalcula; DB solo garantiza `>=0`, Fase 2 garantiza `subtotal_linea = cantidad×precio_unitario` y `total = subtotal+shipping` con `bcmath`.
- **Estados** `OrderStatus` (`PendingPayment='pending_payment'`, `Paid`, `Shipped`, `Delivered`, `Cancelled`) valores inglés + `label()` español; grafo `pending_payment→paid→shipped→delivered` con `cancelled` desde `pending_payment/paid/shipped` — Fase 1 solo enum como contrato, sin state machine.
- **Modelos** `Order` (`status` cast `OrderStatus`, casts centavos `integer`, `lines(): HasMany`, scopes `pendingPayment/paid/byEmail/byStatus`) y `OrderLine` (`order()/product(): BelongsTo`, `m2_por_caja string` nunca float, `specs array`).
- **Factories** `OrderFactory`/`OrderLineFactory` con estados `paid/shipped/cancelled` y `m2Mode/unitMode`.

## Pedidos — Checkout Fase 2 (PlaceOrderAction — Spec 07.2)

Implementado en `docs/specs/07-checkout-fase2.md:1` (cerrada, sin rutas).

- **`PlaceOrderAction`** (`app/Actions/PlaceOrderAction.php`): inyecta `Cart` + `ShippingCalculator` + `AuditRecorder`; `execute(customer_name/email/phone, shipping_cp/address, payment_method): Order` valida **sus propias entradas** (nombre y teléfono no vacíos tras `trim`, email por `FILTER_VALIDATE_EMAIL`, CP `^[0-9]{4}$`, medio de pago en el enum → `DomainException`; red de seguridad del dominio para llamadores que no pasan por `StoreCheckoutRequest`, Spec Higiene 02 HIG-06), `cart no vacío` + prevalidación `hasUnpurchasable()` (regla 108), luego `DB::transaction` + `Product::lockForUpdate()` valida definitivamente `activo` y `cantidad ≤ stock` (regla 109, `M2→cajas`/`Unidad→unidades`); calcula `precio_unitario` vía `Product::precioCajaCents()` / `M2Calculator` para `M2` y `precio_cents` para `Unidad` con `bcmul/bcadd` (regla 110, nunca `float`, `m2_por_caja string`); `ShippingQuote` `quote(trim cp)` → `shipping_cost = disponible? costo:0` snapshot (no recalcula); crea `Order` (`PendingPayment`) + `OrderLines` snapshot (`product_name/codigo/marca/unidad_venta/m2_por_caja/specs`) + `audit order.created` (ADR-004, `actor null` anónimo); **no descuenta stock** (ADR-005 → Spec 08). `Cart::clear()` **solo tras `COMMIT`**, fuera de transacción; `rollback` mantiene carrito (regla 112).
- **Tests**: `tests/Feature/Orders/PlaceOrderTest.php` (25 tests, incluidas las validaciones de entrada y **dos que llegan efectivamente a la revalidación bajo lock** —stock agotado y producto desactivado después de la prevalidación, con un doble de `Cart` cuya prevalidación quedó vieja— que fallan si se borra la revalidación; la concurrencia real no es reproducible con `RefreshDatabase` y la Spec 07.2 quedó enmendada: vacío, `hasUnpurchasable`, `activo/stock` bajo lock, `M2/Unidad`, `shipping disp/no-disp`, snapshot independencia, `audit`, `clear` vs `rollback`, concurrencia PG `lockForUpdate`; cantidad surge de behaviours).
- **Sin anticipación**: no controladores/rutas, no `ConfirmPaymentAction`, no `DTOs/Events/Listeners/Jobs`, no `PaymentGateway` `confirm/createPreference` (Fase 2 solo `name()`).

## Checkout — HTTP Fase 3 (Spec 07.3)

Implementado en `docs/specs/07-checkout-fase3-http.md:1` (cerrada, sin `ConfirmPaymentAction`).

- **Rutas públicas anónimas** (sin `auth`, sin `Policy`): `GET /checkout` (`checkout.show`), `POST /checkout` (`checkout.store`), `GET /checkout/exito` (`checkout.success`) en `routes/web.php` (sin `Route::resource`, coherente con carrito).
- **`StoreCheckoutRequest`** (`app/Http/Requests/Checkout/StoreCheckoutRequest.php`): `customer_name/email/phone` required, `shipping_cp` `regex:/^[0-9]{4}$/` con `trim`, `shipping_address` nullable, `payment_method` `Rule::in(['transferencia','mercadopago'])` mensajes ES; no valida `cart` (lo hace `PlaceOrderAction` regla 108/109).
- **`CheckoutController` delgado** (`app/Http/Controllers/CheckoutController.php`): `show` (si `isEmpty` o `hasUnpurchasable` → redirect `carrito.show`; sino `view('checkout.show', lines/subtotal/categorias)`); `store` (`validated()` → `PlaceOrderAction->execute(...)` → `session(['order_id' => $order->id])` → redirect `checkout.success`; `catch DomainException → back withErrors` carrito intacto); `success` (lee `session('order_id')` sin `{order}` en URL, si null → redirect `carrito.show`; `Order::with('lines')->findOrFail` snapshot, `view('checkout.success')`).
- **MercadoPago (Spec 07.4)**: `store` con `payment_method mercadopago` → `try MercadoPagoGateway->paymentUrl($order) → redirect()->away($url)`, `catch Throwable → Log::error + redirect success with payment_error` (`Order PendingPayment`, `Cart` ya vacío post-commit); `POST /checkout/mercadopago/reintentar` (`checkout.mercadopago.retry`, sin `auth`, `session order_id`, valida `mercadopago` + `PendingPayment` sino `403`) crea nueva `Preference` y sobrescribe `mp_*`; `GET /checkout/exito` solo lectura (con `mp_init_point` → botón continuar a MP; con `payment_error` sin `init_point` → form `POST` reintentar).
- **Vistas** `checkout/show.blade.php` (form `@csrf` + `old()` + `number_format` centavos) y `success.blade.php` (snapshot `Order`/`lines`, `status->label()`, `payment_method` instrucciones) con ` <x-layouts.site :categorias="$categorias">` (`.ai/rules/views.md`).
- **Shipping**: `store` reutiliza `PlaceOrderAction` (regla 110) `quote !disponible → shipping_cost=0` (permitido, sin bloqueo UI).

## Pagos (Payments — Spec 07.1/07.2/07.3/07.4)

Fase 1 solo puerto minimalista; Fase 2 lo consume; Fase 3 ofrece ambas opciones en UI; Fase 4 implementa MercadoPago real.

- **Contrato** `App\Contracts\PaymentGateway` con `name(): string` + `paymentUrl(Order): ?string` (`null` = sin URL de pago, transferencia; fallo MP = excepción, nunca `null`). `App\Services\ManualTransferGateway` devuelve `transferencia`/`null`; `App\Services\MercadoPagoGateway` (`mercadopago`, `mercadopago/dx-php` pineado, `config/services.php` + `MERCADOPAGO_*` por env) crea `Preference` (`items` con `quantity` entera y `unit_price` `bcdiv/2` `ARS`, `external_reference = order->id`, `back_urls → checkout.success`, `auto_return approved` **solo si la back_url es alcanzable desde internet** —localhost, sufijos `.local`/`.test` y rangos IP privados lo omiten, porque MP responde `400 invalid_auto_return`—, y `shipments.cost` con el costo de envío cuando `shipping_cost_cents > 0`, para que el monto cobrado sea el total del pedido y no el subtotal) y persiste `orders.mp_preference_id/mp_init_point` (migración `add_mp_fields_to_orders`). `ShippingCalculator` permanece en `Services/` por `ADR-006`; `Contracts/` es excepción aprobada para puertos externos. Webhook/confirmación/stock pertenecen a Spec 08 (YAGNI).
- MercadoPago/transferencia completos: `ConfirmPaymentAction` y multi-gateway en Spec 08.

## Pedidos — Gestión (Orders — Spec 08 fases 08.a y 08.b)

Implementado en `docs/specs/08-gestion-pedidos.md:1` (08.a dominio, 08.b webhook; sin pantallas hasta 08.c). Cierra el agujero de que el stock no bajaba nunca y el de que nadie escuchaba a MercadoPago.

- **Máquina de estados en el enum** (regla 166): `OrderStatus::transicionesPermitidas()` y `puedeTransicionarA()` fijan el grafo `pending_payment → paid → shipped → delivered`, con `cancelled` desde los tres primeros; `delivered` y `cancelled` son finales y ningún estado transiciona a sí mismo. Vive en el enum porque es conocimiento del estado (`AGENTS.md`: los estados son Enums).
- **`TransitionOrderStatusAction`** (`app/Actions/`): **único** camino para escribir `order.status`. Valida contra la máquina, persiste y audita `order.status_changed` con `previous`/`new`. **No toca stock** a propósito. Una transición inválida lanza `DomainException` y no deja rastro en la auditoría.
- **`ConfirmPaymentAction`** (`app/Actions/`, reglas 143-145 y 150-152): único camino a `paid`. Valida el origen (`mercadopago`|`manual`), abre `DB::transaction`, **bloquea el pedido con `lockForUpdate` y relee su estado adentro** —sin eso dos notificaciones concurrentes del mismo pago descontarían stock dos veces, y MercadoPago reintenta por diseño—, descuenta stock, transiciona y audita `order.paid` con el origen en el **payload** (el webhook corre sin sesión, así que el actor siempre es `null`). Estado y stock en la misma transacción: o pasan los dos, o no pasa ninguno.
- **Descuento de stock** (reglas 143-145, 149): usa las cantidades **congeladas** en `order_lines`, nunca recalcula desde el producto; agrupa por `product_id` y bloquea las filas **ordenadas por id**, para no deadlockear contra una cancelación concurrente que toque los mismos productos. Un pago cobrado sin stock suficiente **no se rechaza**: pasa a `paid`, el stock queda negativo y se audita `order.stock_negative` con producto, cantidad y stock resultante (reposición pendiente). `products.stock` es `integer` sin `CHECK >= 0` en PostgreSQL, así que no hizo falta migración.
- **Idempotencia** (regla 152): confirmar un pedido ya `paid`, `shipped` o `delivered` es un no-op silencioso, sin segundo descuento ni segunda auditoría. Un pago sobre un pedido `cancelled` **no** lo mueve: audita `order.paid_after_cancel` y el pedido queda trabado a propósito, sin override (decisión del dueño: el pago debe ser completo).
- **`CancelOrderAction`** (`app/Actions/`, reglas 147-149 y 165): bloquea el pedido primero para serializar contra una confirmación concurrente, transiciona a `cancelled` y restituye exactamente la cantidad congelada, con el mismo bloqueo ordenado. Un pedido `pending_payment` no toca stock (nunca se descontó); cancelar dos veces lo rechaza la máquina de estados, así que no restituye dos veces. Audita `order.stock_restored` con las líneas devueltas. La devolución del dinero es un trámite **fuera del sistema**.
- **Webhook** (`POST /webhook/mercadopago`, `webhook.mercadopago`, reglas 153-158, fase 08.b): sin `auth`, sin sesión y **excluido de CSRF** en `bootstrap/app.php` —primera y única excepción del proyecto—, porque lo autentica la firma. `MercadoPagoWebhookController` es delgado: valida firma, filtra por tipo y delega.
- **Firma** (`MercadoPagoSignatureVerifier`, regla 154): HMAC-SHA256 sobre el manifiesto `id:<data.id>;request-id:<x-request-id>;ts:<ts>;` con el secreto de `config('services.mercadopago.webhook_secret')` —nunca `env()` directo—, comparado con `hash_equals`. Firma ausente, inválida o secreto sin configurar → **401** + `webhook.signature_invalid`, sin consultar la API.
- **Filtro por tipo antes de consultar** (regla 155): solo `payment` se procesa (`type` moderno o `topic` del IPN viejo); cualquier otro → 200 + `webhook.ignored`. Sin el filtro, un `merchant_order` con firma válida haría fallar la consulta, porque su id no es el de un pago.
- **`PaymentStatusQuery`** (`app/Contracts/`, regla 155 y ADR-006): puerto propio para consultar el estado real del pago, implementado por `MercadoPagoGateway::findPayment()`. Va aparte de `PaymentGateway` porque la transferencia no tiene pago remoto que consultar. `PaymentClient` del SDK es `final` —misma trampa que `PreferenceClient`—, así que la costura de los tests es el puerto bindeado en el contenedor, no el cliente. El monto vuelve en **centavos** (`bcmul`, ADR-003).
- **`ProcessMercadoPagoNotificationAction`** (`app/Actions/`, reglas 155-158): del webhook toma solo el ID y decide con lo que responde la API, nunca con lo que llegó por HTTP. Pago desconocido → `webhook.payment_not_found`; estado distinto de `approved` → sin cambios (el cliente puede reintentar con la regla 126); `external_reference` inexistente → `webhook.order_not_found`; monto distinto de `total_cents` → **no confirma** y audita `order.payment_amount_mismatch` con lo cobrado y lo esperado (regla 157, nacida del defecto real en que MP cobraba el subtotal). Solo el camino completo invoca `ConfirmPaymentAction` con origen `mercadopago`, que ya trae la idempotencia de la 08.a.
- **Códigos de respuesta** (regla 153): 200 cuando se procesó o cuando no había nada que hacer, para que MercadoPago no reintente en vano; **503 deliberado** cuando la consulta a la API falla por causa transitoria, porque responder 200 ahí perdería el pago para siempre con el pedido en `PendingPayment`.
- **Sin anticipación**: sin panel ni vista depósito (08.c), sin ventas por WhatsApp (Spec 08.2), sin `Events`/`Listeners`/`Jobs`, sin movimientos de stock como entidad (ADR-005 → Fase 3).

## Envíos (Shipping — Spec 06)

Implementado en la **Spec 06** (tarifa única por CP exacto, `total = subtotal + shipping` cuando disponible):

- **Tabla `shipping_rates`** (`id`, `cp` varchar 4, `costo_cents` bigint con `CHECK >=0`, `activo` bool, índice `cp` + índice único parcial `UNIQUE(cp) WHERE activo=true` para garantizar una tarifa activa por CP). `cp` como string conserva ceros (`0123`). Modelo `ShippingRate` con scope `activo()`.
- **Puerto `ShippingCalculator` + `ManualShippingCalculator`** (ADR-006): `quote(string $cp): ShippingQuote` consulta `shipping_rates` por `cp` exacto `trim` y `activo`; `disponible=true` con `costoCents` o `disponible=false` sin excepción si no hay tarifa. Binding en `AppServiceProvider`. Validación CP `^[0-9]{4}$` (422 si vacío/inválido); `costo 0` = envío gratis.
- **Administración** en `/admin/tarifas-envio` (solo `role:admin` + `ShippingRatePolicy`): CRUD con Form Requests o equivalente, validación CP 4 dígitos + unicidad parcial tarifa activa + `costo_cents` entero ≥0. Sidebar `Tarifas de envío` habilitada.
- **Integración en carrito** (`CartController::show` + `cart/show`): campo CP → `ShippingCalculator::quote()` → muestra `Envío: $` o `Envío no disponible`, y `Total: $` cuando `disponible`. `subtotal` no cambia por cotizar; `total = subtotal + shipping` solo si `disponible`. Si no hay cotización, el carrito informa la no disponibilidad y **no bloquea** el avance: el pedido se crea con `shipping_cost_cents = 0` (regla 118, que reemplaza el bloqueo original de la regla 100).
- **Sin anticipación**: sin zonas/rangos/precedencias/peso/distancia/API externa; evolución solo como reemplazo del binding (sin diseñar contrato API en esta spec).

## Envíos — Importador de tarifas (Shipping — Spec 06 fase 2)

Implementado en `docs/specs/06-envio-fase2-importador.md:1` (cerrada, reglas 129–142, ADR-011). Laravel **no calcula** tarifas: solo aplica un snapshot externo.

- **`ShippingRatesCsvParser`** (`app/Services/`): servicio puro que parsea y valida el CSV completo sin persistir. Cabecera exacta `codigo_postal,precio_envio`, BOM y CRLF/LF tolerados, `str_getcsv` con `$escape` explícito (obligatorio en PHP 8.4). Por fila devuelve el nº de línea en el error: CP `^[0-9]{4}$` tras `trim` (string, conserva ceros), `precio_envio` entero `>= 0` y `<= 92233720368547758` (regla 130, anti-overflow de `bigint`), duplicado dentro del archivo y fila vacía. Conversión `precio × 100 = costo_cents` con aritmética entera nativa, sin `float` ni BCMath (regla 129).
- **`ImportShippingRatesAction`** (`app/Actions/`): aplica el snapshot **fila-por-fila** dentro de una única `DB::transaction()` (ADR-011, sin `upsert()`): sin tarifa activa → `create` (reglas 131/134, nunca reactiva una histórica); activa con distinto costo → `update` de la misma fila (regla 133); activa con igual costo → no-op sin tocar `updated_at` (regla 132); activa ausente del snapshot → `activo = false` (regla 135). **Cero deletes** (regla 136). Devuelve `created/updated/unchanged/deactivated`.
- **`ShippingRateImportController`** delgado con el flujo de 4 pasos de la spec: `GET .../importar` (form + barrido de temporales vencidos), `POST .../importar` (valida todo; error → **422** re-renderizando el formulario con los errores por línea, nada persiste; éxito → temporal en `storage/app/private/tmp` + manifiesto en caché con `user_id`, `path`, `hash`, conteos y expiración de 30 min, y **redirect** al preview), `GET .../importar/preview?token=` (resumen; token ajeno, inexistente o vencido → rechazo sin mutación) y `POST .../importar/confirmar` (re-parsea y revalida desde el temporal antes de aplicar). El patrón PRG evita que refrescar el preview re-suba el archivo.
- **422, no redirect**: el importador responde `422` ante cualquier error de validación —de contenido y de archivo, este último vía `failedValidation()` en `ImportShippingRatesRequest`— por exigencia de la spec. Es una **excepción deliberada** a la convención del resto del panel (redirect 302 con `withErrors`), que no se modificó.
- **Limpieza del temporal** (ADR-011 punto 5): descarte al confirmar, ante manipulación detectada por `hash`, en `POST .../importar/cancelar`, y barrido de vencidos al abrir el importador. Sin scheduler, porque ningún entorno del proyecto lo ejecuta.
- **Autorización**: ability `import` en `ShippingRatePolicy` (solo admin) con `Gate::authorize`; rutas bajo `auth` + `role:admin` y declaradas **antes** del `Route::resource` para no colisionar con `{tarifa_envio}`.
- **Sin anticipación**: sin tabla de historial de importaciones (regla 142, ADR-011), sin cálculo de tarifas, sin tocar `ShippingCalculator`/`ManualShippingCalculator`.

## Stock (Inventory mínimo)

- El stock (cajas) es atributo del producto; desciende al confirmarse el pago
  (ADR-005); se restituye al cancelar pedidos pagados/despachados.
- El descuento de stock ocurre dentro de transacciones; se protege contra ventas
  concurrentes con bloqueo optimista/locking de fila (ver ADR-005).

## Dinero y unidades

- Montos en **centavos (BIGINT)**; m² como decimal con precisión controlada;
  cálculos con bcmath (ADR-003). `Order`/`OrderLine` congelan `subtotal_cents`/`total_cents`/`shipping_cost_cents` como snapshot, no recalculan.
- **Dos modos de venta** (`unidad_venta`): precio/stock en cajas (modo `m2`) o en
  unidades (modo `unidad`); `precio_caja` se deriva solo en modo `m2` (Spec 03). `OrderLine.cantidad` entera positiva (`M2→cajas`, `Unidad→unidades`), `m2_por_caja` `decimal(8,2) → string` nunca float.

## Observabilidad (estructura reservada — no implementar en MVP)

Para no rediseñar después, se reservan estos espacios (ADR-004):

- **Logs**: canal `stack` con `daily`; logging estructurado con contexto (order_id,
  user_id, product_id). Nada sensible (sin datos de tarjeta).
- **Eventos de dominio**: ya forman parte del diseño (Events/).
- **Auditoría**: tabla `audit_logs` para acciones críticas (cambios de precio,
  ajustes de stock, confirmaciones de pago, roles) con `actor`, `action`,
  `subject_type/id`, `payload`, `created_at`. Implementada en la **Spec 01** para
  usuarios y roles (crear/editar/desactivar/reactivar usuario y cambios de rol);
  se extiende a precios, stock y pagos en sus specs.
- **Métricas**: placeholder en la fase de despliegue (health check `/up`,
  latencia, colas). Sin dashboards en el MVP.

## Testing

- Pest: Feature Tests por caso de uso (el estándar), Unit Tests para lógica
  compleja (cálculo de cajas, descuentos, DTOs). La suite `tests/Unit` existe
  desde la **Spec 04** (`M2CalculatorTest`).
- **Ambos testsuites deben estar declarados en `phpunit.xml`** (`Unit` y
  `Feature`). Hasta el 2026-09-06 solo estaba declarado `Feature`, de modo que
  `tests/Unit` no se ejecutaba ni localmente ni en CI pese a existir en el
  repo. `php artisan test` (el mismo comando que corre `ci.yml`) ejecuta todos
  los testsuites declarados: si se agrega un directorio de tests nuevo, hay que
  declararlo ahí o queda invisible sin que nada falle.
- **Las credenciales de servicios externos se neutralizan en `phpunit.xml`**
  (`MERCADOPAGO_ACCESS_TOKEN`/`MERCADOPAGO_PUBLIC_KEY` vacías). Sin eso la suite
  hereda el `.env` del desarrollador: cuando se configuró un token real de
  MercadoPago por primera vez (2026-09-10), los tests empezaron a crear
  preferencias verdaderas contra la API. Todo servicio externo que se agregue
  debe neutralizarse ahí, y los tests deben bindear un gateway explícito en vez
  de depender de que el ambiente esté sin configurar.
- TDD obligatorio (principio 3).
- Base de datos de tests: PostgreSQL (`ceramica_test`), mismo motor que producción.

## Calidad

- **Laravel Pint** (`pint --test`) y **PHPStan nivel 8** (Larastan) en CI y en el
  Makefile (`make lint`, `make stan`).
- GitHub Actions: lint → stan → tests (workflow en `.github/workflows/ci.yml`).

## Despliegue

- **Desarrollo:** `docker-compose.yml` con nginx + php-fpm + `postgres:17-alpine` + redis + mailpit (solo dev, `17` se mantiene; bump a `18` se evalúa aparte), ver ADR-002.
- **Staging:** `Render Free (Oregon)` + `Neon PG18 (Oregon, us-west-2, 18.6)` + `RoadRunner 2 workers` via `Octane` (`docker/koyeb/Dockerfile`, histórico Koyeb) — `php artisan octane:start --server=roadrunner --workers=2` multi-proceso, `PORT` inyectado, `health /up`, `SESSION/CACHE database`, sin `migrate --force` en `CMD`. Ver `docs/deployment/staging.md` y `ADR-009`/`ADR-010` (fixes: `Telescope` condicional `cb1002b`, `pcntl`/`sockets`/`linux-headers` `10b19a5`/`e56e62c`, `$PORT` `73d2945`, `FrankenPHP EPERM` → `RoadRunner` `6b477bb`, `Mixed Content HTTPS` → `trustProxies at:'*'` `bbfd1fd` en `bootstrap/app.php:17`, `latencia 5s` → `Neon Oregon PG18` `ADR-010`). Estado 2026-09-01: deploy en `https://revestimientos.onrender.com` operativo `~0.3-0.7s` despierto con estilos y HTTPS correctos, `Neon Oregon 18.6` `11` migraciones + seed `users=1`/`roles=3`/`categories=4`/`products=1` (ver `staging.md §15.4`), rollback `sa-east-1` 48h.
- **Producción futura:** VPS único (ADR-002).
- Configuración del runtime PHP en archivos separados dentro de `docker/php/`
  (`php.ini`, `opcache.ini`, `www.conf`); healthchecks declarados por servicio
  en el compose.
- **Proxy HTTPS:** Render termina TLS y reenvía `X-Forwarded-Proto:https`; `bootstrap/app.php:17` `$middleware->trustProxies(at:'*')` hace que `Request::getScheme()` y `UrlGenerator`/`@vite` generen `https://` (local sin header sigue `http`).
- **Postgres:** Staging en PG18, local en PG17 por ahora; `channel_binding=require` solo en DSN de Neon Oregon.

## Estructura de documentación

```
PROJECT_PRINCIPLES.md   → constitución (10 reglas + convención de commits)
README.md               → guía rápida: arranque, comandos, docs
docs/vision.md          → objetivos, alcance, éxito, riesgos
docs/ubiquitous-language.md → vocabulario único
docs/arquitectura.md    → este documento
docs/roadmap.md         → fases con Definition of Done
docs/adr/ADR-0XX-*.md   → decisiones arquitectónicas
docs/specs/*.md         → specs de funcionalidad (aprobadas antes de codear)
```
