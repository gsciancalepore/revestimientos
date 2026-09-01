# ADR-009 — Staging con Render + RoadRunner + Octane

* **Estado:** aceptado para Staging (2026-08-31)
* **Fecha:** 2026-08-31
* **Decide:** migrar el runtime de Staging de Koyeb + FrankenPHP a Render Free + RoadRunner + Laravel Octane 2 workers, manteniendo Neon como PostgreSQL y el resto del contrato `DB_*`/`SESSION`/`CACHE`.
* **Alcance:** exclusivamente entorno Staging. No afecta Production futura (ADR-002) ni el dominio de aplicación.

## Contexto

ADR-008 definió Staging con Koyeb Free + Neon Free + `php artisan serve` (luego `FrankenPHP + Octane`). Tras la incorporación de Koyeb a Mistral AI, Koyeb exige plan pago para cuentas nuevas, incompatible con el requisito de staging `$0` de `docs/deployment/staging.md:1` y `ADR-008:14`.

Paralelamente, el build inicial en staging falló por:

* `composer install --no-dev` sin `Telescope` (`require-dev`) provocaba `package:discover` con `TelescopeServiceProvider` incondicional (`bootstrap/providers.php:4`).
* `php artisan serve` single-thread bloqueaba `/up` detrás de `/` con Neon cold-start 4s (`/ 4.68s` vs `/ping 0.45s` aislado, concurrente `4.42s`).
* `FrankenPHP` en Render Free falló con `sh: exec: /usr/local/bin/frankenphp: Operation not permitted` (seccomp `EPERM`, `Caddy` + `CAP_SYS_ADMIN` no permitido en Free).
* `RoadRunner` + `Octane` requiere `ext-pcntl` (`SIGINT` en `InteractsWithServers.php:174`) y `ext-sockets` (`linux/sock_diag.h` vía `linux-headers` en Alpine) y expansión de `$PORT` (`${PORT:-8000}` en forma exec JSON no expande, `adminPort = 2019 + (port - 8000)` con `string` → `Unsupported operand types`).

El objetivo sigue siendo el de ADR-008: entorno remoto gratuito, disponible sin la PC del desarrollador, con Docker, PostgreSQL externo y deploys repetibles.

## Decisión

Se mantiene:

* **GitHub** como fuente de verdad (`main → Staging`).
* **Neon Free** como PostgreSQL (`ep-cool-morning-aceox6ow-pooler.sa-east-1.aws.neon.tech`, `DB_SSLMODE=require`, `SESSION_DRIVER=database`, `CACHE_STORE=database`, `QUEUE_CONNECTION=sync`, `MAIL_MAILER=log`).
* **Docker** multi-stage y `docs/deployment/staging.md` como contrato.

Se cambia:

* **Render Free Web Service** en lugar de Koyeb Free (750h/mes, `PORT` inyectado, `Dockerfile Path = docker/koyeb/Dockerfile`, `Health Check Path = /up`).
* **RoadRunner 2025.1.15 + Laravel Octane 2.19.1** en lugar de `FrankenPHP`. `rr` binario (`60MB`) + `.rr.yaml` (vacío, config vía `-o`) en lugar de `frankenphp` (`170MB`).
* Base `php:8.4-cli-alpine` + `install: pcntl sockets` (además de `pdo_pgsql bcmath intl zip opcache redis`) con `linux-headers` para compilar `sockets`.
* `CMD ["sh","-c","php artisan octane:start --server=roadrunner --host=0.0.0.0 --port=${PORT:-8000} --workers=2 --max-requests=500"]` (2 workers, `max-requests=500`).

Se conserva sin cambios:

* `Telescope` en `require-dev` con registro condicional solo en `local` y si `TelescopeApplicationServiceProvider` existe (`AppServiceProvider.php:12`, fix `cb1002b`).
* `TELESCOPE_ENABLED=false` en staging, `APP_ENV=staging`, `APP_DEBUG=false`.
* `Neon` con 11 migraciones (`telescope_entries` incluida), sin `migrate:fresh`.

## Justificación

Render Free ofrece lo mismo que Koyeb Free para nuestro tamaño (512MB/0.1 vCPU, `PORT`, `Dockerfile`, `HTTPS`, `scale-to-zero` 15min vs 60min) sin costo, compatible con `DB_*` y `Octane`.

RoadRunner con `ext-pcntl` + `ext-sockets` evita `EPERM` de `FrankenPHP/Caddy` en el sandbox Free de Render, mantiene 2 workers concurrentes (evita que `/` 4s bloquee `/up` en `php artisan serve` single-thread) y no requiere `nginx`/`supervisord` (principios 5 y 8).

`linux-headers` es requisito de `ext-sockets` en Alpine (`sockets.c:58 linux/sock_diag.h`), no una nueva abstracción.

`sh -c` para `$PORT` es requisito de Docker exec JSON (no expande `${VAR}`).

## Consecuencias positivas

* Staging sigue `$0` y accesible sin la PC del desarrollador.
* Build `composer install --no-dev` y `package:discover` pasan sin `Telescope`.
* Runtime multi-proceso: `/up` no queda bloqueado detrás de Neon.
* Sin `FrankenPHP`/`Caddy` privilegiado.

## Consecuencias negativas

* Imagen `rr` (60MB) + `sockets`/`pcntl` compilan en build (166s) — aceptable para Free.
* `.rr.yaml` vacío intencional (config vía `-o` en `StartRoadRunnerCommand.php:106`), no se versiona.
* `frankenphp` binario obsoleto eliminado del repo (ignorado en `.gitignore`/`.dockerignore`).

## Relación con ADR-008

ADR-008 sigue vigente para la decisión de **PostgreSQL externo + `main → Staging` + contrato `DB_*`**. ADR-009 **sustituye solo el runtime** `Koyeb + FrankenPHP` por `Render + RoadRunner` para cuentas nuevas. ADR-002 (VPS único) sigue vigente para Production futura.

```text
ADR-002 → Production futura / VPS
ADR-008 → Staging / Koyeb + Neon (histórico, requiere pago)
ADR-009 → Staging actual / Render + RoadRunner + Neon ($0)
```

## Condición de revisión

Revisar cuando: `Render Free` cambie límites, se requieran `workers >2`, `S3` para `FILESYSTEM_DISK`, `Sentry`/`OpenTelemetry`, o `Production` con otro host.
