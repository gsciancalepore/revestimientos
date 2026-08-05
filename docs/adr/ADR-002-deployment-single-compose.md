# ADR-002 — Deployment Strategy: Single Docker Compose Deployment

- **Estado**: aceptado (2026-08-05)
- **Contexto**: el proyecto corre sobre PHP 8.4/PostgreSQL/Redis. No hay PHP ni
  Composer en el host del desarrollador. El dueño pidió explícitamente: "armamos
  en contenedor para luego en el futuro no tener que escalarlo para deployarlo".

## Decisión

Un **único `docker-compose.yml`** sirve tanto para desarrollo como para producción.
Despliegue en un solo VPS con `docker compose up -d --build`, sin orquestador ni
servicios de infraestructura externos (postgres y redis corren en el mismo host).

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
- **Paas (Forge/Railway/etc.)**: descartado por decisión del dueño de mantener el
  control en un VPS simple.
