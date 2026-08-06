# Casa de Cerámicas

Plataforma de venta web y panel administrativo para una casa de cerámicas.
Compra autoservicio (tarjeta vía MercadoPago o transferencia), catálogo con
calculadora de m² → cajas y panel para operar productos, pedidos y stock.

## Documentación

- **Principios del proyecto** (constitución): [`PROJECT_PRINCIPLES.md`](PROJECT_PRINCIPLES.md)
- **Visión**: [`docs/vision.md`](docs/vision.md)
- **Lenguaje ubicuo** (glosario): [`docs/ubiquitous-language.md`](docs/ubiquitous-language.md)
- **Definición de dominio**: [`docs/specs/00-dominio.md`](docs/specs/00-dominio.md)
- **Spec 01 — Autenticación y roles**: [`docs/specs/01-autenticacion-roles.md`](docs/specs/01-autenticacion-roles.md)
- **Spec 02 — Panel + Categorías**: [`docs/specs/02-panel-categorias.md`](docs/specs/02-panel-categorias.md)
- **Spec 03 — Productos**: [`docs/specs/03-productos.md`](docs/specs/03-productos.md)
- **Spec 04 — Catálogo público**: [`docs/specs/04-catalogo-publico.md`](docs/specs/04-catalogo-publico.md)
- **Spec — Calidad de onboarding** (runbook y docs para agentes): [`docs/specs/calidad-onboarding.md`](docs/specs/calidad-onboarding.md)
- **Arquitectura**: [`docs/arquitectura.md`](docs/arquitectura.md)
- **Roadmap** (fases + Definition of Done): [`docs/roadmap.md`](docs/roadmap.md)
- **Decisiones (ADRs)**: [`docs/adr/`](docs/adr/)

## Acceso al panel

- Login en `/admin/login`; la cuenta inicial se crea automáticamente con
  `make setup` (o `make seed`) usando las credenciales de entorno
  (`ADMIN_NAME`, `ADMIN_EMAIL`, `ADMIN_PASSWORD` en `.env`).

## Requisitos

- Docker + Docker Compose (todo corre en contenedores, incluyendo el frontend:
  no hace falta instalar PHP ni Node en el host)

## Primer arranque

```bash
make setup
```

Esto, en orden:

1. Copia `.env.example` a `.env` (si no existe).
2. Construye y levanta los contenedores (`app`, `web`, `db`, `redis`, `mailpit`).
3. Instala dependencias de Composer desde `composer.lock`.
4. Genera la clave de la app.
5. Aplica las migraciones y siembra los 3 roles y el admin inicial
   (`make setup` es repetible: los seeders son idempotentes).

Luego, para el frontend en desarrollo:

```bash
make npm-dev
```

Web: <http://localhost:8080> · Login: <http://localhost:8080/admin/login> ·
Mailpit (mails de dev): <http://localhost:8025> · Vite dev server: <http://localhost:5173>

## Comandos útiles

Ejecutar siempre en la raíz del proyecto. Todo comando PHP/Node se ejecuta
**dentro de contenedores** (`docker compose exec app php artisan ...`); el host
no necesita PHP ni Node. El Makefile lo resume:

| Comando | Descripción |
|---|---|
| `make setup` | Primer arranque completo (instala, migra y siembra) |
| `make up` / `make down` | Levantar / detener los servicios |
| `make logs` | Logs del contenedor PHP |
| `make shell` | Terminal dentro del contenedor PHP |
| `make artisan cmd="route:list"` | Cualquier comando Artisan (ej: `migrate`, `tinker`) |
| `make migrate` | Aplica migraciones |
| `make seed` | Siembra roles y admin inicial (idempotente) |
| `make test` | Suite de tests (Pest) |
| `make lint` | Laravel Pint (verifica estilo) |
| `make format` | Aplica estilo con Pint |
| `make stan` | PHPStan nivel 8 (análisis estático) |
| `make composer cmd="show --direct"` | Cualquier comando de Composer |
| `make npm-install` | Instala dependencias de npm (contenedor `assets`) |
| `make npm-dev` | Levanta el dev server de Vite (servicio `assets`, http://localhost:5173) |
| `make npm-build` | Compila los assets para producción |

## Problemas comunes

| Síntoma | Causa | Solución |
|---|---|---|
| La web carga **sin estilos** ("se ve muy mal") | `public/hot` apunta a `0.0.0.0:5173` | Ver regla "Vite en Docker: hot file..." en `.ai/rules/general.md` |
| Tests que renderizan vistas fallan con `ViteManifestNotFoundException` | No hay `public/hot` ni `public/build` | `make npm-dev` (dev) o `make npm-build` antes de testear; en CI se construyen solos |
| El panel no ve roles/permisos nuevos tras un seeder | Cache de permisos de Spatie | `make artisan cmd="permission:cache-reset"` (regla en `.ai/rules/seeders.md`) |

## Calidad

- TDD obligatorio (red → green → refactor) y spec aprobada antes de codear
  (ver `PROJECT_PRINCIPLES.md`).
- CI en GitHub Actions: Pint → PHPStan → Pest (`ci.yml`).
- Definition of Done por fase en `docs/roadmap.md`.
