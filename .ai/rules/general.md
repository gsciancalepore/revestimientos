---
paths:
  - vite.config.js
---

# General

## Vite en Docker: hot file con 0.0.0.0 rompe los assets
`npm run dev -- --host 0.0.0.0` (compose) hace que laravel-vite-plugin escriba public/hot con http://0.0.0.0:5173; el navegador interpreta 0.0.0.0 como su propia máquina y las páginas cargan SIN estilos ("se ve muy mal"). Fix: server.hmr.host = 'localhost' en vite.config.js (el hot file se genera del config HMR, no del CLI). Si vuelve a pasar: borrar public/hot y verificar el contenido antes de abrir la web.
