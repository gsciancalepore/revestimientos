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
- **Arquitectura**: [`docs/arquitectura.md`](docs/arquitectura.md)
- **Roadmap** (fases + Definition of Done): [`docs/roadmap.md`](docs/roadmap.md)
- **Decisiones (ADRs)**: [`docs/adr/`](docs/adr/)

## Acceso al panel

- Login en `/admin/login`; la cuenta inicial se crea con
  `php artisan db:seed` usando las credenciales de entorno
  (`ADMIN_NAME`, `ADMIN_EMAIL`, `ADMIN_PASSWORD` en `.env`).

## Requisitos

- Docker + Docker Compose (todo corre en contenedores, incluyendo el frontend:
  no hace falta instalar PHP ni Node en el host)

## Primer arranque

```bash
make setup
```

- Levanta los contenedores (incluye el build de la imagen PHP).
- Instala dependencias de Composer, inicializa Pest y genera la clave de la app.

Web: <http://localhost:8080> · Mailpit (mails de dev): <http://localhost:8025>

## Comandos útiles

Ejecutar siempre en la raíz del proyecto.

| Comando | Descripción |
|---|---|
| `make up` / `make down` | Levantar / detener los servicios |
| `make logs` | Logs del contenedor PHP |
| `make shell` | Terminal dentro del contenedor PHP |
| `make test` | Suite de tests (Pest) |
| `make lint` | Laravel Pint (verifica estilo) |
| `make format` | Aplica estilo con Pint |
| `make stan` | PHPStan nivel 8 (análisis estático) |
| `make npm-install` | Instala dependencias de npm (contenedor `assets`) |
| `make npm-dev` | Levanta el dev server de Vite (servicio `assets`, http://localhost:5173) |
| `make npm-build` | Compila los assets para producción |

Todos los comandos de PHP y Node se ejecutan **dentro de contenedores**
(`docker compose exec app ...`); el host no necesita PHP ni Node.

## Calidad

- TDD obligatorio (red → green → refactor) y spec aprobada antes de codear
  (ver `PROJECT_PRINCIPLES.md`).
- CI en GitHub Actions: Pint → PHPStan → Pest (`ci.yml`).
- Definition of Done por fase en `docs/roadmap.md`.
