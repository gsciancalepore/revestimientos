# Arquitectura

Última actualización: 2026-08-05 (corresponde a la Fase 0; se actualiza con cada
fase aprobada según el Definition of Done del roadmap).

## Visión general

Laravel 12 (PHP 8.4) con estructura estándar del framework, organizada por
responsabilidades de dominio. La arquitectura se mantiene deliberadamente simple:
se usa lo que Laravel ya ofrece y solo se agrega estructura cuando el dominio lo
demuestra (principio 5 y 8 de PROJECT_PRINCIPLES.md).

Stack: PostgreSQL, Redis, Blade + TailwindCSS + AlpineJS (solo donde aporta),
Vite (servicio `assets` con Node 22 en el compose), Docker Compose (dev y
producción: ADR-002), Spatie Permission para roles y permisos (ADR-007).

## Organización por dominios

Dominios actuales (ADR-001): **Products, Orders, Payments, Users**.

- Los dominios no tienen carpetas propias ni paquetes; se expresan con prefijos y
  convenciones de nombres sobre la estructura Laravel estándar:

```
app/
├── Actions/          → un caso de uso por clase (CreateProductAction, ConfirmPaymentAction…)
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

Ejemplos previstos: `CreateProductAction`, `UpdateProductAction`,
`ChangeProductPriceAction`, `PlaceOrderAction`, `ConfirmPaymentAction`,
`RegisterWhatsAppSaleAction`, `DispatchOrderAction`, `CancelOrderAction`.

## Eventos y listeners

Se usan cuando hay una verdadera separación de responsabilidades (ej: `OrderPaid`
→ descontar stock, notificar al cliente). Si una reacción es parte del caso de uso
mismo, se ejecuta dentro de la Action, no vía evento.

## Autenticación y roles (Users)

- Flujo de auth con **Laravel Breeze (stack Blade)** adaptado a `/admin`: login,
  logout, recuperación de contraseña y perfil propio. Sin registro público ni
  verificación de email (panel interno; el cliente web es anónimo — Spec 00).
- Roles con **spatie/laravel-permission** (ADR-007): `admin`, `vendedor`,
  `deposito`, uno por usuario. Autorización por recurso en `app/Policies/`;
  checks de rol con middleware `role:*` y `hasRole()`.
- Baja de usuarios por **desactivación** (`users.is_active`), nunca borrado.
- Auditoría de usuarios/roles: tabla `audit_logs` + servicio `AuditRecorder`
  (ADR-004), implementada desde la Spec 01.

## Pagos (Payments)

- MercadoPago: se integra detrás de un puerto `PaymentGateway` (confirmación
  automática de tarjeta).
- Transferencia bancaria: confirmación manual desde el admin (`ConfirmPaymentAction`).

## Envíos (Shipping)

- Puerto: interfaz `ShippingCalculator` (`quote(PostalCode, ...) → Money`).
- Implementación inicial: tarifas internas (por CP o zona, cargadas desde admin).
- Futuro: API de cotización externa sin tocar el dominio (ADR-006).

## Stock (Inventory mínimo)

- El stock (cajas) es atributo del producto; desciende al confirmarse el pago
  (ADR-005); se restituye al cancelar pedidos pagados/despachados.
- El descuento de stock ocurre dentro de transacciones; se protege contra ventas
  concurrentes con bloqueo optimista/locking de fila (ver ADR-005).

## Dinero y unidades

- Montos en **centavos (BIGINT)**; m² como decimal con precisión controlada;
  cálculos con bcmath (ADR-003).

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
  compleja (cálculo de cajas, descuentos, DTOs).
- TDD obligatorio (principio 3).
- Base de datos de tests: PostgreSQL (`ceramica_test`), mismo motor que producción.

## Calidad

- **Laravel Pint** (`pint --test`) y **PHPStan nivel 8** (Larastan) en CI y en el
  Makefile (`make lint`, `make stan`).
- GitHub Actions: lint → stan → tests (workflow en `.github/workflows/ci.yml`).

## Despliegue

- Un único `docker-compose.yml` sirve para desarrollo y producción (VPS simple,
  sin orquestador): nginx + php-fpm + postgres + redis + mailpit (solo dev).
  Detalle en ADR-002 y en `docs/roadmap.md` (Fase 1).
- Configuración del runtime PHP en archivos separados dentro de `docker/php/`
  (`php.ini`, `opcache.ini`, `www.conf`); healthchecks declarados por servicio
  en el compose.

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
