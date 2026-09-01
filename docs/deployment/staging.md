# Staging — Deployment Roadmap

**Proyecto:** Sistema de ventas para casa de cerámicas y revestimientos
**Estado:** Operativo (deploy en Render con estilos y HTTPS correctos — ver §15 y §15.2)
**Fecha:** 2026-09-01 (actualizado 2026-09-01: fix HTTPS TrustProxies + seed inicial Neon, ver §15.2/15.3)
**Objetivo:** disponer de un entorno remoto gratuito y permanente para que el equipo pueda probar el sistema desde distintas computadoras mientras continúa el desarrollo.

> **Nota 2026-09-01:** Koyeb exige plan pago para cuentas nuevas (Mistral AI). Staging actual es **Render Free + RoadRunner + Octane** (ADR-009), manteniendo **Neon** y el contrato `DB_*`. `docs/deployment/staging.md:15.2` y `ADR-009` documentan las 7 incidencias y fixes (5 de migración + Mixed Content HTTPS + seed Neon vacío).

---

## 1. Objetivo

Crear un entorno **Staging** accesible por Internet que permita:

* ejecutar la aplicación Laravel fuera de la PC del desarrollador;
* permitir que el socio acceda desde su PC;
* mantener PostgreSQL fuera del servidor de aplicación;
* desplegar automáticamente desde GitHub;
* ejecutar migraciones de base de datos de forma controlada;
* mantener secretos fuera del repositorio;
* validar cada cambio antes de considerarlo listo;
* disponer de un mecanismo básico de rollback;
* mantener el entorno de desarrollo local completamente independiente del entorno remoto.

El entorno debe ser suficientemente profesional para representar un flujo real de desarrollo → integración → staging, pero sin introducir infraestructura innecesaria para el tamaño actual del proyecto.

---

# 2. Arquitectura objetivo

```text
                         GitHub
                           │
                    Pull Request / Merge
                           │
                           ▼
                     CI / Quality Gates
                            │
                     Tests + Static Analysis
                            │
                            ▼
                      main
                             │
                             ▼
                       ┌─────────────┐
                       │   Render    │
                       │  Free Web   │
                       │   Laravel   │
                       │  RoadRunner │
                       │  Octane 2w  │
                       │  $PORT      │
                       └──────┬──────┘
                              │
                       HTTPS / Internet
                              │
                ┌─────────────┴─────────────┐
                │                           │
                ▼                           ▼
             Gabriel                     Socio
          desarrollo local             navegador
                │
                │
          Docker Compose
                │
         ┌──────┴───────┐
         │              │
      Laravel       PostgreSQL
         │  (nginx+fpm)   │
         └────── desarrollo local

                      Render
                         │
                         │ DB_* vars
                         │ (DB_HOST, DB_PORT,
                         │  DB_DATABASE, ...)
                         ▼
                    ┌───────────┐
                    │   Neon    │
                    │ PostgreSQL│
                    └───────────┘
```

---

# 3. Servicios

## 3.1 GitHub

Responsabilidades:

* repositorio central;
* control de versiones;
* Pull Requests;
* revisión de cambios;
* GitHub Actions;
* historial de despliegues;
* protección de la rama principal.

GitHub será la fuente de verdad del código.

---

## 3.2 Render (actual) — Koyeb histórico

Responsabilidades (Render Free Web Service, migrado desde Koyeb 2026-08-31 por plan pago para cuentas nuevas tras Mistral AI, ver ADR-009):

* ejecutar la aplicación Laravel;
* construir la aplicación desde GitHub;
* realizar deployments automáticos;
* proporcionar HTTPS;
* exponer una URL pública de staging.

Render Free actual: 750h/mes, 512 MB RAM, 0,1 vCPU, escala a cero tras ~15min sin tráfico (Koyeb era 2 GB SSD/1h). No se utiliza como producción definitiva.

Render soporta `Dockerfile` (`docker/koyeb/Dockerfile`, nombre histórico) y `PORT` inyectado, manteniendo la estrategia de containerización. `Koyeb` queda documentado en `ADR-008` como histórico.

---

## 3.3 Neon

Responsabilidades:

* PostgreSQL remoto para Staging;
* persistencia de los datos del entorno;
* aislamiento respecto de la base de datos local.

El plan Free actual proporciona 0,5 GB de almacenamiento por proyecto, 50 CU-hours mensuales por proyecto y scale-to-zero.

La aplicación utilizará la conexión mediante variables `DB_*` (`DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`) y nunca almacenará credenciales en Git. Neon entrega una connection string, pero en Render (antes Koyeb, ver ADR-009) se mapea a `DB_*` para mantener el contrato estándar de Laravel (ver §9).

---

# 4. Separación de entornos

El proyecto tendrá como mínimo dos entornos:

```text
DEVELOPMENT
    ↓
Docker Compose local
    ↓
PostgreSQL local

STAGING
     ↓
Render Free
     ↓
Neon PostgreSQL
```

Nunca se utilizará la base de datos de Staging desde el entorno de desarrollo como base de datos principal.

Nunca se utilizarán credenciales de Staging en archivos `.env` versionados.

---

# 5. Flujo de trabajo

El flujo esperado será:

```text
1. Crear branch
       ↓
2. Implementar funcionalidad
       ↓
3. Tests
       ↓
4. Pull Request
       ↓
5. CI
       ├── Tests
       ├── Static Analysis
       ├── Code Style
       └── Build
       ↓
6. Merge
       ↓
7. Deploy automático
       ↓
8. Migraciones
       ↓
9. Health Check
       ↓
10. Staging actualizado
       ↓
11. Validación funcional
```

El socio trabajará principalmente contra **Staging**, no contra el entorno local.

---

# 6. Estrategia de ramas

Estrategia adoptada (ADR-008): **main → Staging**. No se introduce rama `staging` en esta etapa.

```text
feature/*
    │
    ↓
Pull Request
    │
    ↓
CI (tests + PHPStan + Pint + build)
    │
    ↓
main  ──► Koyeb (deploy automático)
    │
    └── staging URL
```

`main` representa el código candidato a Staging. Cada `feature/*` se integra vía PR con quality gates. Una evolución a `develop → staging → production` se evaluará en una ADR futura cuando exista necesidad real. No se introduce complejidad de branches sin justificación.

---

# 7. CI/CD

GitHub Actions será responsable de verificar el código antes del deployment.

## Quality Gates

El pipeline deberá contemplar progresivamente:

```text
Install dependencies
        ↓
Application tests
        ↓
PHPStan
        ↓
Pint / code style
        ↓
Build verification
        ↓
Deploy
```

Un cambio que no supere los quality gates no deberá llegar a Staging.

---

# 8. Docker

La aplicación seguirá siendo containerizada. Misma aplicación, dos runtime adapters:

```text
DEVELOPMENT                 STAGING (Render Free)
    nginx  ─┐                  Laravel
    php-fpm ─┤ Laravel  vs      Octane RoadRunner 2 workers
    postgres ┘                  --host=0.0.0.0 --port=$PORT
             │                  Neon PostgreSQL
             └─ docker-compose  └─ docker/koyeb/Dockerfile (histórico Koyeb)
```

* **Desarrollo:** `docker/php/Dockerfile` (php-fpm 8.4) + `docker/nginx/default.conf` + `docker-compose.yml` — sin cambios.
* **Staging Render:** `docker/koyeb/Dockerfile` ( `php:8.4-cli-alpine` + `RoadRunner 2025.1.15` + `Octane 2.19` ) que ejecuta `php artisan octane:start --server=roadrunner --host=0.0.0.0 --port=${PORT} --workers=2 --max-requests=500` con `install-php-extensions pdo_pgsql bcmath intl zip opcache pcntl sockets redis` + `linux-headers`. Render inyecta `$PORT`; no se usa `supervisord` ni se hibrida el Dockerfile FPM. Esto no cambia la arquitectura de la app, solo el adapter de runtime. Ver `ADR-009` y `§15.1` para las 5 incidencias (Telescope, FrankenPHP EPERM, PORT, pcntl/sockets).

La regla es:

> El entorno remoto debe ejecutar la misma aplicación que se desarrolla localmente, modificando únicamente la infraestructura externa y la configuración.

---

# 9. Configuración

Toda configuración dependiente del entorno deberá utilizar variables de entorno.

Ejemplos:

```text
APP_ENV
APP_KEY
APP_DEBUG
APP_URL

DB_CONNECTION   # pgsql
DB_HOST         # ep-...neon.tech  (o db local)
DB_PORT         # 5432
DB_DATABASE
DB_USERNAME
DB_PASSWORD

MERCADOPAGO_ACCESS_TOKEN
MERCADOPAGO_PUBLIC_KEY
```

No se introduce `DATABASE_URL`; Neon entrega connection string pero en Koyeb se descompone en `DB_*` para respetar `config/database.php` y el contrato actual de `.env.example`.

Los valores reales de Staging estarán almacenados en el mecanismo de variables/secrets del proveedor (Koyeb Environment Variables).

Nunca:

```text
.env
.env.production
.env.staging
secrets.json
tokens.txt
```

en Git.

---

# 10. Laravel en Staging

Antes del primer deployment se verificará:

* `APP_ENV=staging` o estrategia equivalente;
* `APP_DEBUG=false`;
* `APP_KEY` generado específicamente para el entorno;
* URL pública correcta;
* conexión correcta con Neon;
* storage correctamente configurado;
* permisos de directorios;
* configuración de cache;
* configuración de sesiones;
* configuración de logs;
* configuración de correo;
* configuración de MercadoPago cuando corresponda.

---

# 11. Base de datos

La base de datos de Staging será creada independientemente de la base de datos local.

Las migraciones son parte del código versionado y se ejecutan de forma **controlada y separada del arranque del contenedor**. No se mezcla `php artisan migrate --force` en el `CMD` de arranque.

Secuencia para Staging inicial:

```text
Build
  ↓
Deploy container (artisan serve $PORT)
  ↓
Migration step controlado ──► php artisan migrate --force (job/step manual)
  ↓
Health check /up
  ↓
Ready
```

Nunca automáticamente en Staging:

```bash
php artisan migrate:fresh
php artisan db:wipe
```

El paso de migraciones podrá automatizarse más adelante como un job de deployment, pero siempre como step explícito, no como efecto colateral del reinicio del servicio.

---

# 12. Datos de Staging

Staging no utilizará datos reales de clientes.

Se podrán utilizar:

* datos ficticios;
* productos de prueba;
* pedidos de prueba;
* usuarios de prueba;
* credenciales sandbox de servicios externos.

El entorno debe poder destruirse y reconstruirse sin perder información de negocio real.

---

# 13. MercadoPago

Durante Staging se utilizarán credenciales de prueba/sandbox siempre que el flujo lo permita.

Las credenciales reales de producción no se incorporarán hasta que exista un entorno de producción separado.

La integración de pagos deberá poder distinguir:

```text
STAGING
    ↓
credenciales de prueba

PRODUCTION
    ↓
credenciales reales
```

---

# 14. Health Check

La aplicación dispone del endpoint de liveness de Laravel 12: `GET /up` (definido en `bootstrap/app.php: health: '/up'`). No se crea `/health` ni `HealthController` en esta etapa.

```text
GET /up
       ↓
HTTP 200
       ↓
Laravel alive + maintenance check
```

Koyeb permite configurar el HTTP health check sobre un path custom; se configurará sobre `/up`.

Diferencia conceptual:

* `/up` = liveness — ¿la app está viva? (suficiente para Staging inicial).
* `/health/ready` = readiness — ¿la app y sus dependencias (DB, Redis) están listas? Se evaluará en una segunda evolución si se necesita verificar Neon/Redis.

No se añade check de DB en esta primera iteración.

---

# 15. Deploy

El primer deployment será manual y controlado.

Una vez validado:

```text
GitHub
   ↓
Render Free
   ↓
Build (docker/koyeb/Dockerfile — histórico Koyeb)
   ↓
Deploy (Octane RoadRunner $PORT, 2 workers)
```

se habilitará el deployment automático desde `main`.

Render (migrado desde Koyeb 2026-08-31, ver ADR-009) soporta continuous deployment basado en GitHub: cada push/merge a `main` puede iniciar un nuevo build y deployment. Las migraciones se ejecutan como step controlado posterior al deploy (ver §11), no dentro del `CMD`.

> **Nota 2026-09-01 — 7 incidencias Render + fixes (migrado Koyeb → Render, ver ADR-009 y §15.2/15.3):**
> 1. **Telescope `require-dev`** (`cb1002b`): Render ejecuta `composer install --no-dev` (`Dockerfile:42`), `Telescope` no se instala en staging. Registro incondicional en `bootstrap/providers.php:4` → `package:discover Class not found`. Fix: registro condicional en `AppServiceProvider.php:12` solo `local && class_exists(TelescopeApplicationServiceProvider)`, `TELESCOPE_ENABLED=false`.
> 2. **`artisan serve` single-thread** (evidencia `/ 4.68s` bloquea `/ping 4.42s` concurrente): `php artisan serve` 1 thread en `Render Free` bloquea `/up` detrás de Neon. Migración a `Octane RoadRunner 2 workers` (`6b477bb`, `73d2945`).
> 3. **FrankenPHP `EPERM`** (`/usr/local/bin/frankenphp: Operation not permitted`): `FrankenPHP/Caddy` requiere `CAP_SYS_ADMIN` bloqueado en Render Free. Migración a `RoadRunner` (Golang, sin `CAP`).
> 4. **`pcntl` + `sockets` faltantes** (`InteractsWithServers.php:174 SIGINT`, `sockets.c:58 linux/sock_diag.h`): `php:8.4-cli-alpine` no trae `pcntl`/`sockets`; `RoadRunner` los requiere. Fixes: `pcntl` (`10b19a5`) + `sockets` + `linux-headers` (`e56e62c`) en `Dockerfile:18`.
> 5. **`$PORT` no expandido** (`StartFrankenPhpCommand.php:273 string - int`): `CMD ["php", ... "--port=${PORT:-8000}"]` exec JSON no expande `${PORT}` → `adminPort = 2019 + ("${PORT:-8000}" - 8000)` → `Unsupported operand`. Fix: `CMD ["sh","-c","php artisan octane:start ... --port=${PORT:-8000} ..."]` (`73d2945`).
> 6. **Mixed Content `http` detrás de proxy TLS** (`2026-09-01`, `bbfd1fd`): `APP_URL=https://revestimientos.onrender.com` correcto, pero Render termina TLS en LB y reenvía `http + X-Forwarded-Proto:https` a RoadRunner. Sin confiar en el proxy, `Request::getScheme()` queda `http` y `UrlGenerator`/`@vite`/`asset()`/`route()` generan `http://` → navegador bloquea CSS/JS y forms. Fix mínimo idiomático Laravel 12: `$middleware->trustProxies(at:'*')` en `bootstrap/app.php:17` (usa `Illuminate\Http\Middleware\TrustProxies` global, respeta `X-Forwarded-Proto`; local sin header sigue `http`).
> 7. **Neon sin seed — `users` vacío** (`2026-09-01`): deploy OK pero `users`/`roles`=`0` filas (11 migraciones aplicadas, `audit_logs` vacío). `COPY . .` + `.gitignore: rr` impide que el binario/seed viaje; el seed no está en `CMD`. Fix: seed controlado contra Neon: `docker compose run --rm -e DB_HOST=...neon.tech ... app php artisan db:seed --force` (crea `admin@ceramica.local`/`admin1234` + 3 roles + 4 categorías planas, idempotente vía `updateOrCreate`).
> **Estado actual:** deploy en `https://revestimientos.onrender.com` **operativo** con `Octane RoadRunner 2w` (`pcntl`/`sockets`/`linux-headers`, `PORT` OK, `trustProxies at:'*'` para HTTPS), `Neon` con 11 migraciones + seed completo (`users=1` admin, `roles=3`, `categories=4`), estilos OK vía `public/build`.

### 15.1 Pendiente histórico — Assets sin estilos (resuelto 2026-09-01)

**Síntoma 2026-08-31:** `https://revestimientos.onrender.com` responde `200` pero sin CSS (solo HTML). `public/build/manifest.json` local existe (`app-Izz6OxUL.css`), `node:22-alpine` en `docker/koyeb/Dockerfile:7` y `docker-compose.yml:77` es correcto para `vite 7.3` + `@tailwindcss/vite 4.3` (exige `node >=20`), `vite.config.js:14` `hmr.host=localhost` corrige `public/hot 0.0.0.0`, `.dockerignore` ignora `/public/hot` pero **no** `/public/build`, `Dockerfile:40` `COPY --from=assets` debería copiar `public/build`.

**Verificaciones realizadas:**
1. `assets` stage: `docker build --target assets -t test-assets && docker run --rm test-assets cat public/build/manifest.json` + `ls -lh public/build/assets` — OK.
2. Imagen final: `docker build -t test-final . && docker run --rm test-final ls -lh public/build && cat public/build/manifest.json` — OK.
3. Runtime: `docker run -p 8001:8000 -e PORT=8000 test-final` → `curl -I http://localhost:8001/build/assets/app-*.css` (`200`) y `curl -s http://localhost:8001/ | grep build/assets` — OK.

La causa real no era `public/build` ausente sino **Mixed Content por `http`** (ver §15.2): el HTML generaba `<link href="http://revestimientos.onrender.com/build/assets/...">` y el navegador lo bloqueaba. Con el fix `trustProxies` el HTML ya genera `https://` y los assets cargan.

### 15.2 Fix — Mixed Content HTTPS detrás de proxy (2026-09-01, `bbfd1fd`)

**Síntoma:** `https://revestimientos.onrender.com` con estilos bloqueados (Mixed Content) y forms con `action="http://..."` pese a `APP_URL=https://...` en Render y `vite build` OK (`/build/assets/app-DmkphLA-.css`).

**Causa:** Render termina TLS y proxy a RoadRunner vía `http` + `X-Forwarded-Proto:https`. Sin `$middleware->trustProxies(at:'*')` en `bootstrap/app.php:17`, `Illuminate\Http\Middleware\TrustProxies` deja `Request::setTrustedProxies([], ...)` y nunca confía en `X-Forwarded-Proto` (lógica `TrustProxies.php:67-98` con `proxies=null` sin `*.on-forge.com` auto-trust). `UrlGenerator` usa `request->getScheme()` → `http`.

**Fix:** `bootstrap/app.php:17` `$middleware->trustProxies(at:'*');` (método `Middleware::trustProxies()` → `TrustProxies::at('*')`). Valida `APP_URL=https` sin hardcodear `https`, no toca Vite/Dockerfile/Tailwind y respeta `http` local (sin header sigue `http`).

**Verificación post-fix:** `curl -s https://revestimientos.onrender.com/ | grep build/assets` → `https://`; `vendor/bin/pint --test`, `phpstan analyse`, `php artisan test` (116 tests) en verde.

### 15.3 Seed inicial Neon — base vacía (2026-09-01)

**Síntoma:** `/admin/login` con `admin@ceramica.local / admin1234` rechaza login ("credenciales no coinciden") pese a seed documentado en `README` y `spec 01:35`.

**Diagnóstico:** `SELECT count(*) FROM users` en Neon (`ep-cool-morning-aceox6ow-pooler.sa-east-1.aws.neon.tech/neondb`) → `0` (mismo `roles=0`, `categories=0`), `migrations=11`. El deploy se lanzó antes que terminara el pipeline de CI, sin ejecutar el step controlado de `§11`.

**Fix:** seed idempotente contra Neon (secuencia de `§11`):
```bash
docker compose run --rm \
  -e DB_CONNECTION=pgsql -e DB_HOST=ep-cool-morning-aceox6ow-pooler.sa-east-1.aws.neon.tech \
  -e DB_PORT=5432 -e DB_DATABASE=neondb -e DB_USERNAME=neondb_owner -e DB_PASSWORD=npg_... -e DB_SSLMODE=require \
  -e ADMIN_NAME=Admin -e ADMIN_EMAIL=admin@ceramica.local -e ADMIN_PASSWORD=admin1234 \
  app php artisan db:seed --force
# Database\Seeders\RolesSeeder 2227ms DONE, AdminSeeder 1448ms DONE, CategoriesSeeder 1079ms DONE
```
Resultado: `users=1 (Admin)`, `roles=3`, `categories=4` (porcelanatos, ceramicas, pastinas, adhesivos). Login operativo. Futuros deploys no requieren re-seed salvo `migrate:fresh` (nunca en staging, §11).

---

# 16. Rollback

Debe existir un procedimiento documentado para volver a la última versión funcional.

Conceptualmente:

```text
Version N
   ↓
Deploy
   ↓
Error
   ↓
Rollback
   ↓
Version N-1
```

El rollback de aplicación y el rollback de base de datos se tratarán como problemas diferentes.

No se asumirán automáticamente migraciones reversibles.

Las migraciones destructivas deberán diseñarse con especial cuidado.

---

# 17. Seguridad mínima

Antes de publicar Staging:

* `APP_DEBUG=false`;
* secretos fuera de Git;
* HTTPS obligatorio;
* credenciales diferentes de producción;
* usuario administrativo de prueba;
* contraseñas fuertes;
* no utilizar datos personales reales;
* revisar `.gitignore`;
* revisar historial Git por posibles secretos;
* limitar el acceso administrativo cuando sea necesario.

---

# 18. Observabilidad inicial

No se implementará una plataforma completa de observabilidad todavía.

En esta etapa será suficiente con:

```text
Koyeb logs
Laravel logs
Deployment status
Health check
GitHub Actions
```

Se podrá agregar posteriormente:

```text
OpenTelemetry
Grafana
Prometheus
Sentry
etc.
```

cuando exista una necesidad real.

---

# 19. Backups

Neon será responsable de la persistencia de PostgreSQL en Staging.

El objetivo de Staging no es garantizar recuperación de datos de negocio.

La estrategia de backups de producción será definida posteriormente y no se considerará resuelta por esta etapa.

---

# 20. Dominio

Inicialmente se utilizará el dominio proporcionado por Koyeb.

Posteriormente:

```text
Staging
    ↓
staging.<dominio>

Production
    ↓
www.<dominio>
```

El dominio propio no es requisito para completar esta etapa.

---

# 21. Criterios de aceptación

El deployment se considerará terminado cuando:

* [ ] El repositorio esté correctamente conectado a GitHub.
* [ ] El proyecto pueda construirse mediante Docker.
* [ ] Koyeb pueda ejecutar la aplicación.
* [ ] Neon proporcione PostgreSQL para Staging.
* [ ] Laravel pueda conectarse correctamente a Neon.
* [ ] Las migraciones puedan ejecutarse de forma segura.
* [ ] `APP_DEBUG` esté deshabilitado.
* [ ] Los secretos estén fuera de Git.
* [ ] La aplicación tenga HTTPS.
* [ ] Exista una URL pública de Staging.
* [ ] El socio pueda acceder desde su PC.
* [ ] El pipeline de CI ejecute los tests.
* [ ] Un merge aprobado pueda desplegarse automáticamente.
* [ ] Exista un procedimiento documentado de rollback.
* [ ] Exista un health check en `GET /up`.
* [ ] El entorno local continúe funcionando independientemente de Staging.

---

# 22. Orden de implementación

## Fase 0 — Auditoría

* [ ] Revisar estado actual del repositorio.
* [ ] Revisar `Dockerfile`.
* [ ] Revisar `docker-compose.yml`.
* [ ] Revisar `.env.example`.
* [ ] Revisar configuración de producción.
* [ ] Identificar servicios realmente utilizados.
* [ ] Verificar que la aplicación arranque desde cero.
* [ ] Ejecutar suite de tests.
* [ ] Verificar migraciones.

## Fase 1 — Git

* [ ] Confirmar repositorio GitHub.
* [ ] Revisar `.gitignore`.
* [ ] Revisar historial por secretos.
* [x] Definir estrategia de branches — `main → Staging` (ADR-008).
* [x] Definir rama de Staging — `main` (sin rama `staging`).

## Fase 2 — Neon

* [ ] Crear proyecto PostgreSQL.
* [ ] Crear base de datos de Staging.
* [ ] Obtener credenciales.
* [ ] Probar conexión desde local.
* [ ] Documentar variables requeridas.
* [ ] No almacenar secretos en Git.

## Fase 3 — Koyeb

* [ ] Crear cuenta/proyecto.
* [ ] Conectar GitHub (rama `main`).
* [ ] Configurar servicio Web.
* [ ] Configurar Dockerfile `docker/koyeb/Dockerfile` (artisan serve `$PORT`).
* [ ] Configurar variables de entorno (`DB_*`, `APP_*`).
* [ ] Configurar puerto (`$PORT` inyectado por Koyeb).
* [ ] Configurar health check `GET /up`.
* [ ] Ejecutar primer deployment.

## Fase 4 — Laravel

* [ ] Ejecutar migraciones.
* [ ] Crear usuario administrativo de Staging.
* [ ] Configurar storage.
* [ ] Configurar cache/sesiones.
* [ ] Configurar URL.
* [ ] Verificar catálogo.
* [ ] Verificar carrito.
* [ ] Verificar checkout cuando esté implementado.

## Fase 5 — CI/CD

* [ ] Crear GitHub Actions.
* [ ] Ejecutar tests.
* [ ] Ejecutar PHPStan.
* [ ] Ejecutar Pint.
* [ ] Ejecutar build.
* [ ] Configurar deployment.
* [ ] Configurar protección de rama.
* [ ] Verificar deployment automático.

## Fase 6 — Validación con el socio

* [ ] Entregar URL de Staging.
* [ ] Crear usuario de prueba.
* [ ] Validar navegación.
* [ ] Validar catálogo.
* [ ] Validar cálculo m²/cajas.
* [ ] Validar carrito.
* [ ] Validar flujo de pedido.
* [ ] Registrar feedback.
* [ ] Convertir feedback aprobado en nuevas Specs.

## Fase 7 — Operación

* [ ] Documentar procedimiento de deploy.
* [ ] Documentar rollback.
* [ ] Documentar recuperación.
* [ ] Documentar variables de entorno.
* [ ] Documentar arquitectura.
* [ ] Revisar costos y límites periódicamente.

---

# 23. Regla de evolución

El entorno de Staging no debe convertirse accidentalmente en Producción.

Cuando el sistema esté listo para clientes reales se definirá una arquitectura de Production independiente.

La evolución prevista es:

```text
                    AHORA

              Development
                   │
                   ↓
               Staging
             Koyeb + Neon
                   │
                   ↓
              Socio / QA


                  FUTURO

              Development
                   │
                   ↓
               Staging
                   │
                   ↓
              Production
                   │
                   ↓
                Clientes
```

La arquitectura de Production será decidida cuando existan requisitos reales de disponibilidad, tráfico, backups, observabilidad, seguridad y costos.

---

# 24. Principio rector

> **No se despliega solamente para que "funcione". Se construye un proceso reproducible mediante el cual cualquier cambio pueda pasar de desarrollo a Staging de forma controlada, verificable y reversible.**

El objetivo de esta etapa no es tener infraestructura sofisticada.

El objetivo es tener **un proceso profesional y repetible**.
