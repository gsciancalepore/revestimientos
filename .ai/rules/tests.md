---
paths:
  - 'tests/**'
---

# Tests

## Tests que renderizan vistas requieren assets de Vite (hot o build)
Los tests que hacen GET a vistas con @vite() (login, profile, reset, confirm-password) fallan con 500/ViteManifestNotFoundException si no existen public/hot (dev server) NI public/build/manifest.json (gitignored). En CI los assets se construyen explícitamente (pasos npm ci + npm run build en ci.yml). Localmente: public/hot activo con make npm-dev, o npm run build antes de testear. Los tests que solo hacen POST/PATCH (redirect) no renderizan y pasan igual.
