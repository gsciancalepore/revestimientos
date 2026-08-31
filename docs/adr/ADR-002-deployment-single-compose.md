# ADR-002 — Despliegue: un único Docker Compose

- **Estado**: aceptado (2026-08-05)
- **Contexto**: el proyecto corre sobre PHP 8.4/PostgreSQL/Redis. No hay PHP ni
  Composer en el host del desarrollador. El dueño pidió explícitamente: "armamos
  en contenedor para luego en el futuro no tener que escalarlo para deployarlo".

## Decisión

Un **único `docker-compose.yml`** sirve tanto para desarrollo como para
producción. El despliegue se hace en un solo VPS con `docker compose up -d
--build`, sin orquestador ni servicios de infraestructura externos (postgres y
redis corren en el mismo host).

Servicios: `app` (php-fpm 8.4), `web` (nginx), `db` (postgres), `redis`,
`mailpit` (solo dev).

## Consecuencias

- Mismo entorno en dev y producción; reproducibilidad total.
- `mailpit` y `assets` (node) son servicios solo de desarrollo: en el VPS se
  quitan del archivo (o se omiten) sin afectar al resto.
- Configuración del runtime PHP en archivos separados (`docker/php/php.ini`,
  `opcache.ini`, `www.conf`) y healthchecks por servicio en el compose: el mismo
  archivo documenta su propio estado de salud.
- Deploy simple: clonar → `.env` → `docker compose up -d --build`.
- Límite conocido: un solo nodo. Cuando el producto crezca (múltiples comercios),
  la migración natural es separar la base de datos y/o el worker de la web, y
  orquestar con Compose remoto o un host de contenedores — sin cambios de
  arquitectura de la aplicación.
- Backup: volúmenes de postgres (dump programado por cron del VPS).

## Alternativas

- **Laravel Sail**: descartado — orientado a dev, no al deploy.
- **Kubernetes / swarm**: descartado — sobredimensionado (principio 5).
- **Paas (Forge/Railway/etc.)**: descartado por decisión del dueño de mantener
  el control en un VPS simple.

## Nota 2026-08-31 — Relación con ADR-008

ADR-002 continúa vigente como referencia para una futura estrategia de
Production basada en VPS. Para Staging, fue complementado/sustituido por
ADR-008 (Koyeb + Neon). No invalida el historial: ADR-002 documenta la decisión
original de VPS y su contexto; ADR-008 rige exclusivamente Staging.
