---
paths:
  - 'tests/**'
---

# Tests

## Tests que renderizan vistas requieren assets de Vite (hot o build)
Los tests que hacen GET a vistas con @vite() (login, profile, reset, confirm-password) fallan con 500/ViteManifestNotFoundException si no existen public/hot (dev server) NI public/build/manifest.json (gitignored). En CI los assets se construyen explícitamente (pasos npm ci + npm run build en ci.yml). Localmente: public/hot activo con make npm-dev, o npm run build antes de testear. Los tests que solo hacen POST/PATCH (redirect) no renderizan y pasan igual.

## Nunca correr dos suites Pest en paralelo; saneo con migrate:fresh dedicado
La base de test es PostgreSQL dedicada ceramica_test (phpunit.xml). Dos php artisan test concurrentes sobreponen migrate:fresh y corrompen la base (relation "migrations" does not exist / "users" already exists). Correr una sola suite a la vez. Para sanear: docker compose exec -e DB_DATABASE=ceramica_test app php artisan migrate:fresh --force (al 2026-09-10: 15 migraciones, 23 tablas; verificar el número real en lugar de confiar en este dato si pasó tiempo).

## Ningún test alcanza servicios externos
Las credenciales de servicios externos se neutralizan en phpunit.xml (MERCADOPAGO_ACCESS_TOKEN/MERCADOPAGO_PUBLIC_KEY vacías): sin eso la suite hereda el .env del desarrollador y, con un token real configurado, los tests crean recursos verdaderos contra la API (ocurrió con MercadoPago el 2026-09-10). Un test nunca debe depender de que el ambiente esté sin configurar para tomar el camino de error: bindear el gateway explícitamente en el contenedor. PreferenceClient del SDK de MercadoPago es final y no se puede mockear — por eso MercadoPagoGateway expone preferencePayload() protected y el test verifica el payload en vez del cliente.
