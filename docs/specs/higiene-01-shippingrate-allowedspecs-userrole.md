# Spec Higiene 01 — ShippingRate Actions + AllowedSpecs + User::role()

- **Estado**: borrador (2026-09-03) — pendiente de aprobación (refactor sin cambio de comportamiento; documentada para implementar **después** de mergear `07.1` y `07.2`)
- **Fuentes**: `.ai/rules/actions.md`, `controllers.md`, `productos.md`, `app.md`, `AGENTS.md: Arquitectura y dominio (Actions delgadas, Policies, validación en Requests, estados Enum, centavos bcmath)`, `ADR-006`/`ADR-007`, principios 5/8 (simplicidad/YAGNI)

## Objetivo

Higiene técnica sin nueva funcionalidad: alinear `ShippingRateController`, validación de `specs` y `User::role()` con los patrones ya usados en `Products`/`Categories`/`Users`.

## Contexto

- `ShippingRateController` hace `ShippingRate::create/update/delete` directo (73L), mientras `Category/Product/User` usan `Actions` + `Gate` + `Request`.
- `StoreProductRequest`/`UpdateProductRequest` duplican `validateSpecsKeys()` closure idéntico (`ProductSpecs::allowedKeysFor()`).
- `User::role(): UserRole` retorna `Vendedor` fallback si no hay roles, enmascara seeder faltante; debe lanzar `DomainException`.

Esta spec es `refactor`/`chore`, no `feat`; no introduce dependencias nuevas (`AGENTS.md: No introducir dependencias sin respaldo`).

## Reglas de negocio (refactor)

HIG-01. **`AllowedSpecs` Rule**: `app/Rules/AllowedSpecs.php` implementa `ValidationRule + DataAwareRule` con `setData(): static`; `validate()` resuelve `Category::find(category_id)` → `ProductSpecs::allowedKeysFor()` → `array_diff` → `fail('Los atributos no están permitidos para la familia "...".')`. Usada en `StoreProductRequest`/`UpdateProductRequest` como `['nullable','array', new AllowedSpecs]` + `specs.*` string; se elimina `validateSpecsKeys()` duplicado.

HIG-02. **`ShippingRate Actions`**: `app/Actions/CreateShippingRateAction`, `UpdateShippingRateAction`, `DeleteShippingRateAction` (una clase = un caso, sin HTTP, sin auditoría — coherente con `Category` `ADR-004` no audita tarifas). `ShippingRateController` queda delgado: `__construct` DI de las 3 Actions + `Gate::authorize` + `validated()` + `execute()` + `redirect`. Sin lógica en controller.

HIG-03. **`User::role()` excepción**: `User::role(): UserRole` lanza `DomainException('El usuario no tiene rol asignado.')` si `roles->first() === null`, en lugar de fallback `Vendedor`. Superficies donde se revela el bug: `Policies` deben envolver `try{ role()===Admin }catch(DomainException => false)` para responder `403` en lugar de `500`; `resources/views/layouts/navigation.blade.php:19` pasa de `role()->value==='admin'` a `hasRole('admin')` (Spatie `HasRoles`); `resources/views/admin/usuarios/*` ya asume usuarios con rol (seed `RolesSeeder`). Tests `ProfileTest` que hacen `User::factory()->create()` sin `withRole` deben usar `withRole(...)` o el `hasRole` evita el `500`.

## Matriz de permisos

Sin cambios: `ShippingRatePolicy` solo `admin` (`role:admin` + `Policy`), `UserPolicy`/`CategoryPolicy`/`ProductPolicy` igual.

| Acción | admin | vendedor | depósito | público |
|---|---|---|---|---|
| Ver/crear/editar/borrar tarifas | ✓ | 403 | 403 | — |
| Crear/editar producto con `specs` | ✓ (validado por `AllowedSpecs`) | 403 | 403 | — |
| `User::role()` sin rol | `DomainException` (bug visible) | `DomainException` | `DomainException` | — |

## Casos borde

- `AllowedSpecs` con `category_id` null o `Category` no encontrada → `return` sin `fail` (otra regla `exists` lo cubre).
- `AllowedSpecs` con `specs` vacío → no `fail`.
- `CreateShippingRateAction` con `cp` con espacios → `trim` (coherente con `StoreShippingRateRequest prepareForValidation trim`).
- `User::role()` sin rol en `Policies` → `catch → false` (no `500` en autorización); en `navigation` `hasRole` evita excepción.

## Criterios de aceptación

- [ ] `app/Rules/AllowedSpecs.php` existe, `DataAwareRule` con `setData(): static` y test `Pest` (si aplica) o cubierto por `ProductManagementTest` con `specs` por familia.
- [ ] `StoreProductRequest`/`UpdateProductRequest` usan `new AllowedSpecs`, sin `validateSpecsKeys()`.
- [ ] `app/Actions/Create/Update/DeleteShippingRateAction.php` existen y `ShippingRateController` delega (`__construct` DI, `Gate`, `validated()`).
- [ ] `app/Models/User.php:role()` lanza `DomainException` si no hay roles; `Policies` con `try/catch→false`; `navigation.blade.php` con `hasRole('admin')`.
- [ ] Pint, PHPStan nivel 8 (`app/`), Pest verde (`make lint → stan → test` una suite `ceramica_test`), sin `TODO`s; CI `lint→stan→test` verde.

## Decisiones arquitectónicas

- **Una Action = un caso** (igual que `CreateCategory/Product`): `ShippingRate` sin auditoría (YAGNI, `ADR-004` reserva auditoría para precios/stock/pedidos).
- **`AllowedSpecs` DRY**: extrae closure duplicado a `Rule` reusable; `DataAwareRule` evita resolver `Category` fuera del request.
- **`User::role()` throw**: hace visible seeder faltante (`RolesSeeder`/`withRole` en factory) en lugar de silencioso `vendedor`; `Policies` no deben `500` sino `403`.
- **Sin nuevas dependencias**; `app/Rules/` es nueva carpeta para reglas de validación (análoga a `app/Contracts/` para puertos).

## Evolución documentada (no anticipada)

- No `ShippingRate` auditoría, no `User` `hasRoleOrThrow`, no `DTOs/Events`.

## Tareas técnicas

- [ ] `php artisan make:rule AllowedSpecs --no-interaction` + implementar `DataAwareRule`.
- [ ] Refactor `Store/UpdateProductRequest` → `new AllowedSpecs`.
- [ ] `Create/Update/DeleteShippingRateAction` + refactor `ShippingRateController`.
- [ ] `User::role()` throw + `Policies` `try/catch` + `navigation` `hasRole`.
- [ ] `make format` → `make lint` → `make stan` → `make test`; PR `feat/higiene-01-...` **después** de `07.2` merge (respeta tu orden).

## Nota de handoff

Implementar **después** de `07.2` merge a `main`. Rama `feat/higiene-01-shippingrate-allowedspecs-userrole` desde `main`. TDD no necesario (refactor), pero tests existentes deben seguir verdes. Seguir `AGENTS.md`, `.ai/rules`, `PROJECT_PRINCIPLES.md`, `make` Docker.

