---
paths:
  - vite.config.js
  - phpstan.neon
---

# General

## Vite en Docker: hot file con 0.0.0.0 rompe los assets
`npm run dev -- --host 0.0.0.0` (compose) hace que laravel-vite-plugin escriba public/hot con http://0.0.0.0:5173; el navegador interpreta 0.0.0.0 como su propia máquina y las páginas cargan SIN estilos ("se ve muy mal"). Fix: server.hmr.host = 'localhost' en vite.config.js (el hot file se genera del config HMR, no del CLI). Si vuelve a pasar: borrar public/hot y verificar el contenido antes de abrir la web.

## PHPStan analiza SOLO app/ (decisión aprobada — spec calidad-analisis-estatico)
PHPStan corre a nivel 8 únicamente sobre `app/` (paths en phpstan.neon); los tests se validan ejecutando la suite de Pest, no con análisis estático. Motivo: no existe soporte oficial de PHPStan para Pest 3.8 (el plugin de Pest requiere Pest 5) y la fricción de `->with()` es estructural. NO reintroducir phpstan-*.stub ni scanDirectories para intentar analizar tests; se documentaron como no viables. Ver `docs/specs/calidad-analisis-estatico.md` (ADR "Análisis estático de tests").
