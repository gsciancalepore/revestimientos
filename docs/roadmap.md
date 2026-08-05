# Roadmap

Última actualización: 2026-08-05.

## Definition of Done (aplica a TODAS las fases y specs)

Una fase/spec se considera terminada **solo** cuando cumple todo:

- [ ] Spec aprobada (y documentada en `docs/specs/`)
- [ ] Tests en verde (Pest: feature + unit según corresponda)
- [ ] PHPStan limpio (nivel 8, sin errores)
- [ ] Pint limpio (sin cambios pendientes)
- [ ] ADR actualizadas (si la fase introduce una decisión arquitectónica)
- [ ] `arquitectura.md` actualizada
- [ ] `roadmap.md` actualizado
- [ ] Sin TODOs
- [ ] Sin código comentado
- [ ] Sin warnings
- [ ] Merge a `main`

## Estado de fases

| Fase | Descripción | Estado |
|---|---|---|
| -2 | Constitución: `PROJECT_PRINCIPLES.md` | ✅ |
| -1 | Visión y lenguaje: `docs/vision.md`, `docs/ubiquitous-language.md` | ✅ (visión en revisión del dueño) |
| 0 | Dominio y arquitectura: spec 00, `arquitectura.md`, ADR-001..006 | ✅ |
| 1 | Fundación técnica: Docker, Laravel 12, calidad, CI | ✅ |
| 1b | Calidad de onboarding: spec `calidad-onboarding` (runbook, Makefile, README) | ✅ (2026-08-05) |
| 2 | Specs 01..09 (funcionales, TDD) | ⏳ siguiente |

## Fase 2 — Entregables funcionales

Cada spec se implementa en orden; cada una depende de la anterior
(autenticación → datos → catálogo → venta → operación).

| # | Spec | Contenido | Dominio | Estado |
|---|---|---|---|---|
| 01 | Autenticación y roles | Login admin (Breeze en `/admin`), usuarios internos, roles admin/vendedor/depósito (Spatie), Policies, auditoría de usuarios/roles | Users | implementada (Pint/PHPStan/Pest en verde); pendientes los tests Pest de gestión de usuarios y auditoría |
| 02 | Panel + Categorías | Layout admin, CRUD de categorías | Products | pendiente |
| 03 | Productos | Atributos de producto + atributos comerciales, precios m²/caja, ofertas, stock, imágenes | Products | pendiente |
| 04 | Catálogo público | Home, categorías, filtros, ficha con calculadora m²→cajas, stock visible, ofertas | Products | pendiente |
| 05 | Reglas del carrito | Spec de reglas: cajas enteras, desperdicio 10 %, stock/precio en cambio, reserva y vencimiento | Orders | pendiente |
| 06 | Carrito + Envío | Implementación del carrito + adaptador de envío por CP (ADR-006) | Orders | pendiente |
| 07 | Checkout | Compra anónima, MercadoPago, transferencia con confirmación manual, creación del pedido | Orders + Payments | pendiente |
| 08 | Gestión de pedidos | Estados, vista depósito, ventas WhatsApp manuales, restitución de stock | Orders | pendiente |
| 09 | Descuentos (opcional) | Por forma de pago y por monto de compra | Orders | pendiente |

## Fase 3 — Post-MVP (candidatas, sin compromiso)

- Extract Stock: movimientos y ajustes con auditoría (crea el dominio Inventory)
- Extract Customers: historial de compras por email/CP
- Tarifas de envío más ricas o API de cotización (implementación real del puerto)
- Compras a proveedores (nuevo dominio Suppliers)

## Notas de decisión

- El orden de la Fase 2 es deliberado: **el admin va antes que el catálogo público**
  para que la carga de datos sea la misma que en producción (sin datos demo
  artificiales ni seeders temporales).
- La Spec 01 introduce la **auditoría** (ADR-004) para usuarios y roles; el resto
  de las acciones críticas (precios, stock, pagos) se auditan en sus specs.
- La Spec 01 usa **Breeze 2.4.2 pineado** y conserva **Tailwind 4** (se restauró
  tras el instalador de Breeze, que lo baja a v3; ver ADR-007).

## Cómo continuar

- **Próximo paso**: escribir y aprobar la **Spec 02 (Panel + Categorías)** —
  layout administrativo y CRUD de categorías (dominio Products). Seguir el
  mismo formato de las specs existentes (`docs/specs/00-dominio.md` y
  `docs/specs/01-autenticacion-roles.md`: objetivo, contexto, reglas, matriz
  de permisos, casos borde, criterios de aceptación, tareas técnicas).
- **Proceso**: spec aprobada por el dueño → TDD (red → green → refactor) →
  verificación local (Pint, PHPStan nivel 8, Pest) → merge a `main` (el CI
  valida la misma secuencia).
- **Contexto para agentes nuevos**: `.ai/rules/index.md` mapea las reglas
  durables del repo; el runbook de arranque está en el README.
