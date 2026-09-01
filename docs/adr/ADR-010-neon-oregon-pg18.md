# ADR-010 — Neon Oregon (us-west-2) + Postgres 18 para Staging

* **Estado:** aceptado para Staging (2026-09-01)
* **Fecha:** 2026-09-01
* **Decide:** migrar Neon de `aws-sa-east-1 (São Paulo)` a `aws-us-west-2 (Oregon)` con Postgres 18 (`18.6`), manteniendo Render Free en Oregon y el contrato `DB_*`. Local sigue en `postgres:17-alpine` (paridad a evaluar aparte).
* **Alcance:** exclusivamente entorno Staging. No afecta `docker-compose.yml` local (sigue PG17) ni Production futura (ADR-002).

## Contexto

Tras `ADR-009` (Render Oregon + RoadRunner) el staging quedó operativo pero lento: `/catalogo` `~4.6-5.1s` despierto (vs `0.33s` de `/up`), `home` `3.0s`, `?categoria=porcelanatos` `8.4s`. Medición con `DB::enableQueryLog()` y `curl -w starttransfer` mostró:

* `Request::getScheme()` ya OK por `trustProxies(at:'*')` (`bbfd1fd`), `X-Forwarded-Proto` correcto, `public/build` en `https`.
* Cada query pagaba `~110-130ms` (Neon Brasil vs `3-15ms` local) + `2.5s` cold-start en 1ra query del pool. `CatalogController:156 filtrosSpecs()` hace 8 `distinct specs->>?` secuenciales → `12` queries en `?categoria=` → `3966ms` sum vs `100ms` local. El 92-95% del `totalMs` era DB (`1109/1204`, `3966/4173`).
* Causa: Render está en `Oregon (us-west)` y Neon estaba en `São Paulo (sa-east-1)` — `~180ms` RTT inter-región, validado `local→Brasil 120ms` vs `Render→Brasil 110ms` y `local→Oregon 720ms`.

Postgres 18 (`2025-09-25`, GA `18.6` del `2026-08-13`) es estable hasta `2030-11-14` y ya soportado en Neon (`14/15/16/17/18` en `neon.com/docs/reference/compatibility`). Neon corre PG18 con `io_method='sync'` (AIO `2-3×` aún en rollout), así que el beneficio inmediato es **co-localización**, no AIO. `php:8.4-cli-alpine pdo_pgsql` y `laravel/framework ^12` son compatibles con PG18 (sin extensión custom en este esquema).

Neon no permite cambiar región de un proyecto (`docs/introduction/regions` — crear nuevo y migrar). Aprovechar el nuevo proyecto para subir a PG18 evita otro dump futuro.

## Decisión

Se mantiene:

* **Render Free Oregon** (`PORT`, `Dockerfile = docker/koyeb/Dockerfile`, `Health /up`, `Octane RoadRunner 2w`, `trustProxies at:'*'` en `bootstrap/app.php:17`).
* Contrato `DB_*` (`DB_HOST/DB_PORT/DB_DATABASE/DB_USERNAME/DB_PASSWORD/DB_SSLMODE=require`, `SESSION/CACHE=database`), sin `DATABASE_URL`.
* `.gitignore: rr/.rr.yaml` y `COPY . .` + `composer install --no-dev` sin `rr` versionado.

Se cambia:

* **Neon nuevo:** `ep-orange-sun-afa5blwb-pooler.c-2.us-west-2.aws.neon.tech` (`aws-us-west-2`), Postgres `18.6`, `pooler` con `sslmode=require&channel_binding=require`.
* **Migración:** `pg_dump 18 --data-only --inserts -t products` desde `ep-cool-morning-aceox6ow-pooler.sa-east-1` → `psql` al nuevo (11 migraciones ya aplicadas, `users=1` admin, `roles=3`, `categories=4`, `products=1` preservado). Proyecto anterior en `sa-east-1` se conserva **48h como rollback** y luego se pausa/borra.
* **Local no cambia:** `docker-compose.yml:40` sigue `postgres:17-alpine`. Evaluar bump a `18-alpine` later como decisión de paridad separada (no en este ADR).

## Justificación

Co-localizar `Render (us-west)` con `Neon (us-west-2)` recorta RTT de `~110ms` a `~10-15ms` por query. Con `5` queries (`/catalogo`) el ahorro es `~0.5s`; con `12` queries (`?categoria=`) `~1.2s`, más la eliminación del cold-start inter-región. Medición post-cutover: `/catalogo` `0.35-0.72s` vs `4.6s` (`7-13×` mejor), `/up` `0.36s` sin cambio, assets en `https://` intactos. PG18 no es bloqueante y unifica EOL; Neon maneja `pg_upgrade` con `io_method sync` estable.

## Consecuencias positivas

* Staging `~0.3-0.7s` despierto vs `~5s`, sin tocar `CatalogController`, `ProductSpecs`, `vite` ni `Tailwind`.
* PG18 vigente hasta 2030; dump/restore validado `public.products_id_seq=1`.
* Rollback simple: revertir `DB_HOST` a `sa-east-1` en Render (30s).

## Consecuencias negativas

* Dos proyectos Neon Free simultáneos 48h (duplica `0.5GB/50CUh` temporal, despreciable).
* `local→Oregon` ahora es más lento (`720ms` por query vs `120ms` a Brasil) — irrelevante para prod, pero `tinker` con `DB_HOST=oregon` ya no sirve como baseline local.
* `.env.neon.test` sigue con host Brasil de ejemplo; no se versionan secretos Oregon.

## Relación con ADRs

```text
ADR-002 → Production futura / VPS
ADR-008 → Staging / Koyeb + Neon (histórico)
ADR-009 → Staging / Render Oregon + RoadRunner + Neon Brasil (histórico desde 2026-09-01)
ADR-010 → Staging actual / Render Oregon + RoadRunner + Neon Oregon PG18 (co-localizado)
```

## Condición de revisión

Revisar cuando: Neon habilite `io_method=io_uring` en Oregon, se requiera `postgres:18-alpine` local para paridad, o se evalúe `persistence`/`read replica` para prod.
