# Spec Higiene 01 — ShippingRate Actions + AllowedSpecs + User::role()

- **Estado**: **cerrada (2026-09-03)** — implementada y mergeada a `main` en `bc45b67` (PR #4). El documento quedó diciendo *borrador — pendiente de aprobación* hasta el 2026-09-12, cuando la auditoría documental lo detectó; ver §Sincronía al pie.
- **Estado original (2026-09-03)**: borrador — pendiente de aprobación (refactor/higiene, con corrección de comportamiento únicamente para el estado inválido "usuario sin rol asignado"; documentada para implementar **después** de mergear `07.1` y `07.2`)
- **Fuentes**: `.ai/rules/actions.md`, `controllers.md`, `productos.md`, `app.md`, `AGENTS.md: Arquitectura y dominio (Actions delgadas, Policies, validación en Requests, estados Enum, centavos bcmath)`, `ADR-006`/`ADR-007`, principios 5/8 (simplicidad/YAGNI)

## Objetivo

Higiene técnica: alinear `ShippingRateController`, validación de `specs` y `User::role()` con los patrones ya usados en `Products`/`Categories`/`Users`, sin nueva funcionalidad.

## Contexto

- `ShippingRateController` hace `ShippingRate::create/update/delete` directo (73L), mientras `Category/Product/User` usan `Actions` + `Gate` + `Request`.
- `StoreProductRequest`/`UpdateProductRequest` duplican `validateSpecsKeys()` closure idéntico (`ProductSpecs::allowedKeysFor()`).
- `User::role(): UserRole` retorna `Vendedor` fallback si no hay roles, enmascara seeder faltante; debe lanzar `DomainException`.

Esta spec es `refactor`/`chore`, con corrección únicamente para el estado inválido "usuario sin rol asignado"; no introduce dependencias nuevas (`AGENTS.md: No introducir dependencias sin respaldo`).

## Reglas de negocio (refactor)

HIG-01. **`AllowedSpecs` Rule**: `app/Rules/AllowedSpecs.php` implementa `ValidationRule + DataAwareRule` con `setData(): static`; `validate()` resuelve `Category::find(category_id)` → `ProductSpecs::allowedKeysFor()` → `array_diff` → `fail('Los atributos no están permitidos para la familia "...".')`. La decisión `category_id null / Category no encontrada → return sin fail` es correcta porque `required/exists` pertenece al `Request`. Usada en `StoreProductRequest`/`UpdateProductRequest` como `['nullable','array', new AllowedSpecs]` + `specs.*` string; se elimina `validateSpecsKeys()` duplicado.

HIG-02. **`ShippingRate Actions`**: `app/Actions/CreateShippingRateAction.php`, `app/Actions/UpdateShippingRateAction.php`, `app/Actions/DeleteShippingRateAction.php` (una clase = un caso, sin HTTP, sin auditoría — coherente con `Category` `ADR-004` no audita tarifas). `ShippingRateController` queda delgado: `__construct` DI de las 3 Actions + `Gate::authorize` + `validated()` + `execute()` + `redirect`. Sin lógica en controller.

HIG-03. **`User::role()` excepción**: `User::role(): UserRole` lanza `DomainException('El usuario no tiene rol asignado.')` si `roles->first() === null`, en lugar de fallback `Vendedor`. Toda `Policy` que invoque `User::role()` debe evitar que la ausencia de rol produzca un `500`; en ese escenario la autorización debe resultar en `false/403` (la implementación concreta — `try/catch` u otra — queda a criterio del implementador, la spec no impone `try/catch` si existe alternativa más limpia). `resources/views/layouts/navigation.blade.php:19` pasa de `role()->value==='admin'` a `hasRole('admin')` (Spatie `HasRoles`); `resources/views/admin/usuarios/*` ya asume usuarios con rol (seed `RolesSeeder`).

## Matriz de permisos

Sin cambios: `ShippingRatePolicy` solo `admin` (`role:admin` + `Policy`), `UserPolicy`/`CategoryPolicy`/`ProductPolicy` igual.

| Acción | admin | vendedor | depósito | público |
|---|---|---|---|---|
| Ver/crear/editar/borrar tarifas | ✓ | 403 | 403 | — |
| Crear/editar producto con `specs` | ✓ (validado por `AllowedSpecs`) | 403 | 403 | — |
| `User::role()` sin rol | `DomainException` (bug visible) → `Policy` `false/403` | `DomainException` → `false/403` | `DomainException` → `false/403` | — |

## Casos borde

- `AllowedSpecs` con `category_id` null o `Category` no encontrada → `return` sin `fail` (otra regla `exists` lo cubre).
- `AllowedSpecs` con `specs` vacío → no `fail`.
- `CreateShippingRateAction` con `cp` con espacios → `trim` (coherente con `StoreShippingRateRequest prepareForValidation trim`).
- `User::role()` sin rol en `Policies` → `false/403`, no `500`; en `navigation` `hasRole` evita excepción.
- `User::role()` sin rol en `ProfileTest` → usar `User::factory()->withRole(...)` para tests de perfil.

## Criterios de aceptación

- [x] `app/Rules/AllowedSpecs.php` existe, `DataAwareRule` con `setData(): static` y regresión: `AllowedSpecs` rechaza claves inválidas y permite claves válidas por familia.
- [x] `StoreProductRequest`/`UpdateProductRequest` usan `new AllowedSpecs`, sin `validateSpecsKeys()`.
- [x] `app/Actions/CreateShippingRateAction.php`, `app/Actions/UpdateShippingRateAction.php`, `app/Actions/DeleteShippingRateAction.php` existen y `ShippingRateController` delega (`__construct` DI, `Gate`, `validated()`).
- [x] `app/Models/User.php:role()` lanza `DomainException` si no hay roles; `Policies` garantizan `false/403` si no hay rol; `navigation.blade.php` con `hasRole('admin')`; usuarios con roles mantienen comportamiento.
- [x] Pint, PHPStan nivel 8 (`app/`), Pest verde (`make lint → stan → test` una suite `ceramica_test`), sin `TODO`s; CI `lint→stan→test` verde. Tests de regresión cubren behaviours afectados (no suite artificial).

## Decisiones arquitectónicas

- **Una Action = un caso** (igual que `CreateCategory/Product`): `ShippingRate` sin auditoría (YAGNI, `ADR-004` reserva auditoría para precios/stock/pedidos).
- **`AllowedSpecs` DRY**: extrae closure duplicado a `Rule` reusable; `DataAwareRule` evita resolver `Category` fuera del request.
- **`User::role()` throw**: hace visible seeder faltante (`RolesSeeder`/`withRole` en factory) en lugar de silencioso `vendedor`; `Policies` no deben `500` sino `403`.
- **Sin nuevas dependencias**; `app/Rules/` es nueva carpeta para reglas de validación (análoga a `app/Contracts/` para puertos).

## Evolución documentada (no anticipada)

- No `ShippingRate` auditoría, no `User` `hasRoleOrThrow`, no `DTOs/Events`.

## Tareas técnicas

- [x] `php artisan make:rule AllowedSpecs --no-interaction` + implementar `DataAwareRule`.
- [x] Refactor `Store/UpdateProductRequest` → `new AllowedSpecs`.
- [x] `Create/Update/DeleteShippingRateAction` + refactor `ShippingRateController`.
- [x] `User::role()` throw + `Policies` garantizan `false/403` + `navigation` `hasRole`.
- [x] `make format` → `make lint` → `make stan` → `make test`; PR `chore/higiene-01-shippingrate-allowedspecs-userrole` **después** de `07.2` merge (respeta tu orden; rama `chore/` porque es `refactor/chore`, no `feat`).

## Nota de handoff

Implementar **después** de `07.2` merge a `main`. Rama `chore/higiene-01-shippingrate-allowedspecs-userrole` desde `main`. No requiere TDD (refactor), pero exige tests de regresión para behaviours afectados. Seguir `AGENTS.md`, `.ai/rules`, `PROJECT_PRINCIPLES.md`, `make` Docker.

## Sincronía 2026-09-12 — el documento decía "borrador" con todo implementado

Detectado por la auditoría documental completa del 2026-09-12. La spec se implementó y mergeó el
2026-09-03 (`bc45b67`, PR #4), pero su línea de Estado y sus diez casillas quedaron como si nada se
hubiera hecho. Un agente nuevo que leyera `docs/specs/` habría encontrado un borrador sin aprobar
con diez tareas pendientes, y podía razonablemente ponerse a implementarlo de nuevo.

Verificado punto por punto contra el código antes de marcar nada:

- `app/Rules/AllowedSpecs.php` existe y los dos Form Requests lo usan.
- `CreateShippingRateAction`, `UpdateShippingRateAction` y `DeleteShippingRateAction` existen, y
  `ShippingRateController` delega en ellas.
- `User::role()` lanza `DomainException` cuando el usuario no tiene rol.

El estado original se conserva arriba, según la regla de que las decisiones se marcan y no se
borran: lo que cambió es el estado, no lo que la spec decidió.
