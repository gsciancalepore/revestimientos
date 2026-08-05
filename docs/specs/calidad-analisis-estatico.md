# Spec — Calidad de análisis estático y gates de CI (PHPStan ↔ Pest 3.8)

- **Estado**: completada (2026-08-05)
- **Fuentes**: investigación de la fricción PHPStan↔Pest 3.8 (2026-08-05) —
  doc oficial de Pest (plugin de PHPStan solo existe para Pest 5), doc oficial
  de PHPStan (stubs: "stub files are only for overriding PHPDocs"), código
  fuente de PHPStan (`TypehintHelper::decideType`, `ComposerJsonAndInstalledJsonSourceLocatorMaker`),
  larastan (sin soporte de Pest), consenso comunitario (issue larastan #706);
  checklist de calidad del dueño; decisiones de la Spec 01

## Objetivo

Resolver la fricción entre PHPStan (nivel 8) y Pest 3.8 de modo que los
**gates de calidad del proyecto queden en verde** con una configuración
sostenible y sin dependencias de terceros, y cerrar la Spec 01 escribiendo y
validando los tests Pest de gestión de usuarios y auditoría.

## Contexto

1. **PHPStan no da soporte oficial a Pest en la versión del proyecto.** El
   plugin `pestphp/pest-plugin-phpstan` existe solo en v5 y requiere
   `pest ^5.0.0` (Pest 3.8 pineado desde el skeleton oficial Laravel 12).
   Larastan no soporta Pest; el maintainer recomienda no analizar los tests.
   Consenso comunitario: excluir `tests/` del análisis o usar PestStan
   (dependencia de terceros) para Pest 3.
2. **La fricción `->with()` no se resolvió con stub files.** Tras la
   investigación realizada, no se encontró una solución basada únicamente en
   stub files que permita resolver la inferencia de `->with()` en Pest 3.8,
   debido a cómo PHPStan combina tipos nativos y PHPDoc: los stubs solo
   sobreescriben PHPDocs, y los tipos nativos reales se re-añaden en
   `TypehintHelper::decideType()`. El retorno real de `test()` en Pest 3.8 es
   `TestCall|HigherOrderTapProxy` (descubierto vía `autoload.files` de
   composer), y la unión nativa reaparece por encima de cualquier `@return`
   del stub.
3. **La validación de stubs no tiene autoload del proyecto**: solo conoce
   bootstrap de larastan y otros stubs propios; los errores de validación
   (`missingType.return`, etc.) no son ignorables.
4. La Spec 01 quedó implementada con los tests de gestión de usuarios y
   auditoría **pendientes** (tarea técnica sin tachar); 10 de esos tests
   nuevos fallaban con `RoleDoesNotExist` (el seed de `RolesSeeder` del
   `Pest.php` de subdirectorio no se aplicaba en esos archivos).
5. **Mismatch de route-model binding en `/admin/usuarios`** (raíz de los 7-8
   fallos restantes al ejecutar los tests de la Spec 01): `Route::resource('usuarios')`
   auto-singulariza el parámetro a `{usuario}`, pero `UserController::update()`
   y `toggleActive()` declaran `User $user`. El binding implícito no matcheaba:
   Laravel inyectaba un `User` vacío (`exists = false`) y el `save()` ejecutaba
   un INSERT con `password` null → errores 500/validación en cascada
   (`SQLSTATE[25P02]: current transaction is aborted`). Se resolvió con
   `->parameters(['usuarios' => 'user'])` en `routes/web.php` y alineando
   `UpdateUserRequest` a `ignore($this->route('user'))`.
6. **La base de test `ceramica_test` (PostgreSQL dedicada, `phpunit.xml`) se
   corrompió** durante la depuración: dos `php artisan test` lanzados en
   paralelo sobrepusieron `migrate:fresh` (una corrida con `RefreshDatabase`
   migraba mientras la otra reseteaba), dejando 4 tablas y `migrations` con 0
   filas → `relation "migrations" does not exist`. Se saneó con un
   `migrate:fresh --force` dedicado a `ceramica_test`
   (`docker compose exec -e DB_DATABASE=ceramica_test app php artisan migrate:fresh --force`),
   quedando 15 tablas / 6 migraciones en verde.

## Reglas de documentación y calidad (no son reglas de negocio; la numeración
de reglas de negocio continúa en las specs de dominio)

1. **PHPStan analiza SOLO `app/` a nivel 8.** Los tests se validan
   ejecutándolos con Pest (es lo que corre el CI). Razón: sin soporte oficial
   de PHPStan para Pest 3.8, analizar `tests/` exige stubs o una dependencia
   de terceros, y la fricción de `->with()` es estructural (ver Contexto).
2. **No reintroducir stubs para analizar tests con Pest 3.8.** La
   investigación no encontró que los stubs reduzcan el retorno nativo
   `TestCall|HigherOrderTapProxy`. Opciones futuras documentadas: plugin
   oficial de Pest (requiere migrar a Pest 5) o PestStan (dependencia de
   terceros, compatible con Pest 3).
3. **Los gates de calidad del proyecto son**: `make stan` (PHPStan nivel 8
   sobre `app/`), `make test` (suite Pest completa) y `make lint`
   (Pint `--test`). Rector no forma parte del stack actual; se evaluará en
   futuras migraciones mayores del framework o del lenguaje.
4. **Los tests de gestión de usuarios siembran los roles en `beforeEach` a
   nivel de archivo** (patrón documentado de Pest), usando los seeders
   idempotentes (`findOrCreate`/`updateOrCreate`). No dejar seeds en
   `Pest.php` de subdirectorio: no aplican a todos los archivos.

## Criterios de aceptación

- [x] `make stan` en verde: PHPStan nivel 8, paths = `app` únicamente.
- [x] `make test` en verde: la suite completa de Pest (52 tests) incluye los
      tests de gestión de usuarios y auditoría de la Spec 01 (antes 10 fallaban;
      ver Contexto 4-6 para raíces y fixes).
- [x] `make lint` en verde: Pint sin cambios pendientes.
- [x] Rector: fuera del stack actual; evaluar solo en futuras migraciones
      mayores.
- [x] `phpstan.neon` sin `stubFiles` ni `scanDirectories`; eliminados los 4
      `phpstan-*.stub`.
- [x] `.ai/rules` actualizadas: `general.md` refleja la decisión (PHPStan
      sobre `app/`; sin stubs para Pest 3.8); `index.md` sin glob de stubs
      huérfanos.
- [x] CI (`ci.yml`) sin cambios: valida la misma secuencia (phpstan, pint,
      pest).
- [x] Spec 01 cerrada: tarea "Tests Pest (TDD)" tachada y estado actualizado;
      `roadmap.md` refleja el cierre.

## Tareas técnicas

- [x] Investigación de la fricción PHPStan↔Pest 3.8 (doc oficial de Pest y
      de PHPStan, código fuente de PHPStan, larastan, comunidad).
- [x] Decisión con el dueño: PHPStan sobre `app/` únicamente; eliminar los 4
      stubs.
- [x] Especificación (este documento).
- [x] `phpstan.neon`: `paths: [app]`, quitar `stubFiles` y `scanDirectories`.
- [x] Eliminar `phpstan-pest-functions.stub`, `phpstan-pest-shells.stub`,
      `phpstan-testcase.stub` y `phpstan-testresponse.stub`.
- [x] Fix de los 10 tests con `RoleDoesNotExist`: mover el seed de
      `RolesSeeder` a `beforeEach` a nivel de archivo en
      `UserManagementTest` y `UserAuditTest`; eliminar
      `tests/Feature/Usuarios/Pest.php` (no aplicaba el seed en subdirectorio).
      Además se resolvió el mismatch de route-model binding (Contexto 5) y se
      saneó la base de test corrompida (Contexto 6): suite final 52 passed.
- [x] Actualizar `.ai/rules/general.md` (decisión) y `index.md`.
- [x] Verificación de calidad: `make stan`, `make test`, `make lint`.
- [x] Cerrar la Spec 01 (tachar tests pendientes) y actualizar `roadmap.md`.

## ADR — Análisis estático de tests

### Estado

Aceptado — 2026-08-05

### Decisión

Se excluye el directorio `tests/` del análisis de PHPStan mientras el proyecto
utilice Pest 3.x.

### Contexto

Durante la implementación de la Spec 01 se investigó la integración entre
PHPStan y Pest 3.x utilizando la documentación oficial de ambas herramientas,
el código fuente de PHPStan y la documentación de la comunidad.

La investigación concluyó que la inferencia de tipos utilizada por Pest 3 para
los datasets (`->with()`) no puede resolverse únicamente mediante *stub files*.
Las alternativas viables son:

- implementar una Dynamic Return Type Extension para PHPStan;
- incorporar una dependencia externa (PestStan);
- migrar el proyecto a Pest 5 y utilizar su plugin oficial para PHPStan.

Ninguna de estas alternativas resulta proporcionada para el estado actual del
proyecto.

### Consecuencias

- PHPStan se ejecuta a nivel 8 únicamente sobre el código de producción (`app/`).
- La calidad de los tests continúa validándose mediante la ejecución completa
  de la suite de Pest en el pipeline de CI.
- Esta decisión podrá revisarse cuando el proyecto migre a Pest 5 o cuando
  exista soporte oficial equivalente para Pest 3.x.
