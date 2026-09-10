---
paths:
  - vite.config.js
  - phpstan.neon
---

# General

## Vite en Docker: hot file con 0.0.0.0 rompe los assets
`npm run dev -- --host 0.0.0.0` (compose) hace que laravel-vite-plugin escriba public/hot con http://0.0.0.0:5173; el navegador interpreta 0.0.0.0 como su propia máquina y las páginas cargan SIN estilos ("se ve muy mal"). Fix: server.hmr.host = 'localhost' en vite.config.js (el hot file se genera del config HMR, no del CLI). Si vuelve a pasar: borrar public/hot y verificar el contenido antes de abrir la web.

## Tras un `wsl --shutdown` hay que recrear los contenedores que publican puertos
Los contenedores levantan `healthy` y se hablan entre ellos —`web:80` contesta 200 desde el contenedor `app`— pero **los puertos publicados quedan muertos**: el navegador da `ERR_EMPTY_RESPONSE` en `http://localhost:8080` y `curl` da *connection reset* incluso desde adentro de WSL, contra `localhost`, `127.0.0.1` y la IP de la VM. Docker declara el mapeo correcto (`docker compose port web 80` → `0.0.0.0:8080`) y `docker-proxy` está escuchando: el puente host↔bridge es lo que quedó roto. **No es Windows, ni el firewall, ni el reenvío de WSL** — descartarlos rápido probando con `curl` desde adentro de WSL, que falla igual. Fix: `docker compose up -d --force-recreate web assets mailpit` (`restart` NO alcanza: no reinstala el binding). Recrear también `db` y `redis` solo si se los usa desde el host. Ocurrió el 2026-09-10 y costó una tarde; ver `docs/deployment/desarrollo-local.md`, que explica por qué hubo que reiniciar WSL.

## Si la web no carga, verificar el esquema antes de diagnosticar
El sitio local se sirve por **`http://localhost:8080`**, sin TLS. `https://localhost:8080` da `ERR_CONNECTION_CLOSED` (handshake TLS contra un puerto que habla HTTP) y `localhost` a secas va al puerto 80, donde no hay nada. Chrome con "Usar siempre conexiones seguras" (HTTPS-First) sube la URL a `https` solo, sobre todo después de una sesión con el túnel de `cloudflared`, que sí es HTTPS.

## PHPStan analiza SOLO app/ (decisión aprobada — spec calidad-analisis-estatico)
PHPStan corre a nivel 8 únicamente sobre `app/` (paths en phpstan.neon); los tests se validan ejecutando la suite de Pest, no con análisis estático. Motivo: no existe soporte oficial de PHPStan para Pest 3.8 (el plugin de Pest requiere Pest 5) y la fricción de `->with()` es estructural. NO reintroducir phpstan-*.stub ni scanDirectories para intentar analizar tests; se documentaron como no viables. Ver `docs/specs/calidad-analisis-estatico.md` (ADR "Análisis estático de tests").
