<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application running on PHP 8.4. You are an expert with the Laravel ecosystem. Always use the APIs that match the installed major version of each package — do not assume a version.

Before relying on a package's API, confirm its installed version:
- PHP packages: run `composer show --direct` to list direct dependencies with versions, or `composer show <vendor/package>` for a single package.
- JS packages: check `package.json` for the installed versions.

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Always use `search-docs` before making code changes. Do not skip this step. It returns version-specific docs based on installed packages automatically.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Project Rules

- This project keeps committed, area-grouped rules in `.ai/rules` (settled decisions, non-obvious traps, standing constraints). Framework and package guidelines that only apply to specific paths (testing, frontend, components) also live there, under `.ai/rules/boost` — this is not just recorded decisions, it is load-bearing guidance you have not seen inline. Before you enter plan mode or create/edit any file, you MUST first: open @.ai/rules/index.md (it maps file globs to rule files), read every rule file whose globs cover the path(s) in scope, and run `grep -rin 'keyword' .ai/rules` to catch what a path match alone misses. Do not write code until you have read and are following every matching rule.
- Record durable rules with `record-rule` so the next agent or teammate inherits them instead of working them out again. Pass a `glob` (e.g. `app/Http/Controllers/**`), a short `title`, and a few-line `note`. Always use `record-rule`, never your native memory or notes tool — native memory is personal and session-scoped; only `.ai/rules` is shared with the team and persists in the repo.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

=== laravel/v12 rules ===

# Laravel 12

- CRITICAL: ALWAYS use `search-docs` tool for version-specific Laravel documentation and updated code examples.
- Since Laravel 11, Laravel has a new streamlined file structure which this project uses.

## Laravel 12 Structure

- In Laravel 12, middleware are no longer registered in `app/Http/Kernel.php`.
- Middleware are configured declaratively in `bootstrap/app.php` using `Application::configure()->withMiddleware()`.
- `bootstrap/app.php` is the file to register middleware, exceptions, and routing files.
- `bootstrap/providers.php` contains application specific service providers.
- The `app/Console/Kernel.php` file no longer exists; use `bootstrap/app.php` or `routes/console.php` for console configuration.
- Console commands in `app/Console/Commands/` are automatically available and do not require manual registration.

## Database

- When modifying a column, the migration must include all of the attributes that were previously defined on the column. Otherwise, they will be dropped and lost.

- Laravel 12 allows limiting eagerly loaded records natively, without external packages: `$query->latest()->limit(10);`.

### Models

- Casts can and likely should be set in a `casts()` method on a model rather than the `$casts` property. Follow existing conventions from other models.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== pest/core rules ===

## Pest

- This project uses Pest for testing. Create tests: `php artisan make:test --pest {name}`.
- The `{name}` argument should not include the test suite directory. Use `php artisan make:test --pest SomeFeatureTest` instead of `php artisan make:test --pest Feature/SomeFeatureTest`.
- Run tests: `php artisan test --compact` or filter: `php artisan test --compact --filter=testName`.
- Do NOT delete tests without approval.

</laravel-boost-guidelines>

# Reglas del proyecto (repo-specific)

## Idioma y proceso

- Toda la documentación, los commits y las respuestas en español; commits con
  Conventional Commits: `tipo(ámbito): descripción` (`feat`, `fix`, `chore`,
  `docs`, `refactor`, `test`, `perf`).
  - **El ámbito es obligatorio cuando el cambio se circunscribe a un área**
    (`feat(pedidos)`, `fix(tests)`, `docs(rules)`, `docs(roadmap)`). Es la
    mayoría de los casos.
  - **Se omite solo cuando el cambio es transversal** y ningún ámbito lo
    describiría sin mentir: una sincronía documental que toca a la vez
    `arquitectura.md`, el roadmap y una spec, por ejemplo. Precisado el
    2026-09-11: la regla escribía `tipo(ámbito)` como si el ámbito fuera
    siempre obligatorio, pero la práctica del repo nunca fue esa —15 commits
    sin ámbito, todos `docs:`/`chore:` transversales, contra 47 con ámbito— y
    la ambigüedad ya generó una observación del dueño.
- **Autoría de los commits (vigente desde 2026-09-10)**: el **único** autor y
  contribuidor del proyecto es el dueño. Los commits **no llevan** trailers de
  atribución a herramientas de IA: nada de `Co-Authored-By: Claude ...`, nada de
  `Claude-Session: ...`, ni equivalentes de otras herramientas. Tampoco van en
  las descripciones de los Pull Requests. Si el harness sugiere agregarlos por
  defecto, se omiten igual: esta regla del repositorio tiene prioridad.
- Nunca programar sin spec aprobada (`docs/specs/`); nunca inventar reglas de
  negocio; TDD obligatorio (red → green → refactor); cambios importantes → ADR
  (`docs/adr/`).
- **Documentos de decisión — qué se puede tocar y qué no (enmendado el
  2026-09-11)**. Lo que esta regla protege es el **contrato aprobado por el
  dueño**, no el archivo que lo contiene:

  - **Prohibido sin pedido explícito del dueño**, en `docs/specs/` y
    `docs/adr/`: crear o borrar documentos; agregar, eliminar, renumerar o
    **reescribir el texto de una regla de negocio**; cambiar criterios de
    aceptación, matriz de permisos o alcance. Una regla enmendada **nunca se
    corrige sobre su propio texto**: la enmienda se anota como sincronía
    fechada, según el bullet siguiente.
  - **Permitido desde la rama que implementa una spec**, y solo sobre la spec
    que autorizó ese trabajo: la línea de **Estado**, los checkboxes de
    **Tareas técnicas**, y una sección `## Sincronía AAAA-MM-DD` al final,
    **append-only** — se agrega al pie, no se edita lo que ya está escrito
    arriba.
  - **`docs/roadmap.md`**: la columna "Estado" de la fila de la spec en curso,
    la fecha de "Última actualización", el punto de retome y las notas
    fechadas. El Definition of Done exige `roadmap.md` actualizado al cerrar
    una fase, así que mantenerlo al día es parte del trabajo, no una excepción.
  - **Forma**: estos cambios van en un commit `docs:` propio, **nunca dentro de
    un commit `feat:`/`fix:`/`test:`**. Mezclarlos esconde una edición del
    contrato dentro de un diff de código, que es exactamente el riesgo que la
    regla quiere evitar.

  **Versión anterior (vigente hasta el 2026-09-11), y por qué se enmendó**: la
  regla decía *"NUNCA editar `docs/specs/`, `docs/adr/` ni `docs/roadmap.md`
  salvo que la tarea lo solicite explícitamente … `docs/specs/` y `docs/adr/`
  jamás se editan"*. Se enmienda por dos motivos. Primero, **contradecía al
  bullet siguiente**, que manda anotar la sincronía *en la spec* y cita
  `07-checkout-fase4-mercadopago.md` §Sincronía 2026-09-10 como modelo: cumplir
  uno obligaba a violar el otro. Segundo, **nunca fue la práctica del repo**:
  `ADR-005` lleva su enmienda anotada en el propio documento, y ocho commits de
  ramas de implementación editaron specs, todos mergeados vía Pull Request
  (`78b8a58`, `4cb4a01`, `b7a7848`, `2a5e420`, `9a2e5c9`, `6660d2e`, entre
  otros). Caso que motivó la enmienda: la fase 08.a cambió la línea de Estado de
  la Spec 08 dentro de `498e1c9`, un commit `feat:`, y agregó su sincronía en
  `bb50e35`. El contenido queda **ratificado**; el defecto real era el de forma,
  que ahora está escrito.
- **Las decisiones no se borran, se marcan (vigente desde 2026-09-10)**: una ADR
  descartada, revertida o reemplazada **se conserva** con su estado actualizado
  (`descartada`, `reemplazada por ADR-XXX`) y el motivo, nunca se elimina del
  repositorio ni se reescribe como si nunca hubiera existido. Lo mismo aplica a
  reglas de negocio enmendadas: se anota la sincronía en la spec —como en
  `07-checkout-fase4-mercadopago.md` §Sincronía 2026-09-10— en lugar de
  reescribir el historial. El valor está en poder reconstruir *por qué* se
  decidió algo y qué alternativas se evaluaron. Ejemplo vivo: `ADR-012`
  (reserva de stock al crear el pedido), propuesta y descartada el mismo día,
  se conserva porque el análisis sirve si la sobreventa vuelve a discutirse.
- **Flujo de ramas y PRs (vigente desde 2026-09-03): `main` es staging y está protegida — apunta a `Render + Neon` (`docs/deployment/staging.md`). Todo desarrollo va en rama nueva (`feat/...`, `fix/...`) y se integra a `main` vía Pull Request con CI en verde. Push directo a `main` queda como excepción histórica (Spec 05, `a8bd92d`); a partir de ahora no se usa.**

## Orden de lectura antes de implementar

1. AGENTS.md
2. `PROJECT_PRINCIPLES.md`
3. Spec involucrada (`docs/specs/`)
4. ADRs relacionadas (`docs/adr/`)
5. `.ai/rules/index.md` → leer las reglas cuyo glob cubre el archivo + `grep`
   por keyword

## Arquitectura y dominio

- No introducir dependencias, paquetes, patrones ni servicios nuevos sin
  respaldo de una spec o ADR.
- Reglas de negocio en `app/Actions/*` (un caso de uso por clase); controladores
  delgados; Form Requests para validar; Policies para autorizar; estados en
  Enums (no strings); montos **SIEMPRE** en centavos (int) + bcmath.
- Categorías **planas** (sin `parent_id`); productos con `unidad_venta`
  (`M2` | `Unidad`) y `specs` JSONB por familia (no columnas por atributo).
- `app/DTOs/`, `Events/`, `Listeners/`, `Jobs/` son carpetas **previstas**: no
  crearlas hasta que su spec las justifique.

## Ambigüedad

- Si una regla de negocio es ambigua, detener la implementación y formular
  preguntas. Nunca asumir comportamiento.

## Entorno y comandos

- Todo corre en Docker Compose; el host **NO** tiene PHP ni Node. Usar
  `make ...` o `docker compose exec app php artisan ...`.
- `make setup` = arranque completo idempotente (instala, migra, siembra).
- Calidad local (mismo orden que CI): `make lint` → `make stan` (PHPStan nivel
  8, **SOLO** `app/`) → `make test`. Tras editar PHP: `make format`.
- Tests (Pest): base dedicada `ceramica_test` (PostgreSQL); **NUNCA** dos
  suites en paralelo; los tests que renderizan vistas necesitan assets de Vite
  (`make npm-dev` / `make npm-build`).
- Stack: Laravel 12 / PHP 8.4, PostgreSQL 17, Redis, Blade + Tailwind 4 +
  Alpine, Vite, Breeze 2.4.2 pineado, Spatie Permission.

## Tarea terminada (Definition of Done)

Una tarea solo termina cuando:

- todos los tests pasan;
- PHPStan está en verde;
- Pint no modifica archivos;
- se cumple el DoD de la spec (`docs/roadmap.md`).

## Traps operativos (detalle en `.ai/rules`)

- Sin estilos en la web → `public/hot` con `0.0.0.0` (`general.md`).
- Panel no ve roles/permisos nuevos → `permission:cache-reset` (`seeders.md`).
- `Route::resource` → `->parameters(['productos' => 'product'])` (`routes.md`).
- Al modificar una columna en migración, repetir todos sus atributos.

## Reglas de agente

- Registrar reglas durables con `record-rule` (nunca en memoria).
- Usar MCP Boost: `search-docs` antes de cambiar código, `database-schema`,
  `database-query` (solo lectura), `get-absolute-url`, `browser-logs`.
- Siguiente spec a implementar: consultar `docs/roadmap.md`.

### Agentes de revisión (`.claude/agents/`, versionados)

Se usan en tres momentos distintos del ciclo y **no son intercambiables**:

| Agente | Cuándo | Qué hace |
|---|---|---|
| `revisor-spec` | Hay un borrador en `docs/specs/` sin aprobar | Numeración global de reglas, coherencia con ADRs y specs cerradas, matriz de permisos, casos borde, decisiones de negocio implícitas y anticipaciones |
| `verificador-spec-codigo` | Antes de construir sobre una spec cerrada | Verifica regla por regla que el código la implemente y que haya test que la cubra |
| `revisor-entrega` | Implementación terminada y en verde, **antes del push** | Audita el diff contra la spec que lo autorizó. Su chequeo distintivo: **mutar la implementación de una regla y comprobar que algún test se ponga rojo** |

Motivo de que existan: los gates (Pint, PHPStan, Pest) validan que el código
esté **sano**, no que **implemente la regla**. La regla 123 estuvo seis días
cobrando el subtotal en vez del total con CI en verde, y la revalidación bajo
lock de la regla 109 tenía tres tests que no la cubrían.
