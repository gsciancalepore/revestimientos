---
paths:
  - vite.config.js
  - phpstan.neon
---

# General

## Vite en Docker: hot file con 0.0.0.0 rompe los assets
`npm run dev -- --host 0.0.0.0` (compose) hace que laravel-vite-plugin escriba public/hot con http://0.0.0.0:5173; el navegador interpreta 0.0.0.0 como su propia máquina y las páginas cargan SIN estilos ("se ve muy mal"). Fix: server.hmr.host = 'localhost' en vite.config.js (el hot file se genera del config HMR, no del CLI). Si vuelve a pasar: borrar public/hot y verificar el contenido antes de abrir la web.

## Stubs PHPStan para Pest: la fase de validación no tiene autoload
La validación de stubs de PHPStan no puede resolver clases del proyecto (ni PHPUnit ni Pest ni Illuminate\Testing). Solo conoce lo que carga el bootstrap de larastan (núcleo de Eloquent) y otros stubs del propio proyecto. Por eso phpstan-*.stub declaran Tests\TestCase, Illuminate\Testing\TestResponse y las funciones globales de Pest con @param-closure-this. Reglas: siempre tipos FQN (los use se ignoran), nada de genéricos array<string, mixed> nativos (error de sintaxis; usar @param), y no referenciar clases Pest en firmas de stubs.
